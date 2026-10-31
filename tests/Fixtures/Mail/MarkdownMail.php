<?php

namespace Qoraiche\MailEclipse\Tests\Fixtures\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MarkdownMail extends Mailable
{
    public function envelope(): Envelope { return new Envelope(subject: 'Your weekly digest'); }
    public function content(): Content { return new Content(markdown: 'fixture::markdown', with: ['title' => 'Weekly digest']); }
}
