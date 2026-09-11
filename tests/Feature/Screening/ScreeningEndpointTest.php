<?php

declare(strict_types=1);

namespace Tests\Feature\Screening;

use App\Models\JobApplication;
use App\Models\PortalAccessLog;
use App\Models\ResumeAssessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FakePdf;
use Tests\TestCase;

/**
 * Geração do parecer, do arquivo ao registro.
 *
 * O teste que mais importa aqui é o do vazamento: o currículo sai desta máquina
 * para um terceiro, e nenhum identificador do candidato pode ir junto.
 */
final class ScreeningEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function enable(): void
    {
        config()->set('montec.ai.key', 'chave-de-teste');
    }

    private function fakeApi(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'registrar_parecer',
                    'input' => [
                        'score' => 78,
                        'strengths' => ['8 anos em solda MIG'],
                        'gaps' => ['Sem TIG'],
                        'skills' => ['MIG', 'NR-34'],
                    ],
                ]],
            ]),
        ]);
    }

    /** PDF com texto de verdade, para o extrator ter o que ler. */
    private function application(string $resumeText = ''): JobApplication
    {
        $texto = $resumeText !== '' ? $resumeText : implode("\n", [
            'Joao Pereira da Silva',
            'joao.pereira@exemplo.com',
            '(19) 99888-7766',
            'CPF 123.456.789-09',
            'Nascido em 14/03/1987, 38 anos',
            '',
            'Soldador com 8 anos de experiencia em MIG e MAG.',
            'Certificacao NR-34 vigente. Atuou com chapa de 6mm em estrutura metalica.',
            'Formacao: Tecnico em Mecanica pelo SENAI de Mococa.',
        ]);

        // PDF mínimo com texto extraível pelo pdfparser.
        $pdf = FakePdf::withText($texto);
        Storage::disk('resumes')->put('2026/09/DEMO.pdf', $pdf);

        return JobApplication::create([
            'name' => 'Joao Pereira da Silva',
            'email' => 'joao.pereira@exemplo.com',
            'phone' => '19998887766',
            'job_opening' => 'Montador/Soldador',
            'resume_path' => '2026/09/DEMO.pdf',
            'resume_original_name' => 'cv.pdf',
            'resume_mime' => 'application/pdf',
            'resume_bytes' => strlen($pdf),
        ]);
    }

    /**
     * Volta para a tela do candidato, não para a lista.
     *
     * Era `back()`, que depende de referer e de estado de sessão: na prática o
     * RH clicava em "Gerar parecer" e era jogado de volta para a listagem, sem
     * ver o resultado. Ação que pertence a um recurso redireciona para ele.
     */
    #[Test]
    public function gera_grava_e_volta_para_a_tela_do_candidato(): void
    {
        Storage::fake('resumes');
        $this->enable();
        $this->fakeApi();
        $application = $this->application();

        $this->actingAs(User::factory()->create(['roles' => ['hr']]))
            ->post("/rh/candidaturas/{$application->id}/parecer")
            ->assertRedirect(route('portal.applications.show', $application));

        $parecer = ResumeAssessment::sole();
        $this->assertSame(78, $parecer->score);
        $this->assertSame(['MIG', 'NR-34'], $parecer->skills);
        $this->assertSame($application->id, $parecer->job_application_id);
    }

    /**
     * O TESTE CENTRAL: nenhum identificador do candidato pode sair desta máquina.
     *
     * Currículo indo para terceiro é tratamento de dado pessoal. A redação
     * acontece antes da chamada, e é aqui que se verifica que ela de fato pegou.
     */
    #[Test]
    public function nao_envia_nenhum_identificador_do_candidato(): void
    {
        Storage::fake('resumes');
        $this->enable();
        $this->fakeApi();
        $application = $this->application();

        $this->actingAs(User::factory()->create(['roles' => ['hr']]))
            ->post("/rh/candidaturas/{$application->id}/parecer");

        Http::assertSent(function (ClientRequest $request): bool {
            $corpo = $request->body();

            foreach ([
                'Joao Pereira',
                'Pereira',
                'joao.pereira@exemplo.com',
                '99888-7766',
                '19998887766',
                '123.456.789-09',
                '14/03/1987',
                '38 anos',
            ] as $identificador) {
                if (str_contains($corpo, $identificador)) {
                    return false;
                }
            }

            // E o que interessa para a vaga precisa ter sobrevivido.
            return str_contains($corpo, 'MIG')
                && str_contains($corpo, 'NR-34')
                && str_contains($corpo, '8 anos de experiencia');
        });
    }

    #[Test]
    public function registra_a_analise_na_trilha_de_auditoria(): void
    {
        Storage::fake('resumes');
        $this->enable();
        $this->fakeApi();
        $application = $this->application();
        $user = User::factory()->create(['roles' => ['hr']]);

        $this->actingAs($user)->post("/rh/candidaturas/{$application->id}/parecer");

        $log = PortalAccessLog::sole();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('analyzed', $log->action);
        $this->assertSame($application->id, $log->resource_id);
    }

    /** Gerar de novo substitui — não acumula pareceres divergentes. */
    #[Test]
    public function gerar_de_novo_substitui_o_parecer_anterior(): void
    {
        Storage::fake('resumes');
        $this->enable();
        $this->fakeApi();
        $application = $this->application();
        $user = User::factory()->create(['roles' => ['hr']]);

        $this->actingAs($user)->post("/rh/candidaturas/{$application->id}/parecer");
        $this->actingAs($user)->post("/rh/candidaturas/{$application->id}/parecer");

        $this->assertSame(1, ResumeAssessment::count());
        // Mas as duas leituras ficam na trilha.
        $this->assertSame(2, PortalAccessLog::count());
    }

    #[Test]
    public function quem_nao_ve_curriculo_nao_gera_parecer(): void
    {
        Storage::fake('resumes');
        $this->enable();
        Http::preventStrayRequests();
        $application = $this->application();

        $this->actingAs(User::factory()->create(['roles' => ['ombudsman']]))
            ->post("/rh/candidaturas/{$application->id}/parecer")
            ->assertForbidden();

        $this->assertSame(0, ResumeAssessment::count());
    }

    #[Test]
    public function visitante_anonimo_e_mandado_para_o_login(): void
    {
        Storage::fake('resumes');
        $application = $this->application();

        $this->post("/rh/candidaturas/{$application->id}/parecer")->assertRedirect('/rh/login');
    }

    /** Falha do serviço volta como aviso, não como 500. */
    #[Test]
    public function falha_da_api_volta_com_mensagem_legivel(): void
    {
        Storage::fake('resumes');
        $this->enable();
        Http::fake(['api.anthropic.com/*' => Http::response([], 529)]);
        $application = $this->application();

        $this->actingAs(User::factory()->create(['roles' => ['hr']]))
            ->post("/rh/candidaturas/{$application->id}/parecer")
            ->assertRedirect(route('portal.applications.show', $application))
            ->assertSessionHasErrors('screening');

        $this->assertSame(0, ResumeAssessment::count());
    }

    /** PDF digitalizado não tem texto: avisa em vez de mandar folha em branco. */
    #[Test]
    public function pdf_sem_texto_avisa_em_vez_de_analisar(): void
    {
        Storage::fake('resumes');
        $this->enable();
        Http::preventStrayRequests();

        Storage::disk('resumes')->put('2026/09/VAZIO.pdf', "%PDF-1.4\ntrailer<</Root 1 0 R>>\n%%EOF\n");
        $application = JobApplication::create([
            'name' => 'Maria', 'email' => 'm@exemplo.com', 'phone' => '19998887766',
            'job_opening' => 'Pintor Industrial', 'resume_path' => '2026/09/VAZIO.pdf',
            'resume_original_name' => 'cv.pdf', 'resume_mime' => 'application/pdf', 'resume_bytes' => 40,
        ]);

        $this->actingAs(User::factory()->create(['roles' => ['hr']]))
            ->post("/rh/candidaturas/{$application->id}/parecer")
            ->assertSessionHasErrors('screening');
    }

    #[Test]
    public function sem_chave_configurada_nao_gera(): void
    {
        Storage::fake('resumes');
        Http::preventStrayRequests();
        $application = $this->application();

        $this->actingAs(User::factory()->create(['roles' => ['hr']]))
            ->post("/rh/candidaturas/{$application->id}/parecer")
            ->assertSessionHasErrors('screening');

        $this->assertSame(0, ResumeAssessment::count());
    }
}
