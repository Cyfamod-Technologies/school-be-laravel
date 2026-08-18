<?php

namespace App\Jobs;

use App\Models\Attendance;
use App\Services\Notifications\StudentPushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAttendanceNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $attendanceId,
        public readonly int $revision,
    ) {}

    public function handle(StudentPushNotificationService $notifications): void
    {
        $attendance = Attendance::query()
            ->with(['student.devices'])
            ->find($this->attendanceId);

        // A deleted record or a newer correction invalidates this delayed job.
        if (! $attendance || (int) $attendance->notification_revision !== $this->revision) {
            return;
        }

        $status = ucfirst(str_replace('_', ' ', (string) $attendance->status));
        $date = $attendance->date?->format('j F Y') ?? 'the selected date';

        $notifications->deliver(
            $attendance->student,
            'attendance',
            'Attendance Updated',
            "You were marked {$status} for {$date}.",
            [
                'type' => 'attendance',
                'attendance_id' => (string) $attendance->id,
                'session_id' => (string) $attendance->session_id,
                'term_id' => (string) $attendance->term_id,
                'date' => $attendance->date?->toDateString() ?? '',
                'status' => (string) $attendance->status,
                'route' => '/attendance',
            ],
            "attendance:{$attendance->id}:revision:{$this->revision}",
        );
    }
}
