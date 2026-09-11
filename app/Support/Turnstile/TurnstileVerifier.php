<?php

declare(strict_types=1);

namespace App\Support\Turnstile;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Verificação de token do Cloudflare Turnstile.
 *
 * O IP do visitante NÃO é enviado. O parâmetro `remoteip` é opcional na API da
 * Cloudflare, e mandá-lo entregaria a um terceiro exatamente o identificador que
 * o canal de ouvidoria promete não coletar — pela mesma razão o rate limit usa
 * hash do IP em vez do IP em claro.
 */
final class TurnstileVerifier
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function isEnabled(): bool
    {
        return $this->secret() !== '';
    }

    public function verify(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->retry(2, 200)
                ->post(self::VERIFY_URL, [
                    'secret' => $this->secret(),
                    'response' => $token,
                ]);
        } catch (ConnectionException) {
            /*
             | Fail closed: Cloudflare inacessível recusa o envio.
             |
             | Fail open seria mais gentil com o visitante, mas transforma uma
             | queda da Cloudflare em janela aberta para bot — e é justamente
             | durante um ataque que a verificação tende a falhar.
             */
            return false;
        }

        return $response->successful() && $response->json('success') === true;
    }

    private function secret(): string
    {
        return trim((string) config('montec.turnstile.secret', ''));
    }
}
