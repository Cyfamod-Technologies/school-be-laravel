<?php

namespace App\Mail;

use App\Models\Agent;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AgentResetPassword extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Agent $agent,
        public string $resetUrl,
        public CarbonInterface $expiresAt,
    ) {
    }

    public function build(): self
    {
        $subject = sprintf('%s - Reset your agent password', config('app.name'));

        return $this->subject($subject)
            ->view('emails.reset-password')
            ->with([
                'user' => $this->agent,
                'displayName' => $this->agent->full_name,
                'resetUrl' => $this->resetUrl,
                'expiresAt' => $this->expiresAt,
            ]);
    }
}
