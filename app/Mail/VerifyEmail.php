<?php

namespace App\Mail;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerifyEmail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $user,
        public string $verificationUrl,
        public CarbonInterface $expiresAt,
    ) {
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        $subject = sprintf('%s - Verify your email address', config('app.name'));

        return $this->subject($subject)
            ->view('emails.verify-school-email')
            ->with([
                'user' => $this->user,
                'verificationUrl' => $this->verificationUrl,
                'expiresAt' => $this->expiresAt,
            ]);
    }
}
