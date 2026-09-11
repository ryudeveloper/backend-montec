<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Parecer de aderência gerado por IA.
 *
 * Sem `updated_at`: um parecer não é editado. Gerar de novo substitui o registro
 * inteiro, com novo carimbo e novo modelo — assim sempre se sabe qual versão
 * produziu o que está na tela.
 */
class ResumeAssessment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'job_application_id',
        'score',
        'strengths',
        'gaps',
        'skills',
        'model',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'strengths' => 'array',
            'gaps' => 'array',
            'skills' => 'array',
            'generated_at' => 'immutable_datetime',
        ];
    }

    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }
}
