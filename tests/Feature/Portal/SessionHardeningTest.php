<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SessionHardeningTest extends TestCase
{
    use RefreshDatabase;

    /** Cookie legível por JavaScript é roubo de sessão via XSS. */
    #[Test]
    public function o_cookie_de_sessao_e_http_only(): void
    {
        $this->assertTrue((bool) config('session.http_only'));
    }

    /**
     * `strict` e não `lax`: o portal não recebe navegação legítima de fora, e
     * strict impede o cookie de acompanhar requisição originada em outro site —
     * proteção contra CSRF que não depende só do token.
     */
    #[Test]
    public function o_cookie_de_sessao_e_same_site_strict(): void
    {
        $this->assertSame('strict', config('session.same_site'));
    }

    /** Sessão regenerada no login fecha a janela de session fixation. */
    #[Test]
    public function o_login_regenera_o_id_de_sessao(): void
    {
        $user = User::factory()->create(['roles' => ['hr'], 'password' => 'senha-de-teste-123']);

        $this->get('/login');
        $before = session()->getId();

        $this->post('/login', ['email' => $user->email, 'password' => 'senha-de-teste-123']);

        $this->assertNotSame($before, session()->getId());
    }

    /**
     * Toda rota POST do portal passa pelo grupo `web`, que traz a proteção CSRF
     * (em Laravel 13 a classe é PreventRequestForgery, não ValidateCsrfToken).
     *
     * A verificação é indireta de propósito: o Laravel desliga a checagem de
     * CSRF ao detectar que está rodando testes, então um POST sem token passaria
     * aqui e o teste não provaria nada. O que se pode afirmar é que a rota está
     * no grupo que aplica a proteção, e que o grupo continua a contendo.
     */
    #[Test]
    public function as_rotas_post_do_portal_estao_no_grupo_que_aplica_csrf(): void
    {
        $webGroup = app(Kernel::class)->getMiddlewareGroups()['web'] ?? [];
        $this->assertContains(PreventRequestForgery::class, $webGroup, 'O grupo web perdeu a proteção CSRF.');

        // Selecionado pelo namespace do controller, não pelo caminho nem pelo
        // nome da rota: as telas perderam o prefixo `rh`, e o POST de login não
        // tem nome — um filtro por nome deixaria de fora justamente a rota que
        // mais precisa de CSRF.
        $postRoutes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with(
                (string) $route->getAction('controller'),
                'App\\Http\\Controllers\\Portal\\',
            ) && in_array('POST', $route->methods(), true));

        $this->assertGreaterThan(0, $postRoutes->count());

        foreach ($postRoutes as $route) {
            $this->assertContains('web', $route->gatherMiddleware(), "Rota {$route->uri()} fora do grupo web.");
        }
    }

    /** O portal nunca deve ser indexado. */
    #[Test]
    public function as_paginas_do_portal_pedem_noindex(): void
    {
        $response = $this->actingAs(User::factory()->create(['roles' => ['hr']]))->get('/candidaturas');

        $response->assertOk();
        $this->assertStringContainsString('noindex', $response->getContent() ?: '');
    }
}
