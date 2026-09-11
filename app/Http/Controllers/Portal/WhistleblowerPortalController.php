<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Domain\Portal\PortalAccessAuditor;
use App\Http\Resources\PageMeta;
use App\Models\WhistleblowerReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Ouvidoria no portal — leitura apenas.
 *
 * Este controller não conhece candidatura nem currículo, e o do RH não conhece
 * denúncia. A separação é estrutural, não só de rota.
 */
final class WhistleblowerPortalController
{
    public function __construct(private PortalAccessAuditor $auditor) {}

    public function index(Request $request): InertiaResponse
    {
        $mode = $request->string('identificacao')->trim()->value();

        $reports = WhistleblowerReport::query()
            ->when($mode === 'anonima', fn ($query) => $query->where('is_anonymous', true))
            ->when($mode === 'identificada', fn ($query) => $query->where('is_anonymous', false))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Reports/Index', [
            'reports' => [
                /*
                 | A listagem NÃO manda o texto da denúncia.
                 |
                 | Havia uma prévia de 120 caracteres aqui, e ela furava a
                 | auditoria: numa denúncia curta a prévia era o relato inteiro,
                 | então bastava abrir a lista para ler tudo sem deixar registro.
                 | Se o motivo de auditar é saber quem leu o quê, não pode haver
                 | um caminho que entregue o conteúdo sem passar pelo registro.
                 |
                 | Assunto, modo e data bastam para navegar — e o assunto é um
                 | resumo escrito pelo próprio denunciante.
                 */
                'data' => $reports->getCollection()->map(fn (WhistleblowerReport $report) => [
                    'id' => $report->id,
                    'subject' => $report->subject,
                    'isAnonymous' => $report->is_anonymous,
                    'receivedAt' => $report->created_at
                        ->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                    'detailUrl' => route('portal.reports.show', $report),
                ])->all(),
                'meta' => PageMeta::from($reports),
            ],
            'counts' => [
                'anonymous' => WhistleblowerReport::where('is_anonymous', true)->count(),
                'identified' => WhistleblowerReport::where('is_anonymous', false)->count(),
            ],
            'filters' => ['mode' => $mode],
        ]);
    }

    public function show(Request $request, WhistleblowerReport $report): InertiaResponse
    {
        $user = $request->user();

        // Auditado na abertura, não só num download: aqui o conteúdo sensível é
        // o texto da denúncia, e ele é lido na própria tela.
        if ($user !== null) {
            $this->auditor->recordReportView($user, $report->id, $request->ip());
        }

        return Inertia::render('Reports/Show', [
            'report' => [
                'id' => $report->id,
                'subject' => $report->subject,
                'description' => $report->description,
                'isAnonymous' => $report->is_anonymous,
                'receivedAt' => $report->created_at
                    ->timezone(config('app.timezone'))->format('d/m/Y \à\s H:i'),
                /*
                 | Em denúncia anônima estes campos são nulos no banco — nunca
                 | foram gravados. Mandar `null` explicitamente, em vez de omitir
                 | a chave, deixa o contrato do cliente estável e evita que a
                 | ausência da chave seja lida como erro de serialização.
                 */
                'name' => $report->is_anonymous ? null : $report->name,
                'email' => $report->is_anonymous ? null : $report->email,
            ],
        ]);
    }
}
