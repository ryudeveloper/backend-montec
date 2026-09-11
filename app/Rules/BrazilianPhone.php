<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Telefone brasileiro com DDD. O frontend envia sem máscara (só dígitos), mas
 * aceitamos formatado para não quebrar quem integrar depois.
 *
 * 10 dígitos = fixo, 11 = celular (nono dígito). DDD válido vai de 11 a 99.
 */
final class BrazilianPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Informe um telefone válido com DDD.');

            return;
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (! in_array(strlen($digits), [10, 11], true)) {
            $fail('Informe um telefone válido com DDD.');

            return;
        }

        $areaCode = (int) substr($digits, 0, 2);

        if ($areaCode < 11) {
            $fail('Informe um DDD válido.');

            return;
        }

        // Celular no plano brasileiro começa com 9 depois do DDD.
        if (strlen($digits) === 11 && $digits[2] !== '9') {
            $fail('Informe um telefone válido com DDD.');
        }
    }
}
