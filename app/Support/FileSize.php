<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Tamanho de arquivo legível.
 *
 * Existe porque a tela mostrava "0kB" em todo currículo pequeno: dividir por
 * 1024 e arredondar zera abaixo de 512 bytes, e tamanho exibido como zero parece
 * arquivo corrompido. Abaixo de 1 kB o valor sai em bytes.
 */
final class FileSize
{
    public static function human(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        if ($bytes < 1024) {
            return "{$bytes} B";
        }

        $kilobytes = $bytes / 1024;

        if ($kilobytes < 1024) {
            return number_format($kilobytes, 0, ',', '.').' kB';
        }

        // Uma casa decimal só a partir de MB: é onde a diferença importa para
        // quem olha (2,4 MB e 2,9 MB são coisas distintas; 412 e 413 kB não).
        return number_format($kilobytes / 1024, 1, ',', '.').' MB';
    }
}
