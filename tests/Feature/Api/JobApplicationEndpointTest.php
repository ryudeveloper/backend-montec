<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Mail\JobApplicationReceived;
use App\Models\JobApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class JobApplicationEndpointTest extends TestCase
{
    use RefreshDatabase;

    /** PDF mínimo válido — o finfo precisa reconhecer pelo conteúdo, não pela extensão. */
    private function pdfBytes(): string
    {
        return "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
    }

    private function docxBytes(): string
    {
        // Zip mínimo: .docx é um container OOXML, e o finfo o vê como zip.
        $path = tempnam(sys_get_temp_dir(), 'docx-');
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document/>');
        $zip->close();
        $contents = (string) file_get_contents($path);
        @unlink($path);

        return $contents;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = [], ?string $resumeBytes = null, string $resumeName = 'curriculo.pdf'): array
    {
        return [
            'nome' => 'João Pereira',
            'email' => 'joao@exemplo.com',
            'telefone' => '19996566767',
            'vaga' => 'Soldador',
            'mensagem' => 'Tenho 8 anos de experiência em solda MIG.',
            'curriculo' => [
                'data' => base64_encode($resumeBytes ?? $this->pdfBytes()),
                'name' => $resumeName,
            ],
            ...$overrides,
        ];
    }

    #[Test]
    public function aceita_candidatura_com_pdf_valido(): void
    {
        Mail::fake();
        Storage::fake('resumes');

        $this->postJson('/api/trabalhe-conosco', $this->payload())
            ->assertCreated()
            ->assertJsonStructure(['message', 'protocolo']);

        $application = JobApplication::sole();
        $this->assertSame('application/pdf', $application->resume_mime);
        Storage::disk('resumes')->assertExists($application->resume_path);
        Mail::assertSent(JobApplicationReceived::class);
    }

    #[Test]
    public function aceita_docx(): void
    {
        Mail::fake();
        Storage::fake('resumes');

        $this->postJson('/api/trabalhe-conosco', $this->payload(
            resumeBytes: $this->docxBytes(),
            resumeName: 'curriculo.docx',
        ))->assertCreated();

        $this->assertSame(1, JobApplication::count());
    }

    /**
     * O nome enviado pelo cliente NUNCA compõe o caminho em disco. Sem isso,
     * "../../../.env" como nome de arquivo escreve fora do diretório.
     */
    #[Test]
    public function nunca_usa_o_nome_original_como_caminho(): void
    {
        Mail::fake();
        Storage::fake('resumes');

        $this->postJson('/api/trabalhe-conosco', $this->payload(
            resumeName: '../../../../etc/passwd.pdf',
        ))->assertCreated();

        $application = JobApplication::sole();

        $this->assertStringNotContainsString('..', $application->resume_path);
        $this->assertStringNotContainsString('passwd', $application->resume_path);
        $this->assertMatchesRegularExpression('#^\d{4}/\d{2}/[0-9A-Z]{26}\.pdf$#', $application->resume_path);
        // O nome original sobrevive apenas como rótulo, para exibir no e-mail.
        $this->assertSame('../../../../etc/passwd.pdf', $application->resume_original_name);
    }

    /** Executável renomeado para .pdf: a extensão engana, o conteúdo não. */
    #[Test]
    public function recusa_arquivo_cujo_conteudo_nao_corresponde_a_extensao(): void
    {
        Mail::fake();
        Storage::fake('resumes');

        $this->postJson('/api/trabalhe-conosco', $this->payload(
            resumeBytes: "MZ\x90\x00\x03\x00\x00\x00PE executável disfarçado",
            resumeName: 'curriculo.pdf',
        ))
            ->assertStatus(422)
            ->assertJsonValidationErrors('curriculo');

        $this->assertSame(0, JobApplication::count());
        Mail::assertNothingSent();
    }

    /** PDF de verdade, extensão perigosa: barrado pela whitelist de extensão. */
    #[Test]
    public function recusa_extensao_fora_da_whitelist(): void
    {
        Storage::fake('resumes');

        $this->postJson('/api/trabalhe-conosco', $this->payload(resumeName: 'curriculo.php'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('curriculo');
    }

    #[Test]
    public function recusa_arquivo_acima_de_5mb(): void
    {
        Storage::fake('resumes');

        $oversized = $this->pdfBytes().str_repeat('A', 5 * 1024 * 1024);

        // A primeira barreira é o teto do base64 em `curriculo.data`, antes de
        // alocar memória decodificando um corpo absurdo.
        $this->postJson('/api/trabalhe-conosco', $this->payload(resumeBytes: $oversized))
            ->assertStatus(422)
            ->assertJsonValidationErrors('curriculo.data');

        $this->assertSame(0, JobApplication::count());
    }

    #[Test]
    public function recusa_base64_corrompido(): void
    {
        Storage::fake('resumes');

        $this->postJson('/api/trabalhe-conosco', [
            ...$this->payload(),
            'curriculo' => ['data' => 'isto###nao###e###base64', 'name' => 'curriculo.pdf'],
        ])->assertStatus(422)->assertJsonValidationErrors('curriculo');
    }

    #[Test]
    public function recusa_arquivo_vazio(): void
    {
        Storage::fake('resumes');

        $this->postJson('/api/trabalhe-conosco', [
            ...$this->payload(),
            'curriculo' => ['data' => '', 'name' => 'curriculo.pdf'],
        ])->assertStatus(422)->assertJsonValidationErrors('curriculo.data');
    }

    #[Test]
    public function descarta_envio_com_honeypot_preenchido(): void
    {
        Mail::fake();
        Storage::fake('resumes');

        $this->postJson('/api/trabalhe-conosco', $this->payload(['website' => 'bot']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('website');

        $this->assertSame(0, JobApplication::count());
    }

    /** Currículo é dado pessoal: arquivo sem registro é retenção sem base legal. */
    #[Test]
    public function nao_deixa_arquivo_orfao_quando_a_gravacao_falha(): void
    {
        Mail::fake();
        Storage::fake('resumes');

        // Derruba o insert sem tocar no disco: a tabela deixa de existir.
        Schema::drop('job_applications');

        try {
            $this->postJson('/api/trabalhe-conosco', $this->payload());
        } catch (\Throwable) {
            // A falha é o cenário; o que importa é o disco depois dela.
        }

        $this->assertEmpty(Storage::disk('resumes')->allFiles());
    }
}
