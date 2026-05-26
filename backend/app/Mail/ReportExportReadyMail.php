<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReportExportReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $downloadUrl;
    public string $fileName;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $downloadUrl, string $fileName)
    {
        $this->user = $user;
        $this->downloadUrl = $downloadUrl;
        $this->fileName = $fileName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Task Report Export is Ready',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.report_export_ready',
        );
    }
}
