<?php

declare(strict_types=1);

namespace App\Domain\Screening;

use Smalot\PdfParser\Parser as PdfParser;
use Throwable;
use ZipArchive;

/**
 * Extrai o texto do currículo.
 *
 * PDF pelo smalot/pdfparser. DOCX é um zip com XML dentro — dá para ler sem
 * dependência nenhuma. DOC (o binário antigo do Word) não é suportado: o formato
 * exigiria uma biblioteca pesada, e é raro o bastante para não valer. Nesse caso
 * a triagem simplesmente não é oferecida, e o RH lê o arquivo como sempre leu.
 *
 * PDF só de imagem (currículo escaneado) devolve texto vazio. Isso é detectado e
 * comunicado — não adianta mandar folha em branco para a IA e receber um parecer
 * inventado sobre o nada.
 */
final class ResumeTextExtractor
{
    /** Abaixo disto o texto não sustenta um parecer honesto. */
    private const MIN_USEFUL_CHARS = 120;

    public function supports(string $extension): bool
    {
        return in_array($extension, ['pdf', 'docx'], true);
    }

    /**
     * @throws UnreadableResumeException
     */
    public function extract(string $absolutePath, string $extension): string
    {
        $text = match ($extension) {
            'pdf' => $this->fromPdf($absolutePath),
            'docx' => $this->fromDocx($absolutePath),
            default => throw new UnreadableResumeException(
                'Currículos em .doc não podem ser lidos automaticamente. Abra o arquivo para avaliar.'
            ),
        };

        $text = trim((string) preg_replace('/[ \t]+/u', ' ', $text));

        if (mb_strlen($text) < self::MIN_USEFUL_CHARS) {
            throw new UnreadableResumeException(
                'Não foi possível extrair texto deste arquivo — provavelmente é um PDF digitalizado (imagem). Abra o arquivo para avaliar.'
            );
        }

        return $text;
    }

    private function fromPdf(string $path): string
    {
        try {
            return (new PdfParser)->parseFile($path)->getText();
        } catch (Throwable $exception) {
            throw new UnreadableResumeException(
                'O PDF não pôde ser lido. Abra o arquivo para avaliar.',
                previous: $exception,
            );
        }
    }

    private function fromDocx(string $path): string
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new UnreadableResumeException('O arquivo .docx parece corrompido.');
        }

        try {
            $xml = $zip->getFromName('word/document.xml');
        } finally {
            $zip->close();
        }

        if ($xml === false) {
            throw new UnreadableResumeException('O arquivo .docx não tem o conteúdo esperado.');
        }

        // Quebra de parágrafo vira nova linha antes de as tags saírem, senão o
        // texto inteiro cola numa linha só e perde a estrutura de seções.
        $xml = (string) preg_replace('/<\/w:p>/', "\n", $xml);

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
