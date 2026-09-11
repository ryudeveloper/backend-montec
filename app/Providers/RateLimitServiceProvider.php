<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class RateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /*
        |----------------------------------------------------------------------
        | Formulários públicos
        |----------------------------------------------------------------------
        | Dois limites, porque um só erra em alguma direção:
        |
        |  - Só global: quem manda um contato e depois um currículo é bloqueado
        |    sem ter feito nada de errado.
        |  - Só por rota: um bot varre os três endpoints e soma o triplo de
        |    tentativas dentro do mesmo minuto.
        |
        | A chave é o HASH do IP, nunca o IP em claro: o limitador precisa
        | distinguir origens, não sabê-las. O valor vive no cache, expira em um
        | minuto e nunca é associado ao conteúdo enviado — é o que permite
        | limitar a ouvidoria sem violar o anonimato prometido na interface.
        */
        RateLimiter::for('public-forms', function (Request $request): array {
            $origin = hash('xxh128', (string) $request->ip());
            $route = $request->route()?->getName() ?? 'sem-rota';

            /** @var int $perRoute */
            $perRoute = config('montec.anti_bot.rate_limit_per_minute');
            /** @var int $global */
            $global = config('montec.anti_bot.rate_limit_global_per_minute');

            return [
                Limit::perMinute($perRoute)
                    ->by("form:{$route}:{$origin}")
                    ->response($this->tooManyRequests()),
                Limit::perMinute($global)
                    ->by("form:global:{$origin}")
                    ->response($this->tooManyRequests()),
            ];
        });
    }

    private function tooManyRequests(): callable
    {
        return static fn () => response()->json([
            'message' => 'Muitas tentativas. Aguarde um minuto e tente novamente.',
        ], 429);
    }
}
