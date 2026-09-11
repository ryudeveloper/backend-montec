<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Domain\JobApplication\InvalidResumeException;
use App\Domain\JobApplication\ResumeSource;
use App\Rules\BrazilianPhone;
use Illuminate\Http\UploadedFile;

/**
 * POST /api/trabalhe-conosco
 *
 * Aceita o currículo de duas formas:
 *
 *  - `multipart/form-data` com o campo `curriculo` — PREFERIDO. O payload é o
 *    tamanho real do arquivo e o PHP grava em disco temporário em vez de
 *    carregar tudo em memória.
 *  - `curriculo: { data, name }` em base64 no corpo JSON — contrato herdado do
 *    site atual, mantido para não quebrar clientes em produção.
 *
 * As regras aqui só conferem forma. Decodificar, farejar MIME e medir tamanho é
 * do ResumeDecoder, e os dois caminhos passam pelo mesmo — validação duplicada é
 * como se cria uma porta dos fundos sem querer.
 */
final class StoreJobApplicationRequest extends PublicFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:180'],
            'telefone' => ['required', 'string', new BrazilianPhone],
            'vaga' => ['required', 'string', 'min:1', 'max:120'],
            'mensagem' => ['nullable', 'string', 'max:2000'],
            'curriculo' => ['required'],
            ...$this->resumeRules(),
            'consentimento' => ['sometimes', 'boolean'],
            ...$this->publicFormRules(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resumeRules(): array
    {
        if ($this->hasFile('curriculo')) {
            /** @var int $maxBytes */
            $maxBytes = config('montec.resume.max_bytes');

            return [
                // O teto em KB é a primeira barreira; o ResumeDecoder reconfere em
                // bytes sobre o conteúdo de fato lido.
                'curriculo' => ['required', 'file', 'max:'.(int) floor($maxBytes / 1024)],
            ];
        }

        /** @var int $maxBytes */
        $maxBytes = config('montec.resume.max_bytes');
        // Base64 infla ~4/3. Barra um corpo absurdo antes de alocar memória.
        $maxEncodedLength = (int) ceil($maxBytes * 4 / 3) + 64;

        return [
            'curriculo' => ['required', 'array'],
            'curriculo.data' => ['required', 'string', "max:{$maxEncodedLength}"],
            'curriculo.name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'curriculo.required' => 'Anexe seu currículo.',
            'curriculo.max' => 'O currículo deve ter no máximo 5MB.',
            'curriculo.data.required' => 'Anexe seu currículo.',
            'curriculo.data.max' => 'O currículo deve ter no máximo 5MB.',
        ];
    }

    /**
     * @return array{name:string,email:string,phone:string,job_opening:string,message:?string,consent_accepted_at:?string}
     */
    public function toAttributes(): array
    {
        $message = $this->string('mensagem')->trim()->value();

        return [
            'name' => $this->string('nome')->trim()->value(),
            'email' => $this->string('email')->trim()->lower()->value(),
            'phone' => preg_replace('/\D/', '', $this->string('telefone')->value()) ?? '',
            'job_opening' => $this->string('vaga')->trim()->value(),
            'message' => $message === '' ? null : $message,
            'consent_accepted_at' => $this->consentAcceptedAt(),
        ];
    }

    /**
     * @throws InvalidResumeException
     */
    public function resumeSource(): ResumeSource
    {
        $file = $this->file('curriculo');

        if ($file instanceof UploadedFile) {
            return ResumeSource::fromUploadedFile($file);
        }

        return ResumeSource::fromBase64(
            (string) $this->input('curriculo.data'),
            (string) $this->input('curriculo.name'),
        );
    }
}
