<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Mail\MailDriverGuard;
use App\Support\Mail\UnsafeMailDriverException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * O driver `log` escreve o corpo INTEIRO do e-mail em storage/logs.
 *
 * Isso significa nome, e-mail, telefone, o currículo e o conteúdo das denúncias
 * em texto puro num arquivo que fica no servidor, é lido por qualquer um com
 * acesso a ele e costuma acabar em backup e em ferramenta de observabilidade.
 *
 * Para a ouvidoria isso anula a confidencialidade prometida na interface. Não é
 * hipótese remota: basta alguém copiar um .env de desenvolvimento para produção.
 */
final class MailDriverGuardTest extends TestCase
{
    #[Test]
    public function em_producao_recusa_o_driver_log(): void
    {
        $this->expectException(UnsafeMailDriverException::class);
        $this->expectExceptionMessageMatches('/log/');

        (new MailDriverGuard)->assertSafe('production', 'log');
    }

    #[Test]
    public function em_producao_recusa_o_driver_array(): void
    {
        $this->expectException(UnsafeMailDriverException::class);

        (new MailDriverGuard)->assertSafe('production', 'array');
    }

    #[Test]
    public function em_producao_aceita_drivers_que_entregam_de_verdade(): void
    {
        $guard = new MailDriverGuard;

        foreach (['smtp', 'ses', 'postmark', 'resend', 'sendmail', 'failover'] as $driver) {
            $guard->assertSafe('production', $driver);
        }

        $this->assertTrue(true);
    }

    /** Em desenvolvimento e teste o driver `log` é justamente o que se quer. */
    #[Test]
    public function fora_de_producao_nao_interfere(): void
    {
        $guard = new MailDriverGuard;

        $guard->assertSafe('local', 'log');
        $guard->assertSafe('testing', 'array');

        $this->assertTrue(true);
    }
}
