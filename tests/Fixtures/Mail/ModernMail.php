<?php

namespace Qoraiche\MailEclipse\Tests\Fixtures\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ModernMail extends Mailable
{
    public function __construct(public int $count = 0, public bool $enabled = false, public string $label = '') {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [new Address('customer@example.com')],
            cc: [new Address('cc@example.com')],
            bcc: [new Address('bcc@example.com')],
            subject: 'Welcome to your workspace',
            using: [static function ($message) { $message->addTo('callback@example.com'); }],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'fixture::welcome', text: 'fixture::plain', with: ['intro' => 'Modern content data']);
    }
}
