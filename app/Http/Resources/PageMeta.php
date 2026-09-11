<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Metadados de paginação em forma explícita.
 *
 * Não é o array `links` do Laravel de propósito: os rótulos dele trazem
 * entidades HTML (`&laquo;`) e o cliente precisaria injetar HTML de string para
 * exibi-los. Seguro enquanto a string é do framework — e deixa de ser no dia em
 * que alguém interpolar dado de usuário ali.
 */
final class PageMeta
{
    /**
     * @return array<string, mixed>
     */
    public static function from(LengthAwarePaginator $paginator): array
    {
        return [
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'prevUrl' => $paginator->previousPageUrl(),
            'nextUrl' => $paginator->nextPageUrl(),
        ];
    }
}
