<?php

declare(strict_types=1);

namespace App\Domain\Screening;

/**
 * Parecer de aderência à vaga.
 *
 * É SUGESTÃO, não decisão. Não há campo de "aprovado" nem de status — o Art. 20
 * da LGPD dá ao candidato direito de revisão de decisão tomada unicamente por
 * processamento automatizado, e não existir onde gravar essa decisão é o que
 * garante que o sistema não a tome.
 */
final readonly class Assessment
{
    /**
     * @param  int  $score  0 a 100 — aderência à vaga, não qualidade da pessoa
     * @param  list<string>  $strengths
     * @param  list<string>  $gaps
     * @param  list<string>  $skills
     */
    public function __construct(
        public int $score,
        public array $strengths,
        public array $gaps,
        public array $skills,
        public string $model,
    ) {}
}
