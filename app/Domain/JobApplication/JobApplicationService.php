<?php

declare(strict_types=1);

namespace App\Domain\JobApplication;

use App\Models\JobApplication;
use Illuminate\Support\Facades\DB;
use Throwable;

final class JobApplicationService
{
    public function __construct(
        private ResumeDecoder $decoder,
        private ResumeStorage $storage,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws InvalidResumeException
     */
    public function record(array $attributes, ResumeSource $source): JobApplication
    {
        $resume = $this->decoder->validate($source);
        $path = $this->storage->store($resume);

        try {
            return DB::transaction(fn (): JobApplication => JobApplication::create([
                ...$attributes,
                'resume_path' => $path,
                'resume_original_name' => $resume->originalName,
                'resume_mime' => $resume->mimeType,
                'resume_bytes' => $resume->bytes,
            ]));
        } catch (Throwable $exception) {
            // Falhou o insert: o arquivo já gravado não pode ficar órfão no disco
            // — currículo é dado pessoal, e retenção sem registro é vazamento.
            $this->storage->delete($path);

            throw $exception;
        }
    }
}
