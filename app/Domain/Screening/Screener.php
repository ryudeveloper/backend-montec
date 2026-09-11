<?php

declare(strict_types=1);

namespace App\Domain\Screening;

/**
 * Produz o parecer de aderência.
 *
 * A interface existe para o modo de demonstração poder substituir a chamada real
 * sem que o resto do sistema saiba da diferença — a ação que orquestra, a
 * auditoria e a tela continuam iguais.
 */
interface Screener
{
    public function isEnabled(): bool;

    /**
     * @param  string  $redactedResume  texto JÁ redigido — sem identificadores
     *
     * @throws ScreeningUnavailableException
     */
    public function assess(string $redactedResume, string $jobOpening): Assessment;
}
