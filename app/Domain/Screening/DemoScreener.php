<?php

declare(strict_types=1);

namespace App\Domain\Screening;

use Illuminate\Support\Str;

/**
 * Parecer de demonstração, sem chamar API nenhuma.
 *
 * Serve para exercitar a tela, o botão e a trilha de auditoria antes de
 * contratar o serviço. NÃO é análise: compara palavras do título da vaga e um
 * vocabulário do setor com o texto do currículo.
 *
 * Duas coisas o mantêm honesto: ele se identifica como demonstração no campo que
 * a tela exibe, e recusa subir em produção. Parecer inventado num sistema real
 * vira decisão sobre a vida de um candidato tomada com base em nada.
 */
final class DemoScreener implements Screener
{
    /** Vocabulário do chão de fábrica — o que a triagem procuraria de verdade. */
    private const VOCABULARY = [
        'MIG', 'MAG', 'TIG', 'eletrodo', 'solda', 'soldagem', 'usinagem', 'torno',
        'fresa', 'CNC', 'calandra', 'dobra', 'corte', 'laser', 'plasma', 'jateamento',
        'pintura', 'chapa', 'estrutura', 'montagem', 'desenho técnico', 'metrologia',
        'paquímetro', 'NR-10', 'NR-11', 'NR-12', 'NR-33', 'NR-34', 'NR-35', 'SENAI',
    ];

    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * @throws UnsafeScreeningDriverException
     */
    public static function assertSafeEnvironment(string $environment): void
    {
        if ($environment === 'production') {
            throw new UnsafeScreeningDriverException(
                'MONTEC_AI_DRIVER="demo" não pode ser usado em produção: ele inventa o parecer '
                .'sem ler o currículo de verdade, e alguém tomaria decisão sobre um candidato com '
                .'base em nada. Configure MONTEC_AI_KEY e use o driver "anthropic".'
            );
        }
    }

    public function assess(string $redactedResume, string $jobOpening): Assessment
    {
        $text = Str::lower($redactedResume);

        $found = $this->matches(self::VOCABULARY, $text);
        $jobTerms = $this->jobTerms($jobOpening);
        $jobMatches = $this->matches($jobTerms, $text);
        $jobMisses = array_values(array_diff($jobTerms, $jobMatches));

        /*
         | A nota sai da sobreposição entre o título da vaga e o texto, com um
         | empurrão do vocabulário do setor. Determinística de propósito: gerar
         | duas vezes e receber notas diferentes destruiria a confiança na
         | demonstração.
         */
        $jobScore = $jobTerms === [] ? 0 : (int) round(60 * (count($jobMatches) / count($jobTerms)));
        $vocabularyScore = (int) round(35 * min(1, count($found) / 6));
        $score = min(100, max(8, $jobScore + $vocabularyScore));

        return new Assessment(
            score: $score,
            strengths: $this->strengths($jobMatches, $found),
            gaps: $this->gaps($jobMisses, $found),
            skills: array_slice($found, 0, 10),
            model: 'demonstração (sem IA)',
        );
    }

    /**
     * @return list<string>
     */
    private function jobTerms(string $jobOpening): array
    {
        // Palavras com 4+ letras: descarta "de", "da", "e" e afins.
        $terms = preg_split('/[^\p{L}\d-]+/u', $jobOpening) ?: [];

        return array_values(array_filter(
            $terms,
            fn (string $term): bool => mb_strlen($term) >= 4,
        ));
    }

    /**
     * @param  list<string>  $needles
     * @return list<string>
     */
    private function matches(array $needles, string $haystack): array
    {
        return array_values(array_filter(
            $needles,
            fn (string $needle): bool => str_contains($haystack, Str::lower($needle)),
        ));
    }

    /**
     * @param  list<string>  $jobMatches
     * @param  list<string>  $found
     * @return list<string>
     */
    private function strengths(array $jobMatches, array $found): array
    {
        $strengths = [];

        if ($jobMatches !== []) {
            $strengths[] = 'O currículo menciona '.implode(', ', array_slice($jobMatches, 0, 3))
                .', termo(s) do próprio título da vaga.';
        }

        if ($found !== []) {
            $strengths[] = 'Vocabulário técnico compatível com o setor: '
                .implode(', ', array_slice($found, 0, 5)).'.';
        }

        if ($strengths === []) {
            $strengths[] = 'Nenhuma evidência clara de aderência encontrada no texto.';
        }

        return $strengths;
    }

    /**
     * @param  list<string>  $jobMisses
     * @param  list<string>  $found
     * @return list<string>
     */
    private function gaps(array $jobMisses, array $found): array
    {
        $gaps = [];

        if ($jobMisses !== []) {
            $gaps[] = 'Sem menção a '.implode(', ', array_slice($jobMisses, 0, 3))
                .', presente(s) no título da vaga.';
        }

        if (count($found) < 4) {
            $gaps[] = 'Poucos termos técnicos do setor no texto — pode ser currículo genérico.';
        }

        $gaps[] = 'Parecer de DEMONSTRAÇÃO: não houve leitura real do conteúdo.';

        return $gaps;
    }
}
