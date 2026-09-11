<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RequestHardeningTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function contact(): array
    {
        return [
            'nome' => 'Ana Souza',
            'email' => 'ana@empresa.com.br',
            'telefone' => '19996566767',
            'assunto' => 'Orçamento',
            'mensagem' => 'Preciso de orçamento para corte a laser de chapa de 6mm.',
        ];
    }

    /**
     * Corpo gigante tem de morrer ANTES do parse do JSON: o PHP carrega o corpo
     * inteiro em memória, e 20 requisições simultâneas de 50 MB derrubam o
     * processo sem precisar de nenhuma falha de lógica.
     */
    #[Test]
    public function recusa_corpo_maior_que_o_teto_com_413(): void
    {
        /*
         | 7,5 MB fica na janela entre o teto da aplicação (~6,9 MB) e o do PHP
         | (post_max_size). Acima do limite do PHP quem responde é o próprio PHP,
         | com mensagem em inglês, e este middleware nunca roda — o teste
         | passaria sem testar nada.
         */
        $oversized = str_repeat('A', (int) (7.5 * 1024 * 1024));

        $this->call(
            'POST',
            '/api/contato',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_LENGTH' => (string) strlen($oversized),
            ],
            content: $oversized,
        )->assertStatus(413)
            // Mensagem específica: só o EnforceMaxBodySize a produz. Sem isso o
            // teste passaria por acaso com um 413 vindo de outra camada.
            ->assertJsonPath('message', 'O conteúdo enviado é grande demais. Verifique o tamanho do arquivo.');
    }

    #[Test]
    public function o_teto_de_corpo_nao_atrapalha_um_envio_normal(): void
    {
        Mail::fake();

        $this->postJson('/api/contato', $this->contact())->assertCreated();
    }

    /**
     * Limite por rota E limite global. Só o global bloquearia quem manda um
     * contato e depois um currículo; só o por-rota deixaria um bot varrer os três
     * endpoints somando o triplo de tentativas.
     */
    #[Test]
    public function o_limite_global_soma_as_tentativas_entre_endpoints(): void
    {
        Mail::fake();
        config()->set('montec.anti_bot.rate_limit_per_minute', 10);
        config()->set('montec.anti_bot.rate_limit_global_per_minute', 4);

        $this->postJson('/api/contato', $this->contact())->assertCreated();
        $this->postJson('/api/contato', $this->contact())->assertCreated();

        // Endpoint diferente, mesma origem: consome o mesmo orçamento global.
        $report = ['assunto' => 'Assunto', 'descricao' => str_repeat('x', 45)];
        $this->postJson('/api/ouvidoria', $report)->assertCreated();
        $this->postJson('/api/ouvidoria', $report)->assertCreated();

        $this->postJson('/api/contato', $this->contact())->assertStatus(429);
    }

    #[Test]
    public function respostas_da_api_trazem_cabecalhos_de_seguranca(): void
    {
        Mail::fake();

        $this->postJson('/api/contato', $this->contact())
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'no-referrer');
    }

    /**
     * A API não deve anunciar framework nem versão.
     *
     * LIMITE DESTE TESTE: no harness o PHP não injeta `X-Powered-By`, então ele
     * passaria mesmo se o middleware não removesse nada. O `header_remove` só se
     * verifica contra servidor de verdade — e verificando foi que apareceu o
     * vazamento de "PHP/8.4.24". A defesa definitiva é `expose_php=Off` no
     * php.ini, documentada nas notas de deploy do README.
     */
    #[Test]
    public function respostas_nao_expoem_o_framework(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/contato', $this->contact());

        $this->assertNull($response->headers->get('X-Powered-By'));
        $this->assertNull($response->headers->get('Server'));
    }
}
