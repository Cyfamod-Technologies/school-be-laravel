<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StudentDeviceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $student = $this->resolveStudent($request);
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'device_id' => ['required', 'string', 'max:191'],
            'platform' => ['required', Rule::in(['android', 'ios'])],
            'app_version' => ['nullable', 'string', 'max:50'],
        ]);

        $device = DB::transaction(function () use ($student, $validated): StudentDevice {
            StudentDevice::query()
                ->where('device_id', $validated['device_id'])
                ->where('token', '!=', $validated['token'])
                ->delete();

            $device = StudentDevice::query()
                ->where('token', $validated['token'])
                ->lockForUpdate()
                ->first() ?? new StudentDevice;

            $device->fill([
                ...$validated,
                'student_id' => $student->id,
                'last_seen_at' => now(),
            ])->save();

            return $device;
        });

        return response()->json([
            'message' => 'Notification device registered.',
            'data' => $this->transform($device),
        ], $device->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request): JsonResponse
    {
        $student = $this->resolveStudent($request);
        $validated = $request->validate([
            'token' => ['nullable', 'required_without:device_id', 'string', 'max:512'],
            'device_id' => ['nullable', 'required_without:token', 'string', 'max:191'],
        ]);

        $deleted = StudentDevice::query()
            ->where('student_id', $student->id)
            ->when(isset($validated['token']), fn ($query) => $query->where('token', $validated['token']))
            ->when(isset($validated['device_id']), fn ($query) => $query->where('device_id', $validated['device_id']))
            ->delete();

        return response()->json([
            'message' => 'Notification device unregistered.',
            'deleted' => $deleted,
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

    private function transform(StudentDevice $device): array
    {
        return [
            'id' => $device->id,
            'device_id' => $device->device_id,
            'platform' => $device->platform,
            'app_version' => $device->app_version,
            'last_seen_at' => $device->last_seen_at?->toISOString(),
        ];
    }
}
