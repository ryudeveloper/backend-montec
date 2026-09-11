<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trilha de auditoria de acesso a currículo.
 *
 * Currículo é dado pessoal, e a LGPD pede responsabilização: é preciso saber
 * QUEM acessou o dado de QUEM e QUANDO. Sem isso, um vazamento interno não tem
 * como ser investigado.
 *
 * Aqui o IP É registrado, ao contrário da ouvidoria — e por motivo oposto: são
 * funcionários identificados operando um sistema interno, e o registro existe
 * justamente para responsabilizá-los. Na ouvidoria o anonimato é a promessa;
 * aqui a rastreabilidade é a obrigação.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resume_access_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Sem cascade: a candidatura pode ser expurgada por retenção, e a
            // trilha do acesso precisa sobreviver ao dado que foi acessado.
            $table->ulid('job_application_id')->nullable()->index();
            $table->string('action', 20);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resume_access_logs');
    }
};
