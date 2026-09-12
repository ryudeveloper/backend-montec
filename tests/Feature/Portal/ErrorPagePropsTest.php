<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A tela de erro precisa das mesmas props compartilhadas das demais.
 *
 * Regressão de tela preta: `Inertia::render()` chamado dentro do tratador de
 * exceções NÃO passa pelo middleware do Inertia, então a página chegava sem
 * `auth` — e o componente, que lê `auth.user` para decidir o que oferecer,
 * quebrava em "Cannot read properties of undefined". Resultado: tela preta, sem
 * mensagem, sem caminho de volta.
 *
 * Acontecia em qualquer 404, 419 ou 500. A raiz `/` (que não tem rota) era o
 * caminho mais fácil de chegar lá.
 */
final class ErrorPagePropsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function o_404_traz_as_props_compartilhadas(): void
    {
        $this->get('/nao-existe-em-lugar-nenhum')
            ->assertNotFound()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Error')
                ->where('status', 404)
                ->has('auth')
                ->where('auth.user', null)
                ->where('auth.can.viewResumes', false)
                ->where('auth.can.viewReports', false)
                ->where('auth.can.viewAudit', false)
            );
    }

    #[Test]
    public function o_403_de_area_proibida_traz_as_props_do_usuario(): void
    {
        $this->actingAs(User::factory()->create(['roles' => ['hr']]))
            ->get('/auditoria')
            ->assertForbidden()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Error')
                ->has('auth.user')
                ->where('auth.can.viewResumes', true)
                ->where('auth.can.viewAudit', false)
            );
    }

    #[Test]
    public function o_404_de_rota_inexistente_dentro_do_portal_tambem(): void
    {
        $this->actingAs(User::factory()->create(['roles' => ['admin']]))
            ->get('/pagina-que-nao-existe')
            ->assertNotFound()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Error')
                ->where('auth.can.viewAudit', true)
            );
    }

    /**
     * O 404 não pode carregar mensagem do framework.
     *
     * Roteamento devolve "The route / could not be found"; falha de model
     * binding devolve "No query results for model [App\Models\X]", que entrega
     * o nome da classe. Nenhum dos dois tem por que chegar ao navegador.
     */
    #[Test]
    public function o_404_nao_expoe_detalhe_de_implementacao(): void
    {
        $esperada = 'O endereço acessado não existe ou o registro não está mais disponível.';

        $this->get('/nao-existe-em-lugar-nenhum')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('message', $esperada));

        // Model binding que não encontra o registro vazaria a classe do model.
        $this->actingAs(User::factory()->create(['roles' => ['hr']]))
            ->get('/candidaturas/01ABCDEFGHIJKLMNOPQRSTUVWX')
            ->assertNotFound()
            ->assertInertia(function (AssertableInertia $page) use ($esperada): void {
                $message = $page->toArray()['props']['message'];

                $this->assertSame($esperada, $message);
                $this->assertStringNotContainsString('App\\Models', $message);
            });
    }
}
