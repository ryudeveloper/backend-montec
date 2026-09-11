<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\FileSize;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FileSizeTest extends TestCase
{
    /**
     * Regressão: a tela mostrava "0kB" em todo currículo pequeno, porque o
     * arredondamento de bytes/1024 zera abaixo de 512 bytes. Tamanho que aparece
     * como zero parece arquivo corrompido.
     */
    #[Test]
    public function abaixo_de_um_kb_mostra_bytes_em_vez_de_zero(): void
    {
        $this->assertSame('90 B', FileSize::human(90));
        $this->assertSame('1 B', FileSize::human(1));
        $this->assertSame('1023 B', FileSize::human(1023));
    }

    #[Test]
    public function formata_kilobytes(): void
    {
        $this->assertSame('1 kB', FileSize::human(1024));
        $this->assertSame('412 kB', FileSize::human(422_000));
    }

    /** Separador decimal em vírgula: o portal é em português. */
    #[Test]
    public function formata_megabytes_com_uma_casa(): void
    {
        $this->assertSame('1,0 MB', FileSize::human(1024 * 1024));
        $this->assertSame('4,8 MB', FileSize::human(5_000_000));
    }

    #[Test]
    public function trata_zero_e_negativo_sem_quebrar(): void
    {
        $this->assertSame('0 B', FileSize::human(0));
        $this->assertSame('0 B', FileSize::human(-5));
    }
}
