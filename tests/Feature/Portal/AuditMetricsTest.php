<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\PortalAccessLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * As métricas da auditoria contam EVENTOS, não recursos.
 *
 * A distinção não é preciosismo: "denúncias abertas" lia como quantidade de
 * denúncias pendentes, quando o número é quantas vezes alguém abriu alguma. Se o
 * mesmo relato for lido três vezes, são três eventos — e é exatamente isso que
 * interessa a quem supervisiona.
 *
 * A contagem de recursos distintos vai junto, como complemento, porque as duas
 * respondem perguntas diferentes: "quanto acesso houve" e "a quantos registros".
 */
final class AuditMetricsTest extends TestCase
{
    use RefreshDatabase;

    private function log(User $operator, string $type, string $resourceId): void
    {
        PortalAccessLog::create([
            'user_id' => $operator->id,
            'resource_type' => $type,
            'resource_id' => $resourceId,
            'action' => $type === PortalAccessLog::RESOURCE_RESUME ? 'downloaded' : 'viewed',
            'ip_address' => '203.0.113.10',
        ]);
    }

    #[Test]
    public function conta_eventos_e_nao_recursos(): void
    {
        $operator = User::factory()->create(['roles' => ['hr']]);

        // A MESMA denúncia aberta três vezes = 3 eventos, 1 recurso distinto.
        $this->log($operator, PortalAccessLog::RESOURCE_REPORT, 'relato-A');
        $this->log($operator, PortalAccessLog::RESOURCE_REPORT, 'relato-A');
        $this->log($operator, PortalAccessLog::RESOURCE_REPORT, 'relato-A');
        $this->log($operator, PortalAccessLog::RESOURCE_REPORT, 'relato-B');

        $this->log($operator, PortalAccessLog::RESOURCE_RESUME, 'cv-1');
        $this->log($operator, PortalAccessLog::RESOURCE_RESUME, 'cv-1');

        $this->actingAs(User::factory()->create(['roles' => ['admin']]))
            ->get('/auditoria')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('counts.total', 6)
                ->where('counts.reports', 4)
                ->where('counts.reportsDistinct', 2)
                ->where('counts.resumes', 2)
                ->where('counts.resumesDistinct', 1)
            );
    }

    #[Test]
    public function sem_acesso_registrado_as_metricas_sao_zero(): void
    {
        $this->actingAs(User::factory()->create(['roles' => ['admin']]))
            ->get('/auditoria')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('counts.total', 0)
                ->where('counts.reports', 0)
                ->where('counts.reportsDistinct', 0)
            );
    }
}
