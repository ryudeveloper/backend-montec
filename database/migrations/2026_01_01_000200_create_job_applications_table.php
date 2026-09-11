<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Candidaturas do "Trabalhe Conosco".
 *
 * `resume_path` aponta para disco privado, fora do webroot, com nome gerado.
 * `resume_original_name` existe só para exibição no e-mail e no painel — nunca
 * é usado para compor caminho (CLAUDE.md §8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_applications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name', 120);
            $table->string('email', 180);
            $table->string('phone', 20);
            $table->string('job_opening', 120);
            $table->text('message')->nullable();
            $table->string('resume_path');
            $table->string('resume_original_name', 255);
            $table->string('resume_mime', 120);
            $table->unsignedInteger('resume_bytes');
            $table->timestamp('consent_accepted_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
