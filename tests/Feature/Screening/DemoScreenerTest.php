<?php

declare(strict_types=1);

namespace Tests\Feature\Screening;

use App\Domain\Screening\DemoScreener;
use App\Domain\Screening\Screener;
use App\Domain\Screening\UnsafeScreeningDriverException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Modo de demonstração: exercita o fluxo sem chave de API.
 *
 * Existe para dar para testar a tela e a trilha de auditoria antes de contratar
 * o serviço. Não é análise de verdade, e por isso duas garantias importam mais
 * que o resto: ele se identifica como demonstração, e NÃO SOBE EM PRODUÇÃO —
 * parecer falso num sistema real é alguém tomando decisão sobre um candidato com
 * base em nada.
 */
final class DemoScreenerTest extends TestCase
{
    private const CURRICULO = 'Soldador com 8 anos em MIG e MAG. Certificacao NR-34. Leitura de desenho tecnico e uso de calandra.';

    #[Test]
    public function nao_chama_api_nenhuma(): void
    {
        Http::preventStrayRequests();

        $parecer = (new DemoScreener)->assess(self::CURRICULO, 'Montador/Soldador');

        $this->assertGreaterThanOrEqual(0, $parecer->score);
        $this->assertLessThanOrEqual(100, $parecer->score);
    }

    /** Quem olhar a tela precisa saber que não é análise real. */
    #[Test]
    public function se_identifica_como_demonstracao(): void
    {
        $parecer = (new DemoScreener)->assess(self::CURRICULO, 'Montador/Soldador');

        $this->assertStringContainsStringIgnoringCase('demonstra', $parecer->model);
    }

    /**
     * O parecer sai do texto, não de números aleatórios: um currículo de
     * soldador numa vaga de soldador tem de pontuar mais que numa de pintor,
     * senão a demonstração não demonstra nada.
     */
    #[Test]
    public function o_resultado_reflete_o_texto_e_a_vaga(): void
    {
        $screener = new DemoScreener;

        $aderente = $screener->assess(self::CURRICULO, 'Montador/Soldador');
        $distante = $screener->assess(self::CURRICULO, 'Analista Financeiro');

        $this->assertGreaterThan($distante->score, $aderente->score);
        $this->assertContains('MIG', $aderente->skills);
    }

    #[Test]
    public function e_deterministico_para_o_mesmo_par(): void
    {
        $screener = new DemoScreener;

        $this->assertSame(
            $screener->assess(self::CURRICULO, 'Montador/Soldador')->score,
            $screener->assess(self::CURRICULO, 'Montador/Soldador')->score,
        );
    }

    #[Test]
    public function sempre_disponivel(): void
    {
        $this->assertTrue((new DemoScreener)->isEnabled());
    }

    /**
     * Em produção o modo de demonstração não pode existir.
     *
     * Um parecer inventado num sistema real vira decisão sobre a vida de um
     * candidato tomada com base em nada. Falhar no boot é o único jeito de isso
     * não passar despercebido.
     */
    #[Test]
    public function recusa_subir_em_producao(): void
    {
        $this->expectException(UnsafeScreeningDriverException::class);

        DemoScreener::assertSafeEnvironment('production');
    }

    #[Test]
    public function fora_de_producao_nao_interfere(): void
    {
        DemoScreener::assertSafeEnvironment('local');
        DemoScreener::assertSafeEnvironment('testing');

        $this->assertTrue(true);
    }

    /** O container precisa entregar o driver escolhido na configuração. */
    #[Test]
    public function o_container_resolve_o_driver_configurado(): void
    {
        config()->set('montec.ai.driver', 'demo');
        $this->assertInstanceOf(DemoScreener::class, app(Screener::class));

        config()->set('montec.ai.driver', 'anthropic');
        $this->assertNotInstanceOf(DemoScreener::class, app(Screener::class));
    }
}
