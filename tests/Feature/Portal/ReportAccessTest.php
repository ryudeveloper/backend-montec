<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\PortalAccessLog;
use App\Models\User;
use App\Models\WhistleblowerReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReportAccessTest extends TestCase
{
    use RefreshDatabase;

    private function ombudsman(): User
    {
        return User::factory()->create(['roles' => ['ombudsman']]);
    }

    private function anonymousReport(): WhistleblowerReport
    {
        return WhistleblowerReport::create([
            'is_anonymous' => true,
            'subject' => 'Descarte irregular de solvente',
            'description' => 'No dia 12/08, no setor de pintura, presenciei descarte na rede pluvial.',
        ]);
    }

    private function identifiedReport(): WhistleblowerReport
    {
        return WhistleblowerReport::create([
            'is_anonymous' => false,
            'name' => 'Carlos Lima',
            'email' => 'carlos@exemplo.com',
            'subject' => 'Assédio na expedição',
            'description' => 'Relato detalhado do ocorrido na expedição durante o turno da noite.',
        ]);
    }

    #[Test]
    public function visitante_anonimo_e_mandado_para_o_login(): void
    {
        $this->get('/ouvidoria')->assertRedirect('/login');
    }

    #[Test]
    public function a_ouvidoria_lista_denuncias(): void
    {
        $this->anonymousReport();
        $this->identifiedReport();

        $this->actingAs($this->ombudsman())
            ->get('/ouvidoria')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Reports/Index')
                ->has('reports.data', 2)
                ->where('counts.anonymous', 1)
                ->where('counts.identified', 1)
            );
    }

    /**
     * A listagem não manda NENHUM trecho do relato.
     *
     * Havia uma prévia de 120 caracteres, e ela furava a auditoria: numa denúncia
     * curta a prévia era o relato inteiro, então bastava abrir a lista para ler
     * tudo sem deixar registro. Se o motivo de auditar é saber quem leu o quê,
     * não pode existir caminho que entregue o conteúdo sem passar pelo registro.
     */
    #[Test]
    public function a_listagem_nao_manda_nenhum_trecho_do_relato(): void
    {
        $report = $this->anonymousReport();

        $this->actingAs($this->ombudsman())
            ->get('/ouvidoria')
            ->assertInertia(function (AssertableInertia $page) use ($report): void {
                $listed = $page->toArray()['props']['reports']['data'][0];

                $this->assertArrayNotHasKey('description', $listed);
                $this->assertArrayNotHasKey('excerpt', $listed);

                // Nem um pedaço: os 30 primeiros caracteres não podem aparecer.
                $serialized = json_encode($listed) ?: '';
                $this->assertStringNotContainsString(
                    substr($report->description, 0, 30),
                    $serialized,
                );
            });

        // E a listagem não gera registro de auditoria, porque não entrega conteúdo.
        $this->assertSame(0, PortalAccessLog::count());
    }

    #[Test]
    public function filtra_por_anonima_e_identificada(): void
    {
        $this->anonymousReport();
        $this->identifiedReport();
        $user = $this->ombudsman();

        $this->actingAs($user)->get('/ouvidoria?identificacao=anonima')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('reports.data', 1)
                ->where('reports.data.0.isAnonymous', true)
                ->where('filters.mode', 'anonima')
            );

        $this->actingAs($user)->get('/ouvidoria?identificacao=identificada')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('reports.data', 1)
                ->where('reports.data.0.isAnonymous', false)
            );
    }

    /**
     * Ler uma denúncia é no mínimo tão sensível quanto abrir um currículo — e
     * aqui o conteúdo é lido na própria tela, não num download. Por isso a
     * auditoria acontece na abertura.
     */
    #[Test]
    public function abrir_uma_denuncia_deixa_registro_de_auditoria(): void
    {
        $report = $this->anonymousReport();
        $user = $this->ombudsman();

        $this->actingAs($user)->get("/ouvidoria/{$report->id}")->assertOk();

        $log = PortalAccessLog::sole();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame(PortalAccessLog::RESOURCE_REPORT, $log->resource_type);
        $this->assertSame($report->id, $log->resource_id);
        $this->assertSame('viewed', $log->action);
    }

    /** Registrar quem LEU não pode virar registro de quem ESCREVEU. */
    #[Test]
    public function a_auditoria_nao_guarda_nada_do_denunciante(): void
    {
        $report = $this->identifiedReport();

        $this->actingAs($this->ombudsman())->get("/ouvidoria/{$report->id}")->assertOk();

        $attributes = PortalAccessLog::sole()->getAttributes();
        $serialized = json_encode($attributes) ?: '';

        $this->assertStringNotContainsString('Carlos Lima', $serialized);
        $this->assertStringNotContainsString('carlos@exemplo.com', $serialized);
    }

    #[Test]
    public function acesso_negado_nao_gera_registro(): void
    {
        $report = $this->anonymousReport();

        $this->actingAs(User::factory()->create(['roles' => ['hr']]))
            ->get("/ouvidoria/{$report->id}")
            ->assertForbidden();

        $this->assertSame(0, PortalAccessLog::count());
    }

    /**
     * Na denúncia anônima não há identificação para a tela esconder — ela nunca
     * foi gravada. A tela diz isso explicitamente para o operador não ficar
     * procurando o dado nem supor que ele existe em outro lugar.
     */
    /**
     * Em denúncia anônima nome e e-mail chegam ao cliente como null — nunca
     * foram gravados. Não é a tela que esconde: o dado não existe.
     */
    #[Test]
    public function a_denuncia_anonima_nao_manda_identificacao_ao_cliente(): void
    {
        $report = $this->anonymousReport();

        $this->actingAs($this->ombudsman())
            ->get("/ouvidoria/{$report->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Reports/Show')
                ->where('report.isAnonymous', true)
                ->where('report.name', null)
                ->where('report.email', null)
            );
    }

    #[Test]
    public function a_denuncia_identificada_manda_o_contato(): void
    {
        $report = $this->identifiedReport();

        $this->actingAs($this->ombudsman())
            ->get("/ouvidoria/{$report->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('report.isAnonymous', false)
                ->where('report.name', 'Carlos Lima')
                ->where('report.email', 'carlos@exemplo.com')
            );
    }

    #[Test]
    public function denuncia_inexistente_responde_404(): void
    {
        $this->actingAs($this->ombudsman())
            ->get('/ouvidoria/01ABCDEFGHIJKLMNOPQRSTUVWX')
            ->assertNotFound();
    }
}
