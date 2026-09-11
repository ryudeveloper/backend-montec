<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Rules\TurnstileToken;
use App\Support\Turnstile\TurnstileVerifier;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base dos três formulários públicos.
 *
 * O honeypot é revalidado aqui porque o frontend só o checa no client: um bot
 * que poste direto no endpoint nunca passa pelo formulário. Preenchido =
 * descarta. A mensagem é genérica de propósito — dizer "honeypot" ensina o bot.
 */
abstract class PublicFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Regras comuns a todo formulário público: honeypot + Turnstile.
     *
     * Ficam juntas num só método para nenhum endpoint novo nascer sem elas por
     * esquecimento — é o tipo de omissão que só aparece quando o spam chega.
     *
     * @return array<string, mixed>
     */
    protected function publicFormRules(): array
    {
        return [...$this->honeypotRules(), ...$this->turnstileRules()];
    }

    /**
     * @return array<string, mixed>
     */
    protected function turnstileRules(): array
    {
        $verifier = app(TurnstileVerifier::class);

        if (! $verifier->isEnabled()) {
            return [];
        }

        return ['turnstile_token' => ['required', 'string', new TurnstileToken($verifier)]];
    }

    /**
     * @return array<string, mixed>
     */
    protected function honeypotRules(): array
    {
        /** @var string $field */
        $field = config('montec.anti_bot.honeypot_field');

        return [
            // `max:0` em vez de `prohibited`: o frontend envia string vazia, que
            // é legítima; só um valor preenchido caracteriza bot.
            $field => ['nullable', 'string', 'max:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        /** @var string $field */
        $field = config('montec.anti_bot.honeypot_field');

        return [
            "{$field}.max" => 'Envio bloqueado.',
        ];
    }

    /**
     * O aceite da Política de Privacidade é registrado quando informado.
     * Opcional no contrato porque o frontend atual ainda não envia o campo —
     * ver README, seção "Pendências de contrato".
     */
    protected function consentAcceptedAt(): ?string
    {
        return $this->boolean('consentimento') ? now()->toDateTimeString() : null;
    }
}
