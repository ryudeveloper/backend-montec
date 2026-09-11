<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Denúncia da ouvidoria.
 *
 * `$fillable` não inclui — e não pode incluir — nenhum identificador técnico.
 * Quando `is_anonymous` é true, `name` e `email` ficam nulos: ver
 * WhistleblowerReportService, que descarta os valores em vez de confiar na UI.
 */
class WhistleblowerReport extends Model
{
    use HasUlids;

    protected $fillable = [
        'is_anonymous',
        'name',
        'email',
        'subject',
        'description',
    ];

    protected function casts(): array
    {
        return ['is_anonymous' => 'boolean'];
    }
}
