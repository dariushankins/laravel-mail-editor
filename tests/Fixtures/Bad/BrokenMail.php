<?php

namespace Qoraiche\MailEclipse\Tests\Fixtures\Bad;

use Illuminate\Mail\Mailable;

class BrokenMail extends Mailable
{
    public function build()
    {
        return $this->subject('Incomplete receipt template')->view('emails.receipt-missing');
    }
}
