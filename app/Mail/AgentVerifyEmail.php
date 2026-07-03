<?php

namespace App\Mail;

use App\Models\Agent;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AgentVerifyEmail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Agent $agent,
        public string $verificationUrl,
        public CarbonInterface $expiresAt,
    ) {
    }

    public function build(): self
    {
        $subject = sprintf('%s - Verify your agent email', config('app.name'));

        return $this->subject($subject)
            ->view('emails.verify-agent-email')
            ->with([
                'agent' => $this->agent,
                'verificationUrl' => $this->verificationUrl,
                'expiresAt' => $this->expiresAt,
            ]);
    }
}
