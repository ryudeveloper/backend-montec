<?php

declare(strict_types=1);

namespace App\Domain\JobApplication;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Guarda o currículo em disco privado, fora do webroot.
 *
 * O nome em disco é um ULID gerado aqui — o nome enviado pelo cliente nunca
 * compõe o caminho. Isso fecha travessia de diretório ("../../.env"), colisão
 * entre candidatos homônimos e execução acidental de arquivo servido.
 *
 * O disco NÃO é parâmetro de construtor: um parâmetro tipado como Filesystem faz
 * o container injetar o disco *default* em vez do configurado, e o currículo
 * acabava gravado fora do disco privado (e invisível para Storage::fake).
 * Resolver sempre pela config elimina a ambiguidade.
 */
final class ResumeStorage
{
    public function store(DecodedResume $resume): string
    {
        $path = sprintf('%s/%s.%s', date('Y/m'), (string) Str::ulid(), $resume->extension);

        $this->disk()->put($path, $resume->contents);

        return $path;
    }

    public function delete(string $path): void
    {
        $this->disk()->delete($path);
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('montec.resume.disk'));
    }
}
