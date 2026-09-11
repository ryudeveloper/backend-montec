<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parecer de aderência gerado por IA.
 *
 * É SUGESTÃO, nunca decisão. O Art. 20 da LGPD dá ao candidato o direito de pedir
 * revisão de decisão tomada unicamente por processamento automatizado — então a
 * nota informa o RH e não reprova ninguém. Não há coluna de "aprovado" nem de
 * status: o sistema não toma essa decisão, e não ter onde gravá-la é o que
 * garante isso.
 *
 * `model` e `generated_at` ficam registrados porque o parecer é reproduzível:
 * saber qual modelo o produziu é parte de poder contestá-lo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resume_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('job_application_id')
                ->constrained()
                ->cascadeOnDelete();

            /** 0 a 100. Aderência à vaga, não qualidade da pessoa. */
            $table->unsignedTinyInteger('score');
            /** @var list<string> Pontos a favor. */
            $table->json('strengths');
            /** @var list<string> Pontos de atenção. */
            $table->json('gaps');
            /** @var list<string> Habilidades encontradas no texto. */
            $table->json('skills');
            $table->string('model', 60);
            $table->timestamp('generated_at');

            // Um parecer por candidatura: gerar de novo substitui o anterior.
            $table->unique('job_application_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resume_assessments');
    }
};
