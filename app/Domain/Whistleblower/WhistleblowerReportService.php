<?php

declare(strict_types=1);

namespace App\Domain\Whistleblower;

use App\Models\WhistleblowerReport;

/**
 * Registro de denúncia.
 *
 * Quando a denúncia é anônima, nome e e-mail são DESCARTADOS aqui, não apenas
 * "não exibidos". O anonimato prometido na interface é compromisso legal: se um
 * cliente adulterado enviar identificação junto com o modo anônimo, o dado não
 * chega ao banco.
 *
 * Igualmente deliberado: este serviço não recebe Request, não consulta IP, não
 * escreve em log. Não há de onde derivar identificador.
 */
final class WhistleblowerReportService
{
    /**
     * @param  array{subject:string,description:string}  $attributes
     */
    public function record(
        array $attributes,
        bool $anonymous,
        ?string $name = null,
        ?string $email = null,
    ): WhistleblowerReport {
        return WhistleblowerReport::create([
            ...$attributes,
            'is_anonymous' => $anonymous,
            'name' => $anonymous ? null : $name,
            'email' => $anonymous ? null : $email,
        ]);
    }
}
