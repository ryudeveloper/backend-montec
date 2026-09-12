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
 * Ela ficava sem rota, devolvendo 404, e quem chegava pelo endereço do portal
 * concluía que o sistema estava fora do ar. O caminho certo — /rh/login — só era
 * conhecido por quem já tinha o link completo.
 *
 * A raiz não decide nada por conta própria: aponta para /rh e deixa as regras
 * que já existem responderem. Visitante cai no login pelo `redirectGuestsTo`;
 * quem está autenticado é encaminhado pelo PortalHomeController para a área que
 * de fato pode abrir.
 */
final class RootEntryPointTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function visitante_na_raiz_termina_no_login(): void
    {
        $this->get('/')
            ->assertRedirect('/rh')
            ->assertSessionMissing('errors');

        // Seguindo o encaminhamento: é o `auth` que leva ao login, não a raiz.
        // A asserção é sobre a página do Inertia, não sobre o HTML — a tela vive
        // no componente React e o servidor entrega só o contêiner e os props.
        $this->followingRedirects()
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Login'));
    }

    #[Test]
    public function quem_cuida_de_curriculos_cai_em_candidaturas(): void
    {
        $user = User::factory()->create(['roles' => ['hr']]);

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect('/rh');

        $this->actingAs($user)
            ->get('/rh')
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
        $user = User::factory()->create(['roles' => ['ombudsman']]);

        $this->actingAs($user)
            ->get('/rh')
            ->assertRedirect(route('portal.reports.index'));
    }

    /**
     * A raiz encaminha, não autoriza. Se algum dia ela passar a responder
     * conteúdo direto, esta asserção quebra — e é isso que se quer.
     */
    #[Test]
    public function a_raiz_nao_entrega_conteudo_por_conta_propria(): void
    {
        $this->get('/')->assertRedirect();
    }
}
