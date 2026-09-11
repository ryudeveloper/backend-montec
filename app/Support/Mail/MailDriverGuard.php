<?php

declare(strict_types=1);

namespace App\Support\Mail;

/**
 * Impede que produção rode com driver de e-mail que não entrega.
 *
 * O driver `log` grava o corpo INTEIRO da mensagem em storage/logs: nome,
 * e-mail, telefone, o currículo anexado e o conteúdo das denúncias, em texto
 * puro, num arquivo que fica no servidor, entra em backup e frequentemente é
 * enviado a ferramenta de observabilidade.
 *
 * Para a ouvidoria isso anula a confidencialidade prometida na interface. E não
 * é hipótese remota: basta alguém copiar um .env de desenvolvimento. Melhor
 * falhar no boot, alto e claro, do que descobrir depois que meses de denúncia
 * estavam legíveis em disco.
 */
final class MailDriverGuard
{
    /** Drivers que não entregam a mensagem — só registram ou descartam. */
    private const NON_DELIVERING = ['log', 'array', 'null'];

    public function assertSafe(string $environment, string $driver): void
    {
        if ($environment !== 'production') {
            return;
        }

        if (in_array($driver, self::NON_DELIVERING, true)) {
            throw new UnsafeMailDriverException(
                "MAIL_MAILER=\"{$driver}\" não pode ser usado em produção: ele não entrega a "
                .'mensagem e grava o conteúdo (currículo, denúncia, dados de contato) em texto '
                .'puro nos logs. Configure um driver de entrega real — smtp, ses, postmark ou resend.'
            );
        }
    }
}
