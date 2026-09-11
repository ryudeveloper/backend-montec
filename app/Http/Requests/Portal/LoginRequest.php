<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class LoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:180'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            /*
             | Mensagem única para e-mail inexistente e senha errada: distinguir
             | os dois casos entrega ao atacante a lista de quem tem conta.
             */
            throw ValidationException::withMessages([
                'email' => 'Credenciais inválidas.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        Event::dispatch(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        // 429 e não 422: é bloqueio por excesso de tentativas, não campo inválido.
        abort(429, "Muitas tentativas de acesso. Tente novamente em {$seconds} segundos.");
    }

    /**
     * Chave por e-mail + origem. Só por IP, um escritório atrás de NAT se
     * bloqueia sozinho; só por e-mail, um atacante distribui e passa.
     */
    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')->value()))
            .'|'.hash('xxh128', (string) $this->ip());
    }
}
