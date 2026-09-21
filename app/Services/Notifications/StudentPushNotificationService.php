<?php

namespace App\Services\Notifications;

use App\Models\Student;
use App\Models\StudentNotification;
use Throwable;

class StudentPushNotificationService
{
    public function __construct(private readonly FcmClient $fcm) {}

    public function deliver(
        Student $student,
        string $type,
        string $title,
        string $body,
        array $data,
        string $dedupeKey,
    ): StudentNotification {
        $notification = StudentNotification::query()->firstOrCreate(
            ['dedupe_key' => $dedupeKey],
            [
                'student_id' => $student->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => $data,
            ]
        );

        if (! $notification->wasRecentlyCreated) {
            if ($notification->sent_at || ! $notification->failed_at) {
                return $notification;
            }

            $notification->forceFill([
                'failed_at' => null,
                'failure_reason' => null,
            ])->save();
        }

        $devices = $student->devices()->get();
        $sent = 0;
        $errors = [];

        foreach ($devices as $device) {
            try {
                $result = $this->fcm->send($device->token, $title, $body, $data);
            } catch (Throwable $exception) {
                $result = ['sent' => false, 'invalid_token' => false, 'error' => $exception->getMessage()];
            }

            if ($result['sent']) {
                $sent++;
            } elseif ($result['invalid_token']) {
                $device->delete();
            }

            if ($result['error']) {
                $errors[] = $result['error'];
            }
        }

        if ($sent > 0) {
            $notification->forceFill(['sent_at' => now()])->save();
        } elseif ($devices->isNotEmpty()) {
            $failureReason = mb_substr(implode(' | ', array_unique($errors)), 0, 2000);
            $notification->forceFill([
                'failed_at' => now(),
                'failure_reason' => $failureReason,
            ])->save();

            throw new \RuntimeException($failureReason ?: 'Unable to deliver the student push notification.');
        }

        return $notification->refresh();
    }
}
