<?php

declare(strict_types=1);

namespace App\Domain\JobApplication;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Valida o currículo, venha ele de multipart ou de base64.
 *
 * O cliente já valida tipo e tamanho, mas isso é UX: quem posta direto no
 * endpoint não passa por lá. Aqui tudo é reconferido, e o MIME sai do CONTEÚDO
 * do arquivo — o tipo declarado pelo cliente não é consultado em momento algum.
 */
final class ResumeDecoder
{
    /**
     * @param  list<string>  $allowedMimeTypes
     * @param  list<string>  $allowedExtensions
     */
    public function __construct(
        private int $maxBytes,
        private array $allowedMimeTypes,
        private array $allowedExtensions,
    ) {}

    public static function fromConfig(): self
    {
        /** @var array{max_bytes:int,mime_types:list<string>,extensions:list<string>} $config */
        $config = config('montec.resume');

        return new self($config['max_bytes'], $config['mime_types'], $config['extensions']);
    }

    /**
     * @throws InvalidResumeException
     */
    public function validate(ResumeSource $source): DecodedResume
    {
        $bytes = strlen($source->contents);

        if ($bytes === 0) {
            throw new InvalidResumeException('O arquivo do currículo está vazio.');
        }

        if ($bytes > $this->maxBytes) {
            $megabytes = (int) floor($this->maxBytes / 1024 / 1024);
            throw new InvalidResumeException("O currículo deve ter no máximo {$megabytes}MB.");
        }

        $extension = $this->extensionOf($source->originalName);
        $mimeType = $this->sniffMimeType($source->contents);

        if (! in_array($mimeType, $this->allowedMimeTypes, true)) {
            throw new InvalidResumeException('Formato não aceito. Envie PDF, DOC ou DOCX.');
        }

        // Extensão E conteúdo têm de concordar: um .exe renomeado para .pdf é
        // barrado pelo MIME, e um PDF renomeado para .php é barrado pela extensão.
        $this->assertMimeMatchesExtension($mimeType, $extension);

        return new DecodedResume(
            $source->contents,
            $source->originalName,
            $mimeType,
            $extension,
            $bytes,
        );
    }

    private function extensionOf(string $originalName): string
    {
        $extension = Str::lower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (! in_array($extension, $this->allowedExtensions, true)) {
            throw new InvalidResumeException('Formato não aceito. Envie PDF, DOC ou DOCX.');
        }

        return $extension;
    }

    private function sniffMimeType(string $contents): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'montec-resume-');

        if ($temporaryPath === false) {
            throw new InvalidResumeException('Não foi possível processar o arquivo enviado.');
        }

        try {
            file_put_contents($temporaryPath, $contents);

            return File::mimeType($temporaryPath) ?: 'application/octet-stream';
        } finally {
            @unlink($temporaryPath);
        }
    }

    private function assertMimeMatchesExtension(string $mimeType, string $extension): void
    {
        $compatible = match ($extension) {
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            // .docx é um container zip: dependendo da base de magic numbers o
            // finfo devolve o tipo OOXML ou simplesmente application/zip.
            'docx' => [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip',
            ],
            default => [],
        };

        if (! in_array($mimeType, $compatible, true)) {
            throw new InvalidResumeException(
                'O conteúdo do arquivo não corresponde à extensão informada.'
            );
        }
    }
}
