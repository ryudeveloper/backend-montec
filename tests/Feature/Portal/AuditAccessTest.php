<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\JobApplication;
use App\Models\PortalAccessLog;
use App\Models\User;
use App\Models\WhistleblowerReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Trilha de auditoria — exclusiva da administração.
 *
 * Saber quem abriu o currículo de quem, e quem leu qual denúncia, é informação
 * de supervisão. Nas mãos de quem opera a área, vira ferramenta para descobrir
 * que um colega está sendo investigado.
 */
final class AuditAccessTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['roles' => ['admin']]);
    }

    private function logEntry(User $operator, string $type, string $resourceId): PortalAccessLog
    {
        return PortalAccessLog::create([
            'user_id' => $operator->id,
            'resource_type' => $type,
            'resource_id' => $resourceId,
            'action' => $type === PortalAccessLog::RESOURCE_RESUME ? 'downloaded' : 'viewed',
            'ip_address' => '203.0.113.10',
        ]);
    }

    #[Test]
    public function o_admin_ve_a_auditoria(): void
    {
        $this->actingAs($this->admin())->get('/auditoria')->assertOk();
    }

    /** Nem RH nem ouvidoria enxergam a trilha — só supervisão. */
    #[Test]
    public function quem_opera_as_areas_nao_ve_a_auditoria(): void
    {
        foreach ([['hr'], ['ombudsman'], ['hr', 'ombudsman']] as $roles) {
            $this->actingAs(User::factory()->create(['roles' => $roles]))
                ->get('/auditoria')
                ->assertForbidden();
        }
    }

    #[Test]
    public function visitante_anonimo_e_mandado_para_o_login(): void
    {
        $this->get('/auditoria')->assertRedirect('/login');
    }

    #[Test]
    public function lista_os_acessos_com_operador_acao_e_origem(): void
    {
        Storage::fake('resumes');
        $operator = User::factory()->create(['roles' => ['hr'], 'name' => 'Maria RH']);

        $application = JobApplication::create([
            'name' => 'João Pereira', 'email' => 'joao@exemplo.com', 'phone' => '19996566767',
            'job_opening' => 'Soldador', 'resume_path' => '2026/09/A.pdf',
            'resume_original_name' => 'cv.pdf', 'resume_mime' => 'application/pdf',
            'resume_bytes' => 10,
        ]);
        $this->logEntry($operator, PortalAccessLog::RESOURCE_RESUME, $application->id);

        $this->actingAs($this->admin())
            ->get('/auditoria')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Audit/Index')
                ->has('logs.data', 1)
                ->where('logs.data.0.operator', 'Maria RH')
                ->where('logs.data.0.action', 'baixou currículo')
                ->where('logs.data.0.subject', 'João Pereira')
                ->where('logs.data.0.ip', '203.0.113.10')
            );
    }

    #[Test]
    public function filtra_por_tipo_de_recurso(): void
    {
        $operator = User::factory()->create(['roles' => ['hr']]);
        $report = WhistleblowerReport::create([
            'is_anonymous' => true, 'subject' => 'Descarte irregular',
            'description' => str_repeat('x', 45),
        ]);
        $this->logEntry($operator, PortalAccessLog::RESOURCE_RESUME, '01ABC');
        $this->logEntry($operator, PortalAccessLog::RESOURCE_REPORT, $report->id);

        $admin = $this->admin();

        $this->actingAs($admin)->get('/auditoria?tipo=report')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('logs.data', 1)
                ->where('logs.data.0.subject', 'Descarte irregular')
            );

        $this->actingAs($admin)->get('/auditoria?tipo=resume')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('logs.data', 1));
    }

    #[Test]
    public function filtra_por_operador(): void
    {
        $maria = User::factory()->create(['roles' => ['hr'], 'name' => 'Maria']);
        $paulo = User::factory()->create(['roles' => ['ombudsman'], 'name' => 'Paulo']);
        $this->logEntry($maria, PortalAccessLog::RESOURCE_RESUME, '01A');
        $this->logEntry($paulo, PortalAccessLog::RESOURCE_REPORT, '01B');

        $this->actingAs($this->admin())
            ->get("/auditoria?usuario={$paulo->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('logs.data', 1)
                ->where('logs.data.0.operator', 'Paulo')
            );
    }

    /**
     * A trilha sobrevive ao dado que registra: a candidatura pode ter sido
     * expurgada por retenção, e o registro continua provando QUE houve acesso —
     * mesmo sem poder dizer a quê.
     */
    #[Test]
    public function acesso_a_registro_ja_expurgado_continua_na_trilha(): void
    {
        $operator = User::factory()->create(['roles' => ['hr']]);
        $this->logEntry($operator, PortalAccessLog::RESOURCE_RESUME, '01JANAOEXISTE');

        $this->actingAs($this->admin())
            ->get('/auditoria')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('logs.data', 1)
                ->where('logs.data.0.subject', 'registro expurgado')
            );
    }

    /**
     * Somente leitura: trilha que a interface pode alterar não serve como
     * trilha, nem para quem administra.
     */
    #[Test]
    public function nao_existe_rota_para_alterar_ou_apagar_a_trilha(): void
    {
        $auditRoutes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'auditoria'));

        $this->assertGreaterThan(0, $auditRoutes->count());

        foreach ($auditRoutes as $route) {
            $methods = array_diff($route->methods(), ['HEAD']);
            $this->assertSame(['GET'], array_values($methods), "Rota {$route->uri()} aceita escrita.");
        }
    }

    /** O acesso do próprio administrador também é registrado. */
    #[Test]
    public function o_download_feito_pelo_admin_tambem_entra_na_trilha(): void
    {
        Storage::fake('resumes');
        Storage::disk('resumes')->put('2026/09/A.pdf', '%PDF-1.4');

        $application = JobApplication::create([
            'name' => 'João Pereira', 'email' => 'joao@exemplo.com', 'phone' => '19996566767',
            'job_opening' => 'Soldador', 'resume_path' => '2026/09/A.pdf',
            'resume_original_name' => 'cv.pdf', 'resume_mime' => 'application/pdf',
            'resume_bytes' => 8,
        ]);

        $admin = $this->admin();
        $this->actingAs($admin)->get("/candidaturas/{$application->id}/curriculo")->assertOk();

        $this->assertSame($admin->id, PortalAccessLog::sole()->user_id);
    }
}
