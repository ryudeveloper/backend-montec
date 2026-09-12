<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Http\Middleware\EnsureAreaAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Controle de acesso ao portal do RH.
 *
 * O portal é a única porta pela qual um currículo — guardado fora do webroot de
 * propósito — se torna alcançável. Se esta camada falhar, todo o cuidado com
 * disco privado e nome gerado não vale nada.
 */
final class PortalAccessTest extends TestCase
{
    use RefreshDatabase;

    private function hr(): User
    {
        return User::factory()->create(['roles' => ['hr']]);
    }

    #[Test]
    public function visitante_anonimo_e_mandado_para_o_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/candidaturas')->assertRedirect('/login');
    }

    /** Conta sem papel não enxerga nada — ausência de papel é a negação. */
    #[Test]
    public function usuario_sem_papel_recebe_403(): void
    {
        $this->actingAs(User::factory()->create(['roles' => []]))
            ->get('/candidaturas')
            ->assertForbidden();
    }

    #[Test]
    public function usuario_do_rh_acessa_a_listagem(): void
    {
        $this->actingAs($this->hr())->get('/candidaturas')->assertOk();
    }

    /**
     * `admin` é o nível de supervisão: abre as duas áreas operacionais.
     *
     * A separação que segue valendo é entre RH e ouvidoria — ver
     * SeparationOfDutiesTest. A contrapartida da supervisão é que o acesso do
     * próprio administrador também vai para a trilha de auditoria.
     */
    #[Test]
    public function admin_acessa_as_duas_areas_operacionais(): void
    {
        $admin = User::factory()->create(['roles' => ['admin']]);

        $this->actingAs($admin)->get('/candidaturas')->assertOk();
        $this->actingAs($admin)->get('/ouvidoria')->assertOk();
    }

    /**
     * Cada área do portal fica atrás da SUA permissão.
     *
     * A ouvidoria agora EXISTE no portal — antes este teste afirmava sua
     * ausência. A garantia mudou de lugar, não de força: o que não pode
     * acontecer é uma rota de conteúdo sem o middleware de área, porque aí a
     * separação viveria só no menu, o que é o mesmo que não existir.
     */
    #[Test]
    public function toda_rota_de_conteudo_exige_o_papel_da_sua_area(): void
    {
        $expected = [
            'candidaturas' => 'resumes',
            'candidaturas/{application}' => 'resumes',
            'candidaturas/{application}/curriculo' => 'resumes',
            'candidaturas/{application}/parecer' => 'resumes',
            'ouvidoria' => 'reports',
            'ouvidoria/{report}' => 'reports',
            'auditoria' => 'audit',
        ];

        // Selecionado pelo namespace do controller, não pelo caminho: as telas
        // do portal perderam o prefixo `rh` e um filtro por URI passaria a não
        // encontrar rota nenhuma — deixando o teste verde sem verificar nada.
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with(
                (string) $route->getAction('controller'),
                'App\\Http\\Controllers\\Portal\\',
            ))
            ->keyBy(fn ($route) => $route->uri());

        foreach ($expected as $uri => $area) {
            $this->assertTrue($routes->has($uri), "Rota {$uri} não existe.");

            $middleware = implode(' ', $routes->get($uri)->gatherMiddleware());
            $this->assertStringContainsString(
                EnsureAreaAccess::class.':'.$area,
                $middleware,
                "Rota {$uri} não exige acesso à área {$area}.",
            );
        }
    }

    /**
     * Separação estrutural, não só de rota: o controller de uma área ignora o
     * model da outra. Fica difícil vazar de um lado para o outro por descuido
     * quando o código de cada área desconhece a existência da outra.
     */
    #[Test]
    public function o_controller_de_cada_area_ignora_o_model_da_outra(): void
    {
        $hr = (string) file_get_contents(
            app_path('Http/Controllers/Portal/JobApplicationPortalController.php')
        );
        $this->assertStringNotContainsString('WhistleblowerReport', $hr);

        $ombudsman = (string) file_get_contents(
            app_path('Http/Controllers/Portal/WhistleblowerPortalController.php')
        );
        $this->assertStringNotContainsString('JobApplication', $ombudsman);
    }

    /**
     * Regressão: quem é só da ouvidoria caía em /candidaturas depois do login
     * e levava 403 na cara — parecia defeito, não regra. O login encaminha para
     * o dispatcher, que manda cada pessoa para a área que ela pode abrir.
     */
    #[Test]
    public function o_login_encaminha_cada_papel_para_a_sua_area(): void
    {
        $hr = User::factory()->create(['roles' => ['hr'], 'password' => 'senha-de-teste-123']);
        $this->post('/login', ['email' => $hr->email, 'password' => 'senha-de-teste-123'])
            ->assertRedirect('/');
        $this->get('/')->assertRedirect(route('portal.applications.index'));
        $this->post('/logout');

        $ombudsman = User::factory()->create(['roles' => ['ombudsman'], 'password' => 'senha-de-teste-123']);
        $this->post('/login', ['email' => $ombudsman->email, 'password' => 'senha-de-teste-123'])
            ->assertRedirect('/');
        $this->get('/')->assertRedirect(route('portal.reports.index'));
        $this->post('/logout');

        /*
         | Conta SEM papel algum recebe 200 com explicação e botão de sair, não
         | 403. Um 403 aqui prendia a pessoa: a tela de erro não tinha layout,
         | logo não tinha Sair, e /login devolve quem já está autenticado para
         | cá — que negava de novo. Ver NoAccessEscapeTest.
         */
        $semPapel = User::factory()->create(['roles' => [], 'password' => 'senha-de-teste-123']);
        $this->post('/login', ['email' => $semPapel->email, 'password' => 'senha-de-teste-123']);
        $this->get('/')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('NoAccess'));
    }

    #[Test]
    public function o_login_registra_o_ultimo_acesso(): void
    {
        $user = User::factory()->create(['roles' => ['hr'], 'password' => 'senha-de-teste-123']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'senha-de-teste-123',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    #[Test]
    public function senha_errada_nao_autentica(): void
    {
        $user = User::factory()->create(['roles' => ['hr'], 'password' => 'senha-de-teste-123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'errada'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /** Força bruta é o ataque óbvio contra uma tela de login. */
    #[Test]
    public function o_login_e_limitado_por_tentativas(): void
    {
        $user = User::factory()->create(['roles' => ['hr'], 'password' => 'senha-de-teste-123']);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'errada']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'errada'])
            ->assertStatus(429);
    }

    #[Test]
    public function o_logout_encerra_a_sessao(): void
    {
        $this->actingAs($this->hr())->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }

    /** Sem cadastro público: contas nascem por comando, não por formulário. */
    #[Test]
    public function nao_existe_rota_de_registro(): void
    {
        $this->get('/registrar')->assertNotFound();
        $this->post('/registrar', [])->assertNotFound();
        $this->get('/register')->assertNotFound();
    }
}
