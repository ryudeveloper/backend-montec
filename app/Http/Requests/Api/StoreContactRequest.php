<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Rules\BrazilianPhone;

/**
 * POST /api/contato — contrato em português, espelhando os limites do Zod do
 * frontend (CLAUDE.md §6). Limites iguais evitam a situação pior de todas:
 * passar no client e falhar no servidor sem mensagem útil.
 */
final class StoreContactRequest extends PublicFormRequest
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
            'assunto' => ['required', 'string', 'min:3', 'max:140'],
            'mensagem' => ['required', 'string', 'min:20', 'max:4000'],
            'consentimento' => ['sometimes', 'boolean'],
            ...$this->publicFormRules(),
        ];
    }

    /**
     * @return array{name:string,email:string,phone:string,subject:string,message:string,consent_accepted_at:?string}
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->string('nome')->trim()->value(),
            'email' => $this->string('email')->trim()->lower()->value(),
            'phone' => preg_replace('/\D/', '', $this->string('telefone')->value()) ?? '',
            'subject' => $this->string('assunto')->trim()->value(),
            'message' => $this->string('mensagem')->trim()->value(),
            'consent_accepted_at' => $this->consentAcceptedAt(),
        ];
    }
}
