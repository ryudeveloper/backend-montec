<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Domain\Portal\PortalAccessAuditor;
use App\Domain\Screening\Screener;
use App\Http\Resources\PageMeta;
use App\Models\JobApplication;
use App\Support\FileSize;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Área de candidaturas.
 *
 * Este controller não conhece denúncia, e o da ouvidoria não conhece
 * candidatura. A separação é estrutural, não só de rota: fica difícil vazar de
 * uma área para a outra por descuido quando o código de cada uma ignora a
 * existência da outra. Há teste lendo os dois arquivos para garantir.
 */
final class JobApplicationPortalController
{
    public function __construct(
        private PortalAccessAuditor $auditor,
        private Screener $screener,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $search = $request->string('busca')->trim()->value();
        $opening = $request->string('vaga')->trim()->value();

        $applications = JobApplication::query()
            ->when($opening !== '', fn ($query) => $query->where('job_opening', $opening))
            ->when($search !== '', function ($query) use ($search): void {
                // Agrupado: sem o closure, o OR escaparia do filtro de vaga e a
                // busca passaria a ignorar o recorte selecionado.
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Applications/Index', [
            'applications' => [
                'data' => $applications->getCollection()
                    ->map(fn (JobApplication $application) => $this->summary($application))
                    ->all(),
                'meta' => PageMeta::from($applications),
            ],
            'totalByOpening' => JobApplication::query()
                ->selectRaw('job_opening, count(*) as total')
                ->groupBy('job_opening')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row) => ['opening' => $row->job_opening, 'total' => (int) $row->total])
                ->all(),
            'filters' => ['search' => $search, 'opening' => $opening],
        ]);
    }

    public function show(JobApplication $application): InertiaResponse
    {
        $application->load('assessment');

        return Inertia::render('Applications/Show', [
            /*
             | A triagem só aparece quando está configurada. Sem chave, o botão
             | nem existe — melhor do que oferecer algo que vai falhar.
             */
            'screeningEnabled' => $this->screener->isEnabled(),
            'assessment' => $application->assessment === null ? null : [
                'score' => $application->assessment->score,
                'strengths' => $application->assessment->strengths,
                'gaps' => $application->assessment->gaps,
                'skills' => $application->assessment->skills,
                'model' => $application->assessment->model,
                'generatedAt' => $application->assessment->generated_at
                    ->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            ],
            'application' => [
                ...$this->summary($application),
                'phone' => $application->phone,
                'message' => $application->message,
                'resumeOriginalName' => $application->resume_original_name,
                'consentAcceptedAt' => $application->consent_accepted_at
                    ?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            ],
        ]);
    }

    /**
     * Entrega o currículo.
     *
     * Única rota pela qual um arquivo guardado fora do webroot se torna
     * alcançável. Daí a combinação: papel conferido pelo middleware, entrega
     * como ANEXO (nunca inline — um PDF ou HTML renderizado no contexto da
     * própria origem é XSS com arquivo de estranho), `nosniff` para o navegador
     * não adivinhar o tipo, `no-store` para não ficar em cache, e registro de
     * auditoria antes de mandar os bytes.
     *
     * Continua fora do Inertia: é resposta de arquivo, não de página.
     */
    public function downloadResume(Request $request, JobApplication $application): StreamedResponse
    {
        $disk = Storage::disk(config('montec.resume.disk'));

        if (! $disk->exists($application->resume_path)) {
            // Pode ter sido expurgado por retenção enquanto a lista ainda o
            // mostrava. 404 em vez de 500.
            abort(404, 'O arquivo deste currículo não está mais disponível.');
        }

        $user = $request->user();
        if ($user !== null) {
            $this->auditor->recordResumeDownload($user, $application->id, $request->ip());
        }

        try {
            return $disk->download(
                $application->resume_path,
                $application->resume_original_name,
                [
                    'Content-Type' => $application->resume_mime,
                    'X-Content-Type-Options' => 'nosniff',
                    'Cache-Control' => 'no-store, max-age=0',
                    'Referrer-Policy' => 'no-referrer',
                ],
            );
        } catch (FileNotFoundException) {
            abort(404, 'O arquivo deste currículo não está mais disponível.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(JobApplication $application): array
    {
        return [
            'id' => $application->id,
            'name' => $application->name,
            'email' => $application->email,
            'jobOpening' => $application->job_opening,
            'receivedAt' => $application->created_at
                ->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            // Formatado no servidor: dividir por 1024 no cliente mostrava "0kB"
            // em arquivo pequeno, e tamanho zero parece arquivo corrompido.
            'resumeSize' => FileSize::human($application->resume_bytes),
            'resumeUrl' => route('portal.applications.resume', $application),
            'detailUrl' => route('portal.applications.show', $application),
        ];
    }
}
