<?php

namespace App\Jobs;

use App\Models\ResultPin;
use App\Services\Notifications\StudentPushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendResultPinNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly string $resultPinId) {}

    public function handle(StudentPushNotificationService $notifications): void
    {
        $pin = ResultPin::query()
            ->with(['student.devices', 'session:id,name', 'term:id,name'])
            ->find($this->resultPinId);

        if (! $pin || ! $pin->sent_at || $pin->status === 'revoked') {
            return;
        }

        $sessionName = $pin->session?->name ?? 'the selected session';
        $termName = $pin->term?->name ?? 'the selected term';

        $notifications->deliver(
            $pin->student,
            'result_pin',
            'Result PIN Available',
            "Your {$termName} {$sessionName} result PIN is now available.",
            [
                'type' => 'result_pin',
                'pin_id' => (string) $pin->id,
                'session_id' => (string) $pin->session_id,
                'term_id' => (string) $pin->term_id,
                'route' => '/result-pins',
            ],
            "result_pin:{$pin->id}:sent:".substr(hash('sha256', (string) $pin->pin_code), 0, 16),
        );
    }
}
