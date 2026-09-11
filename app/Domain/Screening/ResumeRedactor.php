<?php

declare(strict_types=1);

namespace App\Domain\Screening;

use Illuminate\Support\Str;

/**
 * Remove identificadores do texto do currículo antes de ele sair para a IA.
 *
 * Duas razões, nesta ordem:
 *
 * 1. VIÉS. Nome, endereço e idade carregam sinais de gênero, origem e faixa
 *    etária. Um modelo que os lê pode correlacionar o que não tem relação com a
 *    vaga. O RH tem esses dados na tela ao lado — o modelo não precisa deles
 *    para avaliar experiência em solda.
 * 2. MINIMIZAÇÃO (LGPD). Sai só o necessário para a finalidade.
 *
 * A redação é imperfeita por natureza: nenhuma expressão regular pega tudo. Por
 * isso ela começa pelo que o sistema JÁ CONHECE do candidato — nome, e-mail e
 * telefone vindos do banco — e só depois tenta padrões genéricos. É por isso
 * também que o parecer é consultivo: um resíduo que escape não decide nada
 * sozinho.
 */
final class ResumeRedactor
{
    private const MASK = '[removido]';

    /** Partículas de nome que, isoladas, não identificam ninguém. */
    private const NAME_PARTICLES = ['da', 'de', 'do', 'das', 'dos', 'e', 'di', 'del', 'van', 'von'];

    /** Abaixo disto, um pedaço de nome colide com palavra comum. */
    private const MIN_NAME_PART = 3;

    /**
     * O limite vem pelo construtor, não de `config()`: esta é uma função pura de
     * texto e não tem por que depender do container para ser exercitada.
     */
    public function __construct(private int $maxChars = 18000) {}

    public static function fromConfig(): self
    {
        return new self((int) config('montec.ai.max_input_chars', 18000));
    }

    /**
     * @param  array{name?: string, email?: string, phone?: string}  $known
     */
    public function redact(string $text, array $known = []): string
    {
        $text = $this->removeKnownIdentity($text, $known);
        $text = $this->removeGenericPatterns($text);
        $text = $this->collapseWhitespace($text);

        // Currículo longo não melhora o parecer, e texto sem teto vira custo sem
        // controle. Corta em caractere, não em byte — o texto é UTF-8.
        return Str::limit($text, $this->maxChars, '');
    }

    /**
     * @param  array{name?: string, email?: string, phone?: string}  $known
     */
    private function removeKnownIdentity(string $text, array $known): string
    {
        foreach (['email', 'phone'] as $field) {
            $value = trim((string) ($known[$field] ?? ''));
            if ($value !== '') {
                $text = str_ireplace($value, self::MASK, $text);
            }
        }

        $name = trim((string) ($known['name'] ?? ''));
        if ($name === '') {
            return $text;
        }

        // Nome inteiro primeiro: preserva o formato mais informativo da máscara.
        $text = str_ireplace($name, self::MASK, $text);

        // Depois cada parte — o nome reaparece fragmentado ("Sr. Pereira").
        foreach (preg_split('/\s+/u', $name) ?: [] as $part) {
            $normalized = Str::lower($part);

            if (mb_strlen($part) < self::MIN_NAME_PART) {
                continue;
            }

            if (in_array($normalized, self::NAME_PARTICLES, true)) {
                continue;
            }

            $quoted = preg_quote($part, '/');
            $text = (string) preg_replace("/\b{$quoted}\b/iu", self::MASK, $text);
        }

        return $text;
    }

    private function removeGenericPatterns(string $text): string
    {
        $patterns = [
            // E-mail
            '/[\w.+-]+@[\w-]+\.[\w.-]+/u',
            // Telefone brasileiro, com ou sem DDD e máscara
            '/\(?\d{2}\)?[\s.-]?9?\d{4}[\s.-]?\d{4}/u',
            // CPF
            '/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/u',
            // RG
            '/\b\d{2}\.?\d{3}\.?\d{3}-?[\dxX]\b/u',
            // Data completa — normalmente nascimento
            '/\b\d{1,2}\/\d{1,2}\/\d{4}\b/u',
            /*
             | Idade. O limite superior de 79 e a exigência da palavra "anos"
             | imediatamente após o número evitam apagar tempo de experiência —
             | "8 anos de experiência" precisa sobreviver, e sobrevive porque a
             | regra pede o contexto de idade explícito.
             */
            '/\b(?:[1-7]\d)\s*anos\b(?!\s+de\s+(?:experi|atua|traba))/iu',
            // URL de rede social
            '/\b(?:https?:\/\/)?(?:www\.)?(?:linkedin|facebook|instagram)\.com\/\S+/iu',
        ];

        return (string) preg_replace($patterns, self::MASK, $text);
    }

    private function collapseWhitespace(string $text): string
    {
        // Máscaras seguidas viram uma só: reduz ruído e economiza tokens.
        $text = (string) preg_replace('/(\['.'removido'.'\][\s,·|-]*){2,}/u', self::MASK.' ', $text);
        $text = (string) preg_replace('/[ \t]+/u', ' ', $text);
        $text = (string) preg_replace('/\n{3,}/u', "\n\n", $text);

        return trim($text);
    }
}
