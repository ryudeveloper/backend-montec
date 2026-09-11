<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Resources\PageMeta;
use App\Models\JobApplication;
use App\Models\PortalAccessLog;
use App\Models\User;
use App\Models\WhistleblowerReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Trilha de auditoria — exclusiva da administração.
 *
 * Só leitura. Não há rota de edição nem de exclusão, e isso é deliberado:
 * trilha que a interface pode alterar não serve como trilha. Inclusive para
 * quem administra — o acesso do próprio administrador às duas áreas
 * operacionais também é registrado aqui.
 */
final class AuditPortalController
{
    public function index(Request $request): InertiaResponse
    {
        $type = $request->string('tipo')->trim()->value();
        $userId = $request->integer('usuario');

        $logs = PortalAccessLog::query()
            ->with('user:id,name,email')
            ->when(
                in_array($type, [PortalAccessLog::RESOURCE_RESUME, PortalAccessLog::RESOURCE_REPORT], true),
                fn ($query) => $query->where('resource_type', $type),
            )
            ->when($userId > 0, fn ($query) => $query->where('user_id', $userId))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Audit/Index', [
            'logs' => [
                'data' => $logs->getCollection()
                    ->map(fn (PortalAccessLog $log) => $this->present($log))
                    ->all(),
                'meta' => PageMeta::from($logs),
            ],
            /*
             | Métricas contam EVENTOS de acesso, não recursos: se o mesmo relato
             | for lido três vezes, são três eventos — e é isso que interessa a
             | quem supervisiona. A contagem de recursos distintos vai junto,
             | porque as duas respondem perguntas diferentes: quanto acesso houve,
             | e a quantos registros.
             */
            'counts' => [
                'total' => PortalAccessLog::count(),
                'resumes' => $this->countEvents(PortalAccessLog::RESOURCE_RESUME),
                'resumesDistinct' => $this->countDistinctResources(PortalAccessLog::RESOURCE_RESUME),
                'reports' => $this->countEvents(PortalAccessLog::RESOURCE_REPORT),
                'reportsDistinct' => $this->countDistinctResources(PortalAccessLog::RESOURCE_REPORT),
            ],
            'operators' => User::query()
                ->whereIn('id', PortalAccessLog::query()->select('user_id')->distinct())
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
                ->all(),
            'filters' => ['type' => $type, 'userId' => $userId > 0 ? $userId : null],
        ]);
    }

    private function countEvents(string $type): int
    {
        return PortalAccessLog::where('resource_type', $type)->count();
    }

    private function countDistinctResources(string $type): int
    {
        return PortalAccessLog::where('resource_type', $type)
            ->distinct()
            ->count('resource_id');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(PortalAccessLog $log): array
    {
        return [
            'id' => $log->id,
            'operator' => $log->user?->name ?? 'usuário removido',
            'operatorEmail' => $log->user?->email,
            'action' => $log->action === 'downloaded' ? 'baixou currículo' : 'abriu denúncia',
            'resourceType' => $log->resource_type,
            'subject' => $this->describeResource($log),
            'ip' => $log->ip_address,
            'at' => $log->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i:s'),
        ];
    }

    /**
     * Rótulo do recurso acessado.
     *
     * O registro sobrevive ao dado — a candidatura pode ter sido expurgada por
     * retenção. Nesse caso a trilha continua provando QUE houve acesso, mesmo
     * sem poder dizer a quê.
     */
    private function describeResource(PortalAccessLog $log): string
    {
        if ($log->resource_type === PortalAccessLog::RESOURCE_RESUME) {
            return JobApplication::find($log->resource_id)?->name ?? 'registro expurgado';
        }

        return WhistleblowerReport::find($log->resource_id)?->subject ?? 'registro expurgado';
    }
}
