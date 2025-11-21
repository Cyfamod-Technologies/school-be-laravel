<?php

namespace App\Mail;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPassword extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public User $user,
        public string $resetUrl,
        public CarbonInterface $expiresAt,
    ) {
    }

    public function build(): self
    {
        $subject = sprintf('%s - Reset your password', config('app.name'));

        return $this->subject($subject)
            ->view('emails.reset-password')
            ->with([
                'user' => $this->user,
                'resetUrl' => $this->resetUrl,
                'expiresAt' => $this->expiresAt,
            ]);
    }
}

