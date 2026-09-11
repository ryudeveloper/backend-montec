<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitações de contato e orçamento.
 *
 * Sem coluna de IP ou user-agent de propósito: rate limit não precisa de
 * persistência e retenção mínima é exigência de LGPD. Guardar endereço de IE
 * junto do lead seria dado pessoal extra sem finalidade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name', 120);
            $table->string('email', 180);
            $table->string('phone', 20);
            $table->string('subject', 140);
            $table->text('message');
            /** Momento do aceite da Política de Privacidade — prova de consentimento. */
            $table->timestamp('consent_accepted_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_requests');
    }
};
