<?php

declare(strict_types=1);

namespace App\Domain\Screening;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Produz o parecer de aderência chamando a API da Anthropic.
 *
 * Ligado pela presença da chave: sem ela o recurso não aparece na interface.
 *
 * O texto que chega aqui JÁ passou pelo ResumeRedactor — nome, e-mail, telefone,
 * CPF, RG e idade foram removidos. A instrução avisa o modelo disso e proíbe
 * especulação sobre identidade, porque modelo tende a preencher lacuna, e
 * inventar identidade é pior do que não tê-la.
 */
final class AnthropicScreener implements Screener
{
    /** Saída estruturada via ferramenta: texto livre exigiria adivinhar formato. */
    private const TOOL_NAME = 'registrar_parecer';

    public function isEnabled(): bool
    {
        return $this->key() !== '';
    }

    /**
     * @throws ScreeningUnavailableException
     */
    public function assess(string $redactedResume, string $jobOpening): Assessment
    {
        if (! $this->isEnabled()) {
            throw new ScreeningUnavailableException('A triagem por IA não está configurada.');
        }

        $model = (string) config('montec.ai.model');

        try {
            $response = Http::withHeaders([
                // Credencial no cabeçalho, nunca no corpo nem na URL — onde
                // acabaria em log de proxy e em histórico.
                'x-api-key' => $this->key(),
                'anthropic-version' => (string) config('montec.ai.version'),
                'content-type' => 'application/json',
            ])
                ->timeout(60)
                ->retry(2, 500, throw: false)
                ->post((string) config('montec.ai.endpoint'), [
                    'model' => $model,
                    'max_tokens' => 1200,
                    'system' => $this->instructions(),
                    'tools' => [$this->tool()],
                    // Obriga a saída estruturada: sem isto o modelo pode
                    // responder em prosa e não haveria o que exibir em campo.
                    'tool_choice' => ['type' => 'tool', 'name' => self::TOOL_NAME],
                    'messages' => [[
                        'role' => 'user',
                        'content' => $this->prompt($redactedResume, $jobOpening),
                    ]],
                ]);
        } catch (ConnectionException $exception) {
            throw new ScreeningUnavailableException(
                'Não foi possível falar com o serviço de análise. Tente novamente em instantes.',
                previous: $exception,
            );
        }

        if (! $response->successful()) {
            throw new ScreeningUnavailableException(
                'O serviço de análise recusou a requisição. Tente novamente em instantes.'
            );
        }

        return $this->toAssessment($response->json(), $model);
    }

    private function key(): string
    {
        return trim((string) config('montec.ai.key', ''));
    }

    private function instructions(): string
    {
        return <<<'TXT'
        Você avalia a aderência de um currículo a uma vaga de uma indústria
        metalmecânica brasileira (montagem industrial, corte, usinagem, solda,
        pintura).

        Regras:

        1. O texto do currículo foi REDIGIDO antes de chegar até você: nome,
           e-mail, telefone, documentos, endereço e idade foram substituídos por
           [removido]. Não especule sobre identidade, gênero, origem ou idade da
           pessoa, e não use nada disso na avaliação. Se faltar informação,
           registre como ponto de atenção.
        2. Avalie SÓ evidência presente no texto. Não invente experiência,
           certificação ou formação que não esteja escrita.
        3. A nota mede ADERÊNCIA À VAGA, não qualidade da pessoa nem
           empregabilidade.
        4. Seu parecer é consultivo: quem decide é a equipe de recrutamento.
           Escreva para informar essa decisão, não para substituí-la.
        5. Responda em português do Brasil, direto, sem adjetivos vazios.
        TXT;
    }

    /**
     * @return array<string, mixed>
     */
    private function tool(): array
    {
        return [
            'name' => self::TOOL_NAME,
            'description' => 'Registra o parecer de aderência do currículo à vaga.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'score' => [
                        'type' => 'integer',
                        'minimum' => 0,
                        'maximum' => 100,
                        'description' => 'Aderência à vaga, de 0 a 100.',
                    ],
                    'strengths' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'maxItems' => 5,
                        'description' => 'Pontos a favor, cada um citando a evidência do texto.',
                    ],
                    'gaps' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'maxItems' => 5,
                        'description' => 'Pontos de atenção: requisitos da vaga sem evidência no currículo.',
                    ],
                    'skills' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'maxItems' => 12,
                        'description' => 'Habilidades técnicas e certificações encontradas.',
                    ],
                ],
                'required' => ['score', 'strengths', 'gaps', 'skills'],
            ],
        ];
    }

    private function prompt(string $resume, string $jobOpening): string
    {
        return <<<TXT
        VAGA: {$jobOpening}

        CURRÍCULO (texto redigido):
        ---
        {$resume}
        ---
        TXT;
    }

    /**
     * @throws ScreeningUnavailableException
     */
    private function toAssessment(mixed $payload, string $model): Assessment
    {
        $input = $this->extractToolInput($payload);

        $score = $input['score'] ?? null;

        // Nota fora da faixa é resposta inválida, não algo para exibir: uma nota
        // de 140 num campo de 0 a 100 quebraria a leitura e o banco.
        if (! is_int($score) || $score < 0 || $score > 100) {
            throw new ScreeningUnavailableException('O serviço de análise devolveu um parecer inválido.');
        }

        return new Assessment(
            score: $score,
            strengths: $this->stringList($input['strengths'] ?? []),
            gaps: $this->stringList($input['gaps'] ?? []),
            skills: $this->stringList($input['skills'] ?? []),
            model: $model,
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ScreeningUnavailableException
     */
    private function extractToolInput(mixed $payload): array
    {
        try {
            /** @var list<array<string, mixed>> $content */
            $content = $payload['content'] ?? [];

            foreach ($content as $block) {
                if (($block['type'] ?? null) === 'tool_use' && is_array($block['input'] ?? null)) {
                    return $block['input'];
                }
            }
        } catch (Throwable) {
            // Cai no throw abaixo: formato inesperado é sempre indisponibilidade.
        }

        throw new ScreeningUnavailableException('O serviço de análise devolveu um parecer inválido.');
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (mixed $item): string => is_string($item) ? trim($item) : '', $value),
            fn (string $item): bool => $item !== '',
        ));
    }
}
