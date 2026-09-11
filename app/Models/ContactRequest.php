<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ContactRequest extends Model
{
    use HasUlids;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'consent_accepted_at',
    ];

    protected function casts(): array
    {
        return ['consent_accepted_at' => 'immutable_datetime'];
    }
}
