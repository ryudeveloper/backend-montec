<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\WhistleblowerReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso para a caixa da ouvidoria.
 *
 * Sem `replyTo` quando anônima: apontar o reply para o denunciante seria
 * justamente o vazamento que o canal promete não cometer. E o assunto do e-mail
 * não carrega nome nem e-mail — assunto de mensagem aparece em notificação de
 * celular e em preview de caixa de entrada.
 */
final class WhistleblowerReportReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public WhistleblowerReport $report) {}

    public function envelope(): Envelope
    {
        $mode = $this->report->is_anonymous ? 'anônima' : 'identificada';

        return new Envelope(
            subject: "[Ouvidoria] Denúncia {$mode} — {$this->report->subject}",
            replyTo: $this->report->is_anonymous || $this->report->email === null
                ? []
                : [$this->report->email],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.whistleblower-report');
    }
}
