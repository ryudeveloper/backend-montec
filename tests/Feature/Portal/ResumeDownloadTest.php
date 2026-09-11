<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\JobApplication;
use App\Models\PortalAccessLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Download de currículo — a fronteira mais sensível do portal.
 *
 * O arquivo mora fora do webroot exatamente para não ser alcançável pela web.
 * Esta rota é a única exceção, e por isso concentra as garantias: autenticação,
 * autorização, entrega como anexo (nunca inline) e registro de auditoria.
 */
final class ResumeDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function application(): JobApplication
    {
        Storage::disk('resumes')->put('2026/09/ABC.pdf', "%PDF-1.4\nconteudo\n%%EOF");

        return JobApplication::create([
            'name' => 'João Pereira',
            'email' => 'joao@exemplo.com',
            'phone' => '19996566767',
            'job_opening' => 'Soldador',
            'resume_path' => '2026/09/ABC.pdf',
            'resume_original_name' => 'curriculo joão.pdf',
            'resume_mime' => 'application/pdf',
            'resume_bytes' => 22,
        ]);
    }

    #[Test]
    public function visitante_anonimo_nao_baixa_curriculo(): void
    {
        Storage::fake('resumes');
        $application = $this->application();

        $this->get("/rh/candidaturas/{$application->id}/curriculo")->assertRedirect('/rh/login');
    }

    #[Test]
    public function usuario_sem_papel_nao_baixa_curriculo(): void
    {
        Storage::fake('resumes');
        $application = $this->application();

        $this->actingAs(User::factory()->create(['roles' => []]))
            ->get("/rh/candidaturas/{$application->id}/curriculo")
            ->assertForbidden();
    }

    #[Test]
    public function o_rh_baixa_o_curriculo(): void
    {
        Storage::fake('resumes');
        $application = $this->application();

        $response = $this->actingAs(User::factory()->create(['roles' => ['hr']]))
            ->get("/rh/candidaturas/{$application->id}/curriculo");

        $response->assertOk();
        $this->assertSame("%PDF-1.4\nconteudo\n%%EOF", $response->streamedContent());
    }

    /**
     * SEMPRE como anexo, nunca inline.
     *
     * Um PDF ou HTML servido inline executa no contexto da própria origem: é
     * XSS com o arquivo que um estranho enviou. `attachment` + `nosniff` fecham
     * isso — o navegador baixa em vez de renderizar, e não adivinha o tipo.
     */
    #[Test]
    public function o_curriculo_e_entregue_como_anexo_e_sem_sniffing(): void
    {
        Storage::fake('resumes');
        $application = $this->application();

        $response = $this->actingAs(User::factory()->create(['roles' => ['hr']]))
            ->get("/rh/candidaturas/{$application->id}/curriculo");

        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringStartsWith('attachment;', $disposition);
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    /**
     * LGPD pede responsabilização: é preciso saber quem acessou o dado de quem.
     * Sem trilha, vazamento interno não tem como ser investigado.
     */
    #[Test]
    public function todo_download_deixa_registro_de_auditoria(): void
    {
        Storage::fake('resumes');
        $application = $this->application();
        $user = User::factory()->create(['roles' => ['hr']]);

        $this->actingAs($user)->get("/rh/candidaturas/{$application->id}/curriculo")->assertOk();

        $log = PortalAccessLog::sole();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame(PortalAccessLog::RESOURCE_RESUME, $log->resource_type);
        $this->assertSame($application->id, $log->resource_id);
        $this->assertSame('downloaded', $log->action);
    }

    /** Tentativa negada não pode gerar registro: poluiria a trilha real. */
    #[Test]
    public function acesso_negado_nao_gera_registro_de_download(): void
    {
        Storage::fake('resumes');
        $application = $this->application();

        $this->actingAs(User::factory()->create(['roles' => []]))
            ->get("/rh/candidaturas/{$application->id}/curriculo")
            ->assertForbidden();

        $this->assertSame(0, PortalAccessLog::count());
    }

    /** Arquivo apagado do disco não pode virar erro 500 na cara do usuário. */
    #[Test]
    public function arquivo_ausente_responde_404_e_nao_quebra(): void
    {
        Storage::fake('resumes');
        $application = $this->application();
        Storage::disk('resumes')->delete($application->resume_path);

        $this->actingAs(User::factory()->create(['roles' => ['hr']]))
            ->get("/rh/candidaturas/{$application->id}/curriculo")
            ->assertNotFound();
    }

    /** Id inexistente não deve revelar se existe ou não por mensagem diferente. */
    #[Test]
    public function candidatura_inexistente_responde_404(): void
    {
        $this->actingAs(User::factory()->create(['roles' => ['hr']]))
            ->get('/rh/candidaturas/01ABCDEFGHIJKLMNOPQRSTUVWX/curriculo')
            ->assertNotFound();
    }
}
