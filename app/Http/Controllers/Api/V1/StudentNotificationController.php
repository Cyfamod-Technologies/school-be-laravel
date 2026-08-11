<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $student = $this->resolveStudent($request);
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'unread_only' => ['nullable', 'boolean'],
        ]);

        $query = $student->notifications()
            ->when($request->boolean('unread_only'), fn ($builder) => $builder->whereNull('read_at'))
            ->latest();
        $paginator = $query->paginate($validated['per_page'] ?? 20);
        $paginator->through(fn (StudentNotification $notification) => $this->transform($notification));

        return response()->json([
            'unread_count' => $student->notifications()->whereNull('read_at')->count(),
            'notifications' => $paginator,
        ]);
    }

    public function markRead(Request $request, StudentNotification $notification): JsonResponse
    {
        $student = $this->resolveStudent($request);
        abort_unless((string) $notification->student_id === (string) $student->id, 404);

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return response()->json(['data' => $this->transform($notification->refresh())]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $student = $this->resolveStudent($request);
        $updated = $student->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Notifications marked as read.',
            'updated' => $updated,
        ]);
    }

    private function resolveStudent(Request $request): Student
    {
        $student = $request->user('student');

        if ($student instanceof Student) {
            return $student;
        }

        abort(401, 'Unauthenticated.');
    }

    private function transform(StudentNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'body' => $notification->body,
            'data' => $notification->data ?? [],
            'sent_at' => $notification->sent_at?->toISOString(),
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
        ];
    }
}
