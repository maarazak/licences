<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DynamicMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $htmlContent;
    public string $textContent;

    public function __construct(string $subject, string $htmlContent, string $textContent)
    {
        $this->subject = $subject;
        $this->htmlContent = $htmlContent;
        $this->textContent = $textContent;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
        );
    }

    public function build()
    {
        return $this->html($this->htmlContent)
            ->rawText($this->textContent);
    }
}
