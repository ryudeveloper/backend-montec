<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Porta de entrada do portal.
 *
 * Encaminha para a primeira área que o usuário de fato pode abrir, em vez de
 * mandar todo mundo para candidaturas — quem é só da ouvidoria receberia 403 ao
 * entrar, o que pareceria defeito e não regra.
 *
 * Sem nenhuma área liberada, responde 200 com explicação e botão de sair, não
 * 403: um 403 aqui prendia a pessoa: a página de erro não tinha o layout do
 * portal, então não tinha Sair, e /rh/login devolve quem já está autenticado
 * para cá, que negava de novo. A única saída era apagar o cookie na mão.
 */
final class PortalHomeController
{
    public function __invoke(Request $request): RedirectResponse|InertiaResponse
    {
        $user = $request->user();

        if ($user?->canAccessResumes() === true) {
            return redirect()->route('portal.applications.index');
        }

        if ($user?->canAccessReports() === true) {
            return redirect()->route('portal.reports.index');
        }

        if ($user?->canAccessAudit() === true) {
            return redirect()->route('portal.audit.index');
        }

        return Inertia::render('NoAccess');
    }
}
