<?php

declare(strict_types=1);

namespace App\Domain\Screening;

use App\Domain\Portal\PortalAccessAuditor;
use App\Models\JobApplication;
use App\Models\ResumeAssessment;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Gera o parecer de um currículo, do arquivo ao registro.
 *
 * A ordem importa: extrair → REDIGIR → enviar. O texto nunca sai daqui com
 * identificador, e é por isso que a redação acontece antes da chamada, não
 * depois nem "na hora de exibir".
 *
 * O acesso é auditado como qualquer outro: analisar um currículo é ler um
 * currículo, e quem disparou a análise precisa ficar registrado.
 */
final class ScreenResumeAction
{
    public function __construct(
        private ResumeTextExtractor $extractor,
        private ResumeRedactor $redactor,
        private Screener $screener,
        private PortalAccessAuditor $auditor,
    ) {}

    /**
     * @throws UnreadableResumeException|ScreeningUnavailableException
     */
    public function execute(JobApplication $application, User $operator, ?string $ipAddress): ResumeAssessment
    {
        $disk = Storage::disk(config('montec.resume.disk'));

        if (! $disk->exists($application->resume_path)) {
            throw new UnreadableResumeException('O arquivo deste currículo não está mais disponível.');
        }

        $extension = pathinfo($application->resume_path, PATHINFO_EXTENSION);

        if (! $this->extractor->supports($extension)) {
            throw new UnreadableResumeException(
                'Currículos em .doc não podem ser lidos automaticamente. Abra o arquivo para avaliar.'
            );
        }

        $text = $this->extractor->extract((string) $disk->path($application->resume_path), $extension);

        // Redação ANTES da chamada. Os dados conhecidos vêm do banco porque
        // regex sozinha não pega variação de grafia do próprio nome.
        $redacted = $this->redactor->redact($text, [
            'name' => $application->name,
            'email' => $application->email,
            'phone' => $application->phone,
        ]);

        $assessment = $this->screener->assess($redacted, $application->job_opening);

        $this->auditor->recordResumeAnalysis($operator, $application->id, $ipAddress);

        return ResumeAssessment::updateOrCreate(
            ['job_application_id' => $application->id],
            [
                'score' => $assessment->score,
                'strengths' => $assessment->strengths,
                'gaps' => $assessment->gaps,
                'skills' => $assessment->skills,
                'model' => $assessment->model,
                'generated_at' => now(),
            ],
        );
    }
}
