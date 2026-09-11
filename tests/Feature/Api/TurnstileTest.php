<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cloudflare Turnstile — camada anti-bot pedida pela §8 do CLAUDE.md.
 *
 * Ligado por configuração: sem `MONTEC_TURNSTILE_SECRET` o serviço nem chama a
 * Cloudflare. Isso evita o pior cenário de um captcha, que é ficar meio
 * configurado e recusar gente de verdade.
 */
final class TurnstileTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function contact(array $overrides = []): array
    {
        return [
            'nome' => 'Ana Souza',
            'email' => 'ana@empresa.com.br',
            'telefone' => '19996566767',
            'assunto' => 'Orçamento',
            'mensagem' => 'Preciso de orçamento para corte a laser de chapa de 6mm.',
            ...$overrides,
        ];
    }

    private function enableTurnstile(): void
    {
        config()->set('montec.turnstile.secret', 'segredo-de-teste');
    }

    #[Test]
    public function desligado_por_padrao_o_token_nao_e_exigido(): void
    {
        Mail::fake();
        Http::preventStrayRequests();

        $this->postJson('/api/contato', $this->contact())->assertCreated();
    }

    #[Test]
    public function ligado_exige_o_token(): void
    {
        $this->enableTurnstile();
        Http::preventStrayRequests();

        $this->postJson('/api/contato', $this->contact())
            ->assertStatus(422)
            ->assertJsonValidationErrors('turnstile_token');
    }

    #[Test]
    public function ligado_aceita_token_valido(): void
    {
        $this->enableTurnstile();
        Mail::fake();
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true]),
        ]);

        $this->postJson('/api/contato', $this->contact(['turnstile_token' => 'token-ok']))
            ->assertCreated();
    }

    #[Test]
    public function ligado_recusa_token_invalido(): void
    {
        $this->enableTurnstile();
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response([
                'success' => false,
                'error-codes' => ['invalid-input-response'],
            ]),
        ]);

        $this->postJson('/api/contato', $this->contact(['turnstile_token' => 'token-ruim']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('turnstile_token');
    }

    /**
     * Enviar o IP do visitante para a Cloudflare junto da verificação entregaria
     * a um terceiro exatamente o identificador que o canal de ouvidoria promete
     * não coletar. O `remoteip` é opcional na API da Cloudflare — e fica de fora.
     */
    #[Test]
    public function nunca_envia_o_ip_do_visitante_para_a_cloudflare(): void
    {
        $this->enableTurnstile();
        Mail::fake();
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $this->postJson('/api/ouvidoria', [
            'assunto' => 'Descarte irregular',
            'descricao' => str_repeat('x', 45),
            'turnstile_token' => 'token-ok',
        ])->assertCreated();

        Http::assertSent(function (ClientRequest $request): bool {
            $body = $request->data();

            return ! array_key_exists('remoteip', $body)
                && $body['secret'] === 'segredo-de-teste'
                && $body['response'] === 'token-ok';
        });
    }

    /** Cloudflare fora do ar não pode virar formulário quebrado sem explicação. */
    #[Test]
    public function falha_de_rede_na_verificacao_devolve_erro_legivel(): void
    {
        $this->enableTurnstile();
        Http::fake(function (): void {
            throw new ConnectionException('timeout');
        });

        $this->postJson('/api/contato', $this->contact(['turnstile_token' => 'token-ok']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('turnstile_token');
    }

    #[Test]
    public function vale_para_os_tres_formularios(): void
    {
        $this->enableTurnstile();
        Http::preventStrayRequests();

        $this->postJson('/api/contato', $this->contact())
            ->assertJsonValidationErrors('turnstile_token');

        $this->postJson('/api/ouvidoria', [
            'assunto' => 'Assunto', 'descricao' => str_repeat('x', 45),
        ])->assertJsonValidationErrors('turnstile_token');

        $this->postJson('/api/trabalhe-conosco', [
            'nome' => 'João', 'email' => 'j@b.com', 'telefone' => '19996566767',
            'vaga' => 'Soldador',
            'curriculo' => ['data' => base64_encode('%PDF-1.4'), 'name' => 'cv.pdf'],
        ])->assertJsonValidationErrors('turnstile_token');
    }
}
