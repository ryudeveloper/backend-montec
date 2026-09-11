<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\JobApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Upload multipart — caminho preferido.
 *
 * Base64 no corpo JSON continua aceito para não quebrar clientes antigos, mas
 * multipart é melhor em três frentes: o payload é o tamanho real do arquivo (e
 * não 133% dele), o PHP escreve direto em arquivo temporário em vez de carregar
 * tudo em memória, e o tipo chega como UploadedFile já sanitizado.
 *
 * A validação de segurança é EXATAMENTE a mesma nos dois caminhos: ambos passam
 * pelo ResumeDecoder, que fareja o MIME no conteúdo.
 */
final class ResumeMultipartTest extends TestCase
{
    use RefreshDatabase;

    private function pdf(string $name = 'curriculo.pdf'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf-');
        file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function fields(array $overrides = []): array
    {
        return [
            'nome' => 'João Pereira',
            'email' => 'joao@exemplo.com',
            'telefone' => '19996566767',
            'vaga' => 'Soldador',
            'mensagem' => '8 anos em solda MIG.',
            ...$overrides,
        ];
    }

    #[Test]
    public function aceita_curriculo_como_arquivo_multipart(): void
    {
        Mail::fake();
        Storage::fake('resumes');

        $this->post('/api/trabalhe-conosco', [
            ...$this->fields(),
            'curriculo' => $this->pdf(),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonStructure(['message', 'protocolo']);

        $application = JobApplication::sole();
        $this->assertSame('application/pdf', $application->resume_mime);
        Storage::disk('resumes')->assertExists($application->resume_path);
    }

    /** Mesma proteção do caminho base64: o conteúdo manda, não a extensão. */
    #[Test]
    public function recusa_executavel_disfarcado_tambem_no_multipart(): void
    {
        Mail::fake();
        Storage::fake('resumes');

        $path = tempnam(sys_get_temp_dir(), 'exe-');
        file_put_contents($path, "MZ\x90\x00\x03\x00\x00\x00PE disfarçado");
        $fake = new UploadedFile($path, 'curriculo.pdf', 'application/pdf', null, true);

        $this->post('/api/trabalhe-conosco', [
            ...$this->fields(),
            'curriculo' => $fake,
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('curriculo');

        $this->assertSame(0, JobApplication::count());
    }

    #[Test]
    public function nunca_usa_o_nome_original_como_caminho_no_multipart(): void
    {
        Mail::fake();
        Storage::fake('resumes');

        $this->post('/api/trabalhe-conosco', [
            ...$this->fields(),
            'curriculo' => $this->pdf('../../../../etc/passwd.pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $application = JobApplication::sole();
        $this->assertStringNotContainsString('..', $application->resume_path);
        $this->assertMatchesRegularExpression('#^\d{4}/\d{2}/[0-9A-Z]{26}\.pdf$#', $application->resume_path);
    }

    #[Test]
    public function recusa_arquivo_multipart_acima_do_limite(): void
    {
        Storage::fake('resumes');

        $path = tempnam(sys_get_temp_dir(), 'big-');
        file_put_contents($path, "%PDF-1.4\n".str_repeat('A', 6 * 1024 * 1024));
        $big = new UploadedFile($path, 'curriculo.pdf', 'application/pdf', null, true);

        $this->post('/api/trabalhe-conosco', [
            ...$this->fields(),
            'curriculo' => $big,
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('curriculo');
    }

    /** O contrato antigo não pode quebrar: o frontend em produção ainda o usa. */
    #[Test]
    public function o_caminho_base64_continua_funcionando(): void
    {
        Mail::fake();
        Storage::fake('resumes');

        $this->postJson('/api/trabalhe-conosco', [
            ...$this->fields(),
            'curriculo' => [
                'data' => base64_encode("%PDF-1.4\ntrailer<</Root 1 0 R>>\n%%EOF\n"),
                'name' => 'curriculo.pdf',
            ],
        ])->assertCreated();

        $this->assertSame(1, JobApplication::count());
    }

    #[Test]
    public function exige_o_curriculo_em_um_dos_dois_formatos(): void
    {
        Storage::fake('resumes');

        $this->postJson('/api/trabalhe-conosco', $this->fields())
            ->assertStatus(422)
            ->assertJsonValidationErrors('curriculo');
    }

    #[Test]
    public function honeypot_tambem_vale_no_multipart(): void
    {
        Mail::fake();
        Storage::fake('resumes');

        $this->post('/api/trabalhe-conosco', [
            ...$this->fields(['website' => 'bot']),
            'curriculo' => $this->pdf(),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('website');
    }
}
