<?php

namespace Qoraiche\MailEclipse\Tests\Fixtures\Mail;

use Illuminate\Mail\Mailable;

class LegacyMail extends Mailable
{
    public function __construct(public int $count = 0, public bool $enabled = false, public string $label = '') {}

    public function build()
    {
        return $this->subject('Legacy welcome')->view('fixture::welcome')->with('intro', 'Legacy content data');
    }
}
