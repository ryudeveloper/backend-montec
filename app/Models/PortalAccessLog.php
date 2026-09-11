<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de acesso a dado sensível do portal.
 *
 * Só `created_at`: trilha de auditoria não se atualiza. Registro alterável
 * depois não serve como trilha.
 */
class PortalAccessLog extends Model
{
    public const UPDATED_AT = null;

    public const RESOURCE_RESUME = 'resume';

    public const RESOURCE_REPORT = 'report';

    protected $fillable = ['user_id', 'resource_type', 'resource_id', 'action', 'ip_address'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
