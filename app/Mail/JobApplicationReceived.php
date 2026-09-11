<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\JobApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class JobApplicationReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public JobApplication $application) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Vaga] {$this->application->job_opening} — {$this->application->name}",
            replyTo: [$this->application->email],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.job-application');
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            // Anexa lendo do disco privado e renomeia para o nome original só na
            // entrega — o RH recebe "curriculo-joao.pdf", o disco guarda o ULID.
            Attachment::fromStorageDisk(
                config('montec.resume.disk'),
                $this->application->resume_path,
            )->as($this->application->resume_original_name)
                ->withMime($this->application->resume_mime),
        ];
    }
}
