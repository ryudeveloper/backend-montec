<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trilha de auditoria única do portal — currículo e denúncia.
 *
 * Substitui `resume_access_logs`. Ler uma denúncia é no mínimo tão sensível
 * quanto abrir um currículo, e duas tabelas paralelas acabariam divergindo: uma
 * ganharia campo que a outra não tem, e a auditoria ficaria desigual justamente
 * onde precisa ser uniforme.
 *
 * Atenção ao que este registro significa na ouvidoria: ele identifica quem LEU
 * a denúncia — funcionário identificado operando sistema interno — e nunca quem
 * a escreveu. O anonimato do denunciante não é tocado aqui, porque não há dado
 * dele a registrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_access_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            /** 'resume' ou 'report'. */
            $table->string('resource_type', 20);
            /*
             | Sem foreign key: o recurso pode ser expurgado por retenção, e a
             | trilha do acesso precisa sobreviver ao dado que foi acessado.
             */
            $table->string('resource_id', 40);
            $table->string('action', 20);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['resource_type', 'resource_id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::dropIfExists('resume_access_logs');
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_access_logs');
    }
};
