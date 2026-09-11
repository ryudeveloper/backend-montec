<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\JobApplication\ResumeStorage;
use App\Models\ContactRequest;
use App\Models\JobApplication;
use App\Models\WhistleblowerReport;
use Illuminate\Console\Command;

/**
 * Expurgo por retenção (LGPD): dado pessoal não fica guardado indefinidamente.
 *
 * Prazos em config/montec.php. A ouvidoria tem prazo mais longo por natureza —
 * apuração de denúncia se arrasta —, mas também vence.
 */
final class PurgeExpiredSubmissions extends Command
{
    protected $signature = 'montec:purge-expired {--dry-run : Apenas relata o que seria apagado}';

    protected $description = 'Apaga envios de formulário além do prazo de retenção (LGPD)';

    public function __construct(private ResumeStorage $storage)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $contacts = $this->purgeContacts($dryRun);
        $applications = $this->purgeJobApplications($dryRun);
        $reports = $this->purgeWhistleblowerReports($dryRun);

        $prefix = $dryRun ? 'Seriam apagados' : 'Apagados';
        $this->info("{$prefix}: {$contacts} contatos, {$applications} candidaturas, {$reports} denúncias.");

        return self::SUCCESS;
    }

    private function purgeContacts(bool $dryRun): int
    {
        $query = ContactRequest::query()
            ->where('created_at', '<', now()->subDays(config('montec.retention_days.contact')));

        return $dryRun ? $query->count() : $query->delete();
    }

    private function purgeJobApplications(bool $dryRun): int
    {
        $query = JobApplication::query()
            ->where('created_at', '<', now()->subDays(config('montec.retention_days.job_application')));

        if ($dryRun) {
            return $query->count();
        }

        $purged = 0;

        // Um a um para o arquivo sair junto do registro: delete em massa deixaria
        // o currículo no disco, e currículo órfão é retenção sem base legal.
        $query->each(function (JobApplication $application) use (&$purged): void {
            $this->storage->delete($application->resume_path);
            $application->delete();
            $purged++;
        });

        return $purged;
    }

    private function purgeWhistleblowerReports(bool $dryRun): int
    {
        $query = WhistleblowerReport::query()
            ->where('created_at', '<', now()->subDays(config('montec.retention_days.whistleblower')));

        return $dryRun ? $query->count() : $query->delete();
    }
}
