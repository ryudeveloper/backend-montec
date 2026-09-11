<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ContactRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class ContactRequestReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ContactRequest $contactRequest) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Site] {$this->contactRequest->subject} — {$this->contactRequest->name}",
            // replyTo com o e-mail do lead: responder direto da caixa comercial.
            replyTo: [$this->contactRequest->email],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.contact-request');
    }
}
