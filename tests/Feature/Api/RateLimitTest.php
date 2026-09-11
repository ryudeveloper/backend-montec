<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Rate limit é a ÚNICA barreira anti-bot que o servidor controla: honeypot e
 * tempo mínimo de preenchimento moram no cliente, e quem posta direto no
 * endpoint não passa por nenhum dos dois.
 */
final class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('montec.anti_bot.rate_limit_per_minute', 3);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'nome' => 'Ana Souza',
            'email' => 'ana@empresa.com.br',
            'telefone' => '19996566767',
            'assunto' => 'Orçamento',
            'mensagem' => 'Preciso de orçamento para corte a laser de chapa de 6mm.',
        ];
    }

    #[Test]
    public function bloqueia_com_429_apos_o_limite_e_devolve_message(): void
    {
        Mail::fake();

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->postJson('/api/contato', $this->payload())->assertCreated();
        }

        // O cliente exibe `payload.message`; sem ele o usuário vê erro genérico.
        $this->postJson('/api/contato', $this->payload())
            ->assertStatus(429)
            ->assertJsonPath('message', 'Muitas tentativas. Aguarde um minuto e tente novamente.');
    }

    #[Test]
    public function o_limite_vale_para_os_tres_formularios(): void
    {
        foreach (['/api/contato', '/api/trabalhe-conosco', '/api/ouvidoria'] as $endpoint) {
            $middleware = collect(app('router')->getRoutes()->getRoutes())
                ->first(fn ($route) => $route->uri() === ltrim($endpoint, '/'))
                ->gatherMiddleware();

            $this->assertContains('throttle:public-forms', $middleware, "{$endpoint} sem rate limit.");
        }
    }
}
