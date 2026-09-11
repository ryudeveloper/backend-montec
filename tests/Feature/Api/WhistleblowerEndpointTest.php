<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Mail\WhistleblowerReportReceived;
use App\Models\WhistleblowerReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class WhistleblowerEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const DESCRIPTION = 'No dia 12/08, no setor de pintura, presenciei descarte irregular de solvente na rede pluvial.';

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'assunto' => 'Descarte irregular de resíduo',
            'descricao' => self::DESCRIPTION,
            ...$overrides,
        ];
    }

    #[Test]
    public function aceita_denuncia_anonima_sem_nome_nem_email(): void
    {
        Mail::fake();

        $this->postJson('/api/ouvidoria', $this->payload())
            ->assertCreated()
            ->assertJsonStructure(['message', 'protocolo']);

        $report = WhistleblowerReport::sole();
        $this->assertTrue($report->is_anonymous);
        $this->assertNull($report->name);
        $this->assertNull($report->email);

        Mail::assertSent(WhistleblowerReportReceived::class);
    }

    #[Test]
    public function aceita_denuncia_identificada(): void
    {
        Mail::fake();

        $this->postJson('/api/ouvidoria', $this->payload([
            'nome' => 'Carlos Lima',
            'email' => 'carlos@exemplo.com',
        ]))->assertCreated();

        $report = WhistleblowerReport::sole();
        $this->assertFalse($report->is_anonymous);
        $this->assertSame('Carlos Lima', $report->name);
        $this->assertSame('carlos@exemplo.com', $report->email);
    }

    /**
     * A tabela não tem onde gravar identificador técnico. Garantia estrutural:
     * o anonimato prometido na interface é compromisso legal, e uma coluna
     * acrescentada por descuido reabriria o vazamento sem ninguém notar.
     */
    #[Test]
    public function a_tabela_nao_tem_coluna_para_nenhum_identificador_tecnico(): void
    {
        $columns = Schema::getColumnListing('whistleblower_reports');

        foreach (['ip', 'ip_address', 'user_agent', 'session_id', 'fingerprint', 'device_id'] as $forbidden) {
            $this->assertNotContains($forbidden, $columns, "A coluna '{$forbidden}' não pode existir.");
        }
    }

    /**
     * Cliente adulterado enviando identificação junto de denúncia anônima: o
     * servidor descarta. Confiar na UI para isso é confiar no atacante.
     */
    #[Test]
    public function nao_grava_identificacao_quando_o_cliente_manda_email_sem_nome(): void
    {
        Mail::fake();

        // Sem `nome` a denúncia é anônima por contrato; `email` sozinho é ruído
        // e não pode virar identificador persistido.
        $this->postJson('/api/ouvidoria', $this->payload(['email' => 'vazou@exemplo.com']))
            ->assertCreated();

        $report = WhistleblowerReport::sole();
        $this->assertTrue($report->is_anonymous);
        $this->assertNull($report->email);
    }

    #[Test]
    public function exige_email_de_quem_escolhe_se_identificar(): void
    {
        $this->postJson('/api/ouvidoria', $this->payload(['nome' => 'Carlos Lima']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    /** Logar denúncia anônima é exatamente o que o canal promete não fazer. */
    #[Test]
    public function nao_escreve_o_conteudo_da_denuncia_em_log(): void
    {
        Mail::fake();
        Log::spy();

        $this->postJson('/api/ouvidoria', $this->payload())->assertCreated();

        Log::shouldNotHaveReceived('info');
        Log::shouldNotHaveReceived('debug');
        Log::shouldNotHaveReceived('warning');
    }

    /** Reply-To apontando para o denunciante anularia o anonimato na caixa. */
    #[Test]
    public function o_email_de_denuncia_anonima_nao_tem_reply_to(): void
    {
        Mail::fake();

        $this->postJson('/api/ouvidoria', $this->payload())->assertCreated();

        Mail::assertSent(WhistleblowerReportReceived::class, function (WhistleblowerReportReceived $mail): bool {
            return $mail->envelope()->replyTo === [];
        });
    }

    #[Test]
    public function descarta_envio_com_honeypot_preenchido(): void
    {
        Mail::fake();

        $this->postJson('/api/ouvidoria', $this->payload(['website' => 'bot']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('website');

        $this->assertSame(0, WhistleblowerReport::count());
    }

    #[Test]
    public function recusa_descricao_curta_no_mesmo_limite_do_frontend(): void
    {
        $this->postJson('/api/ouvidoria', $this->payload(['descricao' => 'muito curto']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('descricao');
    }
}
