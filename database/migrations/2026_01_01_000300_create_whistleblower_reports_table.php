<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canal de ouvidoria.
 *
 * A tabela NÃO tem coluna de IP, user-agent, sessão ou qualquer fingerprint.
 * Isso é estrutural, não convencional: o anonimato prometido na interface é
 * compromisso legal, e o jeito de garanti-lo é não existir onde gravar o dado.
 * Há teste afirmando essa ausência — ela não pode reaparecer por descuido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whistleblower_reports', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->boolean('is_anonymous');
            /** Preenchidos apenas quando o denunciante escolhe se identificar. */
            $table->string('name', 120)->nullable();
            $table->string('email', 180)->nullable();
            $table->string('subject', 140);
            $table->text('description');
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whistleblower_reports');
    }
};
