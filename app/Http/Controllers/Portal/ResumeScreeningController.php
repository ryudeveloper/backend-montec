<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Domain\Screening\ScreeningUnavailableException;
use App\Domain\Screening\ScreenResumeAction;
use App\Domain\Screening\UnreadableResumeException;
use App\Models\JobApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Dispara a análise de um currículo.
 *
 * POST porque cria registro e custa dinheiro — não é operação idempotente de
 * leitura, e não pode ser disparada por um GET que um crawler ou um prefetch
 * alcance.
 *
 * Fica na área de currículos: quem pode ler o currículo pode pedir o parecer
 * dele. Não há permissão nova, e isso é deliberado — o parecer não expõe nada
 * que quem já abriu o arquivo não pudesse ver.
 */
final class ResumeScreeningController
{
    public function store(
        Request $request,
        JobApplication $application,
        ScreenResumeAction $action,
    ): RedirectResponse {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        try {
            $action->execute($application, $user, $request->ip());
        } catch (UnreadableResumeException|ScreeningUnavailableException $exception) {
            /*
             | Volta com o erro na sessão em vez de estourar: a mensagem destas
             | duas exceções é escrita para o RH ler, e a tela do candidato
             | continua útil mesmo sem o parecer.
             */
            return $this->backToApplication($application)
                ->withErrors(['screening' => $exception->getMessage()]);
        }

        return $this->backToApplication($application)->with('success', 'Parecer gerado.');
    }

    /**
     * Destino explícito, não `back()`.
     *
     * `back()` depende do referer e do estado de sessão: na prática o RH clicava
     * em "Gerar parecer" e era devolvido à listagem, sem ver o resultado. Ação
     * que pertence a um recurso redireciona para esse recurso.
     */
    private function backToApplication(JobApplication $application): RedirectResponse
    {
        return redirect()->route('portal.applications.show', $application);
    }
}
