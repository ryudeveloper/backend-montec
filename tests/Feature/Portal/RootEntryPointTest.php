<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A raiz do subdomínio é o que as pessoas digitam e o que colam no chat interno.
 *
 * Ela é a porta do portal inteiro — não de uma área. O endereço não carrega mais
 * o prefixo `rh`: o portal deixou de ser só do RH quando a ouvidoria e a
 * auditoria entraram, e uma pessoa da ouvidoria entrando por um caminho chamado
 * `/rh` lia isso como estar no lugar errado.
 *
 * A raiz não decide nada por conta própria: quem responde são as regras que já
 * existem. Visitante cai no login pelo `redirectGuestsTo`; quem está autenticado
 * é encaminhado pelo PortalHomeController para a área que de fato pode abrir.
 */
final class RootEntryPointTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function visitante_na_raiz_termina_no_login(): void
    {
        $this->get('/')->assertRedirect('/login');

        $this->followingRedirects()
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Login'));
    }

    #[Test]
    public function quem_cuida_de_curriculos_cai_em_candidaturas(): void
    {
        $this->actingAs(User::factory()->create(['roles' => ['hr']]))
            ->get('/')
            ->assertRedirect(route('portal.applications.index'));
    }

    /**
     * O caso que mais importa da separação de áreas: quem é só da ouvidoria não
     * pode ser jogado em candidaturas e receber 403 na cara ao entrar pela raiz
     * — pareceria defeito, e não regra.
     */
    #[Test]
    public function quem_cuida_da_ouvidoria_cai_em_ouvidoria(): void
    {
        $this->actingAs(User::factory()->create(['roles' => ['ombudsman']]))
            ->get('/')
            ->assertRedirect(route('portal.reports.index'));
    }

    #[Test]
    public function o_administrador_cai_em_candidaturas(): void
    {
        $this->actingAs(User::factory()->create(['roles' => ['admin']]))
            ->get('/')
            ->assertRedirect(route('portal.applications.index'));
    }

    /**
     * O prefixo antigo não pode continuar respondendo: dois endereços servindo a
     * mesma tela deixam links internos e favoritos divergirem sem ninguém notar.
     */
    #[Test]
    public function o_prefixo_antigo_nao_responde_mais(): void
    {
        $this->actingAs(User::factory()->create(['roles' => ['admin']]))
            ->get('/rh/candidaturas')
            ->assertNotFound();

        $this->get('/rh/login')->assertNotFound();
    }

    /**
     * As rotas dos formulários públicos vivem sob /api e não podem colidir com
     * as telas do portal — `/ouvidoria` (tela) e `/api/ouvidoria` (endpoint)
     * são coisas diferentes e precisam continuar sendo.
     */
    #[Test]
    public function as_telas_nao_colidem_com_os_endpoints_publicos(): void
    {
        $this->actingAs(User::factory()->create(['roles' => ['ombudsman']]))
            ->get('/ouvidoria')
            ->assertOk();

        // O endpoint público não aceita GET — se aceitasse, a tela teria sido
        // sobrescrita pela rota de API.
        $this->get('/api/ouvidoria')->assertMethodNotAllowed();
    }
}
