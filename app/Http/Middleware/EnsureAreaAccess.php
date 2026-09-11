<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\PortalArea;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige acesso a uma ÁREA do portal.
 *
 * A rota declara área (`area:resumes`), não papel. Com papel na rota, a
 * sobreposição do administrador teria de ser repetida em cada grupo — e é aí que
 * alguém esquece de incluí-la, ou a inclui onde não devia.
 *
 * Separado do `auth` de propósito: estar autenticado não é estar autorizado.
 */
final class EnsureAreaAccess
{
    public function handle(Request $request, Closure $next, string $area): Response
    {
        $required = PortalArea::tryFrom($area);
        $user = $request->user();

        // Área inexistente no enum: nega. Erro de digitação na rota não pode
        // virar uma área sem proteção.
        if ($required === null || $user === null || ! $user->canAccess($required)) {
            abort(403, 'Seu usuário não tem acesso a esta área do portal.');
        }

        return $next($request);
    }
}
