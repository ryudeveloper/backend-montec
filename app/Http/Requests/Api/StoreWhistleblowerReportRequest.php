<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

/**
 * POST /api/ouvidoria
 *
 * `nome` e `email` são opcionais: ausentes = denúncia anônima. O contrato do
 * frontend simplesmente omite as chaves quando o denunciante escolhe anonimato
 * (ver toWhistleblowerPayload), e aqui a ausência é o que define o modo — não
 * há flag a ser falsificada.
 */
final class StoreWhistleblowerReportRequest extends PublicFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome' => ['nullable', 'string', 'min:2', 'max:120'],
            // `required_with` nos dois sentidos: identificar-se pela metade não
            // é identificação, e deixaria um dado pessoal órfão no banco.
            'email' => ['nullable', 'required_with:nome', 'email:rfc', 'max:180'],
            'assunto' => ['required', 'string', 'min:3', 'max:140'],
            'descricao' => ['required', 'string', 'min:40', 'max:5000'],
            ...$this->publicFormRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'email.required_with' => 'Informe o e-mail para se identificar.',
            'descricao.min' => 'Descreva com detalhes: data, local e pessoas envolvidas.',
        ];
    }

    public function isAnonymous(): bool
    {
        return $this->string('nome')->trim()->isEmpty();
    }

    /**
     * @return array{subject:string,description:string}
     */
    public function toAttributes(): array
    {
        return [
            'subject' => $this->string('assunto')->trim()->value(),
            'description' => $this->string('descricao')->trim()->value(),
        ];
    }

    public function reporterName(): ?string
    {
        $name = $this->string('nome')->trim()->value();

        return $name === '' ? null : $name;
    }

    public function reporterEmail(): ?string
    {
        $email = $this->string('email')->trim()->lower()->value();

        return $email === '' ? null : $email;
    }
}
