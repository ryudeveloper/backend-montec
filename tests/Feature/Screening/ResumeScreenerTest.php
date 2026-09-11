<?php

declare(strict_types=1);

namespace Tests\Feature\Screening;

use App\Domain\Screening\AnthropicScreener;
use App\Domain\Screening\ScreeningUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cliente da API que produz o parecer.
 *
 * Duas preocupações separadas: que o parecer chegue em formato utilizável, e que
 * nada que identifique o candidato saia daqui para um terceiro.
 */
final class ResumeScreenerTest extends TestCase
{
    private const CURRICULO = 'Soldador com 8 anos de experiencia em MIG e MAG. Certificacao NR-34 vigente. Atuou com chapa de 6mm.';

    private function enable(): void
    {
        config()->set('montec.ai.key', 'chave-de-teste');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function fakeAssessment(array $overrides = []): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'registrar_parecer',
                    'input' => [
                        'score' => 78,
                        'strengths' => ['8 anos em solda MIG/MAG', 'NR-34 vigente'],
                        'gaps' => ['Sem mencao a solda TIG'],
                        'skills' => ['MIG', 'MAG', 'NR-34'],
                        ...$overrides,
                    ],
                ]],
            ]),
        ]);
    }

    #[Test]
    public function desligado_sem_chave_configurada(): void
    {
        Http::preventStrayRequests();

        $this->assertFalse(app(AnthropicScreener::class)->isEnabled());
    }

    #[Test]
    public function ligado_quando_ha_chave(): void
    {
        $this->enable();

        $this->assertTrue(app(AnthropicScreener::class)->isEnabled());
    }

    #[Test]
    public function devolve_o_parecer_estruturado(): void
    {
        $this->enable();
        $this->fakeAssessment();

        $parecer = app(AnthropicScreener::class)->assess(self::CURRICULO, 'Montador/Soldador');

        $this->assertSame(78, $parecer->score);
        $this->assertSame(['8 anos em solda MIG/MAG', 'NR-34 vigente'], $parecer->strengths);
        $this->assertSame(['Sem mencao a solda TIG'], $parecer->gaps);
        $this->assertSame(['MIG', 'MAG', 'NR-34'], $parecer->skills);
        $this->assertSame(config('montec.ai.model'), $parecer->model);
    }

    /**
     * A chave é credencial: vai no cabeçalho, nunca no corpo nem na URL — onde
     * acabaria em log de proxy e em histórico.
     */
    #[Test]
    public function envia_a_chave_no_cabecalho_e_nao_no_corpo(): void
    {
        $this->enable();
        $this->fakeAssessment();

        app(AnthropicScreener::class)->assess(self::CURRICULO, 'Montador/Soldador');

        Http::assertSent(function (ClientRequest $request): bool {
            return $request->header('x-api-key') === ['chave-de-teste']
                && $request->header('anthropic-version') !== []
                && ! str_contains($request->body(), 'chave-de-teste');
        });
    }

    /** O nome da vaga precisa chegar ao modelo, senão não há o que comparar. */
    #[Test]
    public function envia_a_vaga_junto_do_curriculo(): void
    {
        $this->enable();
        $this->fakeAssessment();

        app(AnthropicScreener::class)->assess(self::CURRICULO, 'Operador de Torno CNC');

        Http::assertSent(fn (ClientRequest $r) => str_contains($r->body(), 'Operador de Torno CNC')
            && str_contains($r->body(), 'Soldador com 8 anos'));
    }

    /** Nota fora da faixa é resposta inválida, não algo para exibir. */
    #[Test]
    public function recusa_nota_fora_da_faixa(): void
    {
        $this->enable();
        $this->fakeAssessment(['score' => 140]);

        $this->expectException(ScreeningUnavailableException::class);

        app(AnthropicScreener::class)->assess(self::CURRICULO, 'Montador/Soldador');
    }

    #[Test]
    public function recusa_resposta_sem_o_formato_esperado(): void
    {
        $this->enable();
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Achei o curriculo bom.']],
            ]),
        ]);

        $this->expectException(ScreeningUnavailableException::class);

        app(AnthropicScreener::class)->assess(self::CURRICULO, 'Montador/Soldador');
    }

    /** Falha de rede vira mensagem legível, não 500 na cara do RH. */
    #[Test]
    public function falha_de_rede_vira_erro_tratado(): void
    {
        $this->enable();
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->expectException(ScreeningUnavailableException::class);

        app(AnthropicScreener::class)->assess(self::CURRICULO, 'Montador/Soldador');
    }

    #[Test]
    public function erro_da_api_vira_erro_tratado(): void
    {
        $this->enable();
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'overloaded']], 529)]);

        $this->expectException(ScreeningUnavailableException::class);

        app(AnthropicScreener::class)->assess(self::CURRICULO, 'Montador/Soldador');
    }

    /**
     * A instrução precisa dizer ao modelo que o texto foi redigido e que ele não
     * deve especular sobre quem é a pessoa. Sem isso, o modelo tende a preencher
     * a lacuna — e inventar identidade é pior do que não tê-la.
     */
    #[Test]
    public function instrui_o_modelo_a_nao_especular_sobre_identidade(): void
    {
        $this->enable();
        $this->fakeAssessment();

        app(AnthropicScreener::class)->assess(self::CURRICULO, 'Montador/Soldador');

        Http::assertSent(function (ClientRequest $request): bool {
            $body = mb_strtolower($request->body());

            return str_contains($body, 'removid') && str_contains($body, 'especul');
        });
    }
}
