<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class JobApplication extends Model
{
    use HasUlids;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'job_opening',
        'message',
        'resume_path',
        'resume_original_name',
        'resume_mime',
        'resume_bytes',
        'consent_accepted_at',
    ];

    /** Um parecer por candidatura: gerar de novo substitui o anterior. */
    public function assessment(): HasOne
    {
        return $this->hasOne(ResumeAssessment::class);
    }

    protected function casts(): array
    {
        return [
            'resume_bytes' => 'integer',
            'consent_accepted_at' => 'immutable_datetime',
        ];
    }
}
