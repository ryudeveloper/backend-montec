<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Turnstile\TurnstileVerifier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class TurnstileToken implements ValidationRule
{
    public function __construct(private TurnstileVerifier $verifier) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->verifier->verify($value)) {
            // Mensagem genérica: detalhar o motivo só ajuda quem está tentando
            // burlar. Para a pessoa de verdade, recarregar resolve.
            $fail('Não foi possível confirmar que você não é um robô. Recarregue a página e tente novamente.');
        }
    }
}
