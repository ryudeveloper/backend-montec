<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\JobApplication;
use App\Models\User;
use App\Models\WhistleblowerReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Separação de deveres entre RH e ouvidoria.
 *
 * É o requisito central do portal, não uma conveniência: denúncia é muitas vezes
 * contra alguém da própria empresa, e quem cuida de recrutamento não tem por que
 * ler isso.
 *
 * A separação vale entre os papéis OPERACIONAIS. `admin` é o nível de
 * supervisão e abre as duas áreas — com a contrapartida de que o acesso dele
 * também vai para a trilha, e a trilha é somente leitura (ver AuditAccessTest).
 */
final class SeparationOfDutiesTest extends TestCase
{
    use RefreshDatabase;

    private function application(): JobApplication
    {
        Storage::disk('resumes')->put('2026/09/ABC.pdf', '%PDF-1.4');

        return JobApplication::create([
            'name' => 'João Pereira', 'email' => 'joao@exemplo.com', 'phone' => '19996566767',
            'job_opening' => 'Soldador', 'resume_path' => '2026/09/ABC.pdf',
            'resume_original_name' => 'cv.pdf', 'resume_mime' => 'application/pdf',
            'resume_bytes' => 8,
        ]);
    }

    private function report(): WhistleblowerReport
    {
        return WhistleblowerReport::create([
            'is_anonymous' => true,
            'subject' => 'Descarte irregular',
            'description' => str_repeat('detalhe ', 10),
        ]);
    }

    // ── quem é só do RH ─────────────────────────────────────────────────────

    #[Test]
    public function quem_tem_so_rh_ve_curriculos(): void
    {
        Storage::fake('resumes');
        $this->application();

        $this->actingAs(User::factory()->create(['roles' => ['hr']]))
            ->get('/rh/candidaturas')
            ->assertOk();
    }

    #[Test]
    public function quem_tem_so_rh_nao_ve_a_lista_de_denuncias(): void
    {
        $this->actingAs(User::factory()->create(['roles' => ['hr']]))
            ->get('/rh/ouvidoria')
            ->assertForbidden();
    }

    #[Test]
    public function quem_tem_so_rh_nao_abre_uma_denuncia_pelo_id(): void
    {
        $report = $this->report();

        $this->actingAs(User::factory()->create(['roles' => ['hr']]))
            ->get("/rh/ouvidoria/{$report->id}")
            ->assertForbidden();
    }

    // ── quem é só da ouvidoria ──────────────────────────────────────────────

    #[Test]
    public function quem_tem_so_ouvidoria_ve_denuncias(): void
    {
        $this->report();

        $this->actingAs(User::factory()->create(['roles' => ['ombudsman']]))
            ->get('/rh/ouvidoria')
            ->assertOk();
    }

    #[Test]
    public function quem_tem_so_ouvidoria_nao_ve_a_lista_de_candidaturas(): void
    {
        $this->actingAs(User::factory()->create(['roles' => ['ombudsman']]))
            ->get('/rh/candidaturas')
            ->assertForbidden();
    }

    /** O ponto mais sensível: currículo é dado pessoal de terceiro. */
    #[Test]
    public function quem_tem_so_ouvidoria_nao_baixa_curriculo(): void
    {
        Storage::fake('resumes');
        $application = $this->application();

        $this->actingAs(User::factory()->create(['roles' => ['ombudsman']]))
            ->get("/rh/candidaturas/{$application->id}/curriculo")
            ->assertForbidden();
    }

    // ── acumulação explícita ────────────────────────────────────────────────

    #[Test]
    public function quem_tem_os_dois_papeis_ve_as_duas_areas(): void
    {
        Storage::fake('resumes');
        $this->application();
        $this->report();
        $user = User::factory()->create(['roles' => ['hr', 'ombudsman']]);

        $this->actingAs($user)->get('/rh/candidaturas')->assertOk();
        $this->actingAs($user)->get('/rh/ouvidoria')->assertOk();
    }

    /**
     * MUDANÇA DE MODELO: `admin` passou a abrir as duas áreas operacionais.
     *
     * A separação que o portal garante é entre RH e ouvidoria — os papéis de
     * quem opera. A supervisão enxerga tudo, e paga por isso ficando registrada
     * na trilha como qualquer outro acesso.
     */
    #[Test]
    public function admin_ve_as_duas_areas_operacionais(): void
    {
        Storage::fake('resumes');
        $this->application();
        $this->report();
        $admin = User::factory()->create(['roles' => ['admin']]);

        $this->actingAs($admin)->get('/rh/candidaturas')->assertOk();
        $this->actingAs($admin)->get('/rh/ouvidoria')->assertOk();
    }

    /** E a trilha continua sendo exclusiva dele. */
    #[Test]
    public function so_o_admin_ve_a_trilha_de_auditoria(): void
    {
        $this->actingAs(User::factory()->create(['roles' => ['admin']]))
            ->get('/rh/auditoria')->assertOk();

        foreach ([['hr'], ['ombudsman'], ['hr', 'ombudsman']] as $roles) {
            $this->actingAs(User::factory()->create(['roles' => $roles]))
                ->get('/rh/auditoria')->assertForbidden();
        }
    }

    #[Test]
    public function conta_sem_papel_nao_ve_nada(): void
    {
        $user = User::factory()->create(['roles' => []]);

        $this->actingAs($user)->get('/rh/candidaturas')->assertForbidden();
        $this->actingAs($user)->get('/rh/ouvidoria')->assertForbidden();
        $this->actingAs($user)->get('/rh/auditoria')->assertForbidden();
    }

    /** Papel inventado na coluna não pode virar acesso. */
    #[Test]
    public function papel_desconhecido_e_descartado(): void
    {
        $user = User::factory()->create(['roles' => ['superusuario', 'root']]);

        $this->assertSame([], $user->roles());
        $this->actingAs($user)->get('/rh/candidaturas')->assertForbidden();
        $this->actingAs($user)->get('/rh/ouvidoria')->assertForbidden();
        $this->actingAs($user)->get('/rh/auditoria')->assertForbidden();
    }

    // ── navegação ───────────────────────────────────────────────────────────

    /**
     * O menu não deve oferecer o que a pessoa não pode abrir.
     *
     * O cabeçalho é desenhado a partir de `auth.can`, então é isso que se
     * verifica. Vale lembrar: estas flags são dica de interface, não a
     * autorização — quem nega é o EnsureRole, e os testes acima provam isso
     * batendo nas URLs diretas.
     */
    #[Test]
    public function as_permissoes_compartilhadas_refletem_apenas_as_areas_do_usuario(): void
    {
        Storage::fake('resumes');
        $this->application();
        $this->report();

        $this->actingAs(User::factory()->create(['roles' => ['hr']]))
            ->get('/rh/candidaturas')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.can.viewResumes', true)
                ->where('auth.can.viewReports', false)
                ->where('auth.can.viewAudit', false)
            );

        $this->actingAs(User::factory()->create(['roles' => ['ombudsman']]))
            ->get('/rh/ouvidoria')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.can.viewResumes', false)
                ->where('auth.can.viewReports', true)
                ->where('auth.can.viewAudit', false)
            );

        // Só a supervisão recebe a permissão de auditoria.
        $this->actingAs(User::factory()->create(['roles' => ['admin']]))
            ->get('/rh/auditoria')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.can.viewAudit', true)
            );
    }

    /**
     * O que é compartilhado com o cliente chega ao navegador e é legível no
     * DevTools. Papel não vai — o cliente não precisa saber que existe `admin`
     * para desenhar um menu.
     */
    #[Test]
    public function as_props_compartilhadas_nao_expoem_a_lista_de_papeis(): void
    {
        Storage::fake('resumes');
        $this->application();

        $this->actingAs(User::factory()->create(['roles' => ['hr', 'admin']]))
            ->get('/rh/candidaturas')
            ->assertInertia(function (AssertableInertia $page): void {
                $auth = $page->toArray()['props']['auth'];

                $this->assertArrayNotHasKey('roles', $auth['user']);
                $this->assertSame(
                    ['viewResumes', 'viewReports', 'viewAudit'],
                    array_keys($auth['can']),
                );
            });
    }
}
