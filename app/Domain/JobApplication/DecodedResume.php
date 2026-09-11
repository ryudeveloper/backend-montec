<?php

declare(strict_types=1);

namespace App\Domain\JobApplication;

/**
 * Currículo já decodificado e aprovado.
 *
 * `originalName` serve só para exibição. O nome de arquivo em disco é gerado
 * (ver ResumeStorage) — usar o nome enviado pelo cliente para compor caminho é
 * travessia de diretório esperando acontecer.
 */
final readonly class DecodedResume
{
    public function __construct(
        public string $contents,
        public string $originalName,
        public string $mimeType,
        public string $extension,
        public int $bytes,
    ) {}
}
