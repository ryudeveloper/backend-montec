<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Papel do usuário no portal interno.
 *
 * Sem cadastro público: contas são criadas por `montec:create-user`. Um portal
 * que dá acesso a currículo — dado pessoal de gente que confiou na empresa —
 * não tem por que ter tela de registro aberta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // 'none' é o padrão de propósito: usuário recém-criado não enxerga
            // nada até alguém conceder o papel explicitamente.
            $table->string('role', 20)->default('none')->after('email');
            $table->timestamp('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['role', 'last_login_at']);
        });
    }
};
