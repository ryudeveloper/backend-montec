<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Quem entra sem área liberada precisa conseguir sair.
 *
 * Sem isto a pessoa fica presa: a tela de erro não tem o cabeçalho do portal,
 * logo não tem botão Sair, e `/login` está atrás do middleware `guest`, que
 * devolve quem já está autenticado para a raiz — que negava outra vez. A única
 * saída era apagar o cookie na mão.
 */
final class NoAccessEscapeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Conta sem NENHUM papel.
     *
     * Era `admin` até a supervisão passar a abrir todas as áreas. Agora quem
     * fica sem área é apenas quem não recebeu papel algum — que continua sendo
     * o padrão de toda conta nova.
     */
    private function withoutArea(): User
    {
        return User::factory()->create(['roles' => []]);
    }

    #[Test]
    public function conta_sem_area_cai_na_tela_de_sem_acesso(): void
    {
        $user = $this->withoutArea();

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('NoAccess')
                // A tela usa isto para dizer quem está conectado e oferecer o Sair.
                ->where('auth.user.email', $user->email)
                ->where('auth.can.viewResumes', false)
                ->where('auth.can.viewReports', false)
            );
    }

    #[Test]
    public function o_logout_funciona_a_partir_dessa_tela(): void
    {
        $this->actingAs($this->withoutArea());

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    /**
     * Mesmo problema na outra direção: quem tem um papel e bate numa área que
     * não é a sua recebe 403 — e a tela de erro tem de renderizar dentro do
     * portal, com o cabeçalho, para a pessoa voltar para onde pode ir.
     */
    #[Test]
    public function a_pagina_403_renderiza_dentro_do_portal(): void
    {
        $this->actingAs(User::factory()->create(['roles' => ['hr']]))
            ->get('/ouvidoria')
            ->assertForbidden()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Error')
                ->where('status', 403)
                // Oferece de volta só a área que esta pessoa pode abrir.
                ->where('auth.can.viewResumes', true)
                ->where('auth.can.viewReports', false)
            );
    }

    /** A tela de erro não deve revelar a área que a pessoa não pode abrir. */
    #[Test]
    public function a_pagina_403_nao_vaza_a_area_proibida(): void
    {
        $this->actingAs(User::factory()->create(['roles' => ['ombudsman']]))
            ->get('/candidaturas')
            ->assertForbidden()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Error')
                ->where('auth.can.viewResumes', false)
                ->where('auth.can.viewReports', true)
            );
    }

    /**
     * Mensagem de 5xx não pode chegar ao navegador.
     *
     * Ela carrega caminho de arquivo, consulta SQL e nome de classe. Provoca um
     * 500 de verdade em vez de inspecionar o código-fonte: teste que lê o
     * arquivo passa a acusar qualquer reformatação e não prova comportamento
     * nenhum.
     */
    #[Test]
    public function erro_de_servidor_nao_expoe_detalhe_interno(): void
    {
        Route::middleware('web')->get('/explode-de-proposito', function (): never {
            throw new RuntimeException('SELECT * FROM users WHERE senha_secreta = 42');
        });

        $response = $this->actingAs(User::factory()->create(['roles' => ['hr']]))
            ->get('/explode-de-proposito');

        $response->assertStatus(500)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Error')
                ->where('status', 500)
                ->where('message', 'Algo deu errado do nosso lado. Tente novamente em instantes.')
            );

        $this->assertStringNotContainsString('senha_secreta', $response->getContent() ?: '');
    }
}
