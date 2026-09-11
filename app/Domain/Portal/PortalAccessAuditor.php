<?php

declare(strict_types=1);

namespace App\Domain\Portal;

use App\Models\PortalAccessLog;
use App\Models\User;

/**
 * Registra acesso a dado sensível do portal.
 *
 * O IP é gravado, ao contrário da ouvidoria pública — e por motivo oposto. Lá o
 * anonimato do denunciante é a promessa; aqui são funcionários identificados
 * operando sistema interno, e o registro existe para responsabilizá-los pelo
 * acesso a dado de terceiros (LGPD).
 *
 * Importante não confundir os dois lados: registrar quem LEU uma denúncia não
 * toca o anonimato de quem a ESCREVEU — não há dado do denunciante a registrar.
 */
final class PortalAccessAuditor
{
    public function recordResumeDownload(User $user, string $applicationId, ?string $ipAddress): void
    {
        $this->record($user, PortalAccessLog::RESOURCE_RESUME, $applicationId, 'downloaded', $ipAddress);
    }

    /**
     * Analisar um currículo é ler um currículo: quem disparou a análise fica
     * registrado como em qualquer outro acesso.
     */
    public function recordResumeAnalysis(User $user, string $applicationId, ?string $ipAddress): void
    {
        $this->record($user, PortalAccessLog::RESOURCE_RESUME, $applicationId, 'analyzed', $ipAddress);
    }

    public function recordReportView(User $user, string $reportId, ?string $ipAddress): void
    {
        $this->record($user, PortalAccessLog::RESOURCE_REPORT, $reportId, 'viewed', $ipAddress);
    }

    private function record(
        User $user,
        string $resourceType,
        string $resourceId,
        string $action,
        ?string $ipAddress,
    ): void {
        PortalAccessLog::create([
            'user_id' => $user->id,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'action' => $action,
            'ip_address' => $ipAddress,
        ]);
    }
}
