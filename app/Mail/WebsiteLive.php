<?php

namespace App\Mail;

use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WebsiteLive extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public School $school) {}

    public function build(): self
    {
        return $this->subject('Your website is live!')
            ->view('emails.website-live')
            ->with(['school' => $this->school]);
    }
}
