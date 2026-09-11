<?php

declare(strict_types=1);

namespace App\Support\Demo;

/**
 * Gera um PDF válido, com texto extraível.
 *
 * Um PDF "mínimo" escrito à mão, sem tabela xref, é recusado pelo parser — e foi
 * o que fez a demonstração falhar com "o PDF não pôde ser lido". Aqui os offsets
 * de cada objeto são calculados e a xref é montada de verdade.
 *
 * Só para dados de demonstração. Currículo real vem do candidato.
 */
final class PdfBuilder
{
    public static function withText(string $text): string
    {
        $stream = '';
        foreach (explode("\n", $text) as $index => $line) {
            $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
            $y = 800 - ($index * 15);
            $stream .= "BT /F1 10 Tf 40 {$y} Td ({$escaped}) Tj ET\n";
        }

        $objects = [
            '<</Type/Catalog/Pages 2 0 R>>',
            '<</Type/Pages/Kids[3 0 R]/Count 1>>',
            '<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]'
                .'/Resources<</Font<</F1 5 0 R>>>>/Contents 4 0 R>>',
            '<</Length '.strlen($stream).">>\nstream\n{$stream}endstream",
            '<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $body) {
            $number = $index + 1;
            $offsets[$number] = strlen($pdf);
            $pdf .= "{$number} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $count = count($objects) + 1;

        $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<</Size {$count}/Root 1 0 R>>\nstartxref\n{$xrefOffset}\n%%EOF\n";
    }
}
