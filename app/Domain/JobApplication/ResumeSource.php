<?php

declare(strict_types=1);

namespace App\Domain\JobApplication;

use Illuminate\Http\UploadedFile;

/**
 * Origem do currículo, normalizada.
 *
 * Existem dois jeitos de o arquivo chegar — multipart (preferido) e base64 no
 * corpo JSON (contrato herdado). Ambos são reduzidos aqui a "bytes + nome
 * original", e daí em diante a validação é uma só: o ResumeDecoder fareja o MIME
 * no conteúdo, confere extensão e tamanho. Um caminho alternativo com validação
 * própria é como se cria uma porta dos fundos sem querer.
 */
final readonly class ResumeSource
{
    private function __construct(
        public string $contents,
        public string $originalName,
    ) {}

    public static function fromUploadedFile(UploadedFile $file): self
    {
        return new self(
            // getRealPath e não get(): o arquivo já está em disco temporário,
            // ler dali evita uma segunda cópia em memória.
            (string) file_get_contents((string) $file->getRealPath()),
            $file->getClientOriginalName(),
        );
    }

    /**
     * @throws InvalidResumeException
     */
    public static function fromBase64(string $data, string $originalName): self
    {
        // strict: true recusa caractere fora do alfabeto base64 em vez de
        // ignorá-lo em silêncio e produzir um arquivo corrompido.
        $decoded = base64_decode($data, true);

        if ($decoded === false) {
            throw new InvalidResumeException('Não foi possível ler o arquivo enviado.');
        }

        return new self($decoded, $originalName);
    }
}
