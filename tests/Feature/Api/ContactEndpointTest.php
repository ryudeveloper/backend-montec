<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Mail\ContactRequestReceived;
use App\Models\ContactRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ContactEndpointTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'nome' => 'Ana Souza',
            'email' => 'ana@empresa.com.br',
            'telefone' => '19996566767',
            'assunto' => 'Solicitação de orçamento',
            'mensagem' => 'Preciso de orçamento para corte a laser de chapa 6mm, lote de 200 peças.',
            ...$overrides,
        ];
    }

    #[Test]
    public function aceita_uma_solicitacao_valida_e_persiste(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/contato', $this->payload());

        $response->assertCreated()->assertJsonStructure(['message', 'protocolo']);

        $this->assertDatabaseHas('contact_requests', [
            'email' => 'ana@empresa.com.br',
            'subject' => 'Solicitação de orçamento',
            'phone' => '19996566767',
        ]);

        Mail::assertSent(ContactRequestReceived::class);
    }

    /** O cliente lê `payload.message` para exibir o erro — sem isso ele mostra genérico. */
    #[Test]
    public function responde_422_com_campo_message_quando_invalido(): void
    {
        $response = $this->postJson('/api/contato', $this->payload(['email' => 'nao-e-email']));

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors('email');
    }

    #[Test]
    public function normaliza_email_e_remove_mascara_do_telefone(): void
    {
        Mail::fake();

        $this->postJson('/api/contato', $this->payload([
            'email' => '  ANA@Empresa.COM.BR ',
            'telefone' => '(19) 99656-6767',
        ]))->assertCreated();

        $this->assertDatabaseHas('contact_requests', [
            'email' => 'ana@empresa.com.br',
            'phone' => '19996566767',
        ]);
    }

    /**
     * Honeypot: o frontend valida no client, mas um bot posta direto no endpoint
     * e nunca passa por lá. Sem esta revalidação a proteção é decorativa.
     */
    #[Test]
    public function descarta_envio_com_honeypot_preenchido(): void
    {
        Mail::fake();

        $this->postJson('/api/contato', $this->payload(['website' => 'http://spam.example']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('website');

        $this->assertSame(0, ContactRequest::count());
        Mail::assertNothingSent();
    }

    #[Test]
    public function aceita_honeypot_vazio_que_e_o_que_o_frontend_envia(): void
    {
        Mail::fake();

        $this->postJson('/api/contato', $this->payload(['website' => '']))->assertCreated();
    }

    #[Test]
    public function registra_o_consentimento_lgpd_quando_informado(): void
    {
        Mail::fake();

        $this->postJson('/api/contato', $this->payload(['consentimento' => true]))->assertCreated();

        $this->assertNotNull(ContactRequest::sole()->consent_accepted_at);
    }

    #[Test]
    public function recusa_telefone_sem_ddd_valido(): void
    {
        $this->postJson('/api/contato', $this->payload(['telefone' => '1234']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('telefone');
    }

    #[Test]
    public function recusa_mensagem_curta_no_mesmo_limite_do_frontend(): void
    {
        $this->postJson('/api/contato', $this->payload(['mensagem' => 'curto']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('mensagem');
    }
}
