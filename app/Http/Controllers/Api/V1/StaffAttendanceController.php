<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Support\SimplePdfBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;

class StaffAttendanceController extends Controller
{
    private const STATUSES = ['present', 'absent', 'late', 'on_leave'];

    public function index(Request $request): JsonResponse
    {
        $query = $this->baseQuery($request);

        $perPage = min($request->integer('per_page', 50), 200);

        $paginator = $query
            ->orderByDesc('date')
            ->orderBy('staff_id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (StaffAttendance $attendance) => $this->transformAttendance($attendance));

        return response()->json($paginator);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'branch_name' => ['nullable', 'string', 'max:255'],
            'staff_id' => ['required_without:entries', 'uuid'],
            'status' => ['required_without:entries', Rule::in(self::STATUSES)],
            'metadata' => ['nullable', 'array'],
            'entries' => ['required_without:staff_id', 'array', 'min:1'],
            'entries.*.staff_id' => ['required', 'uuid'],
            'entries.*.status' => ['required', Rule::in(self::STATUSES)],
            'entries.*.branch_name' => ['nullable', 'string', 'max:255'],
            'entries.*.metadata' => ['sometimes', 'array'],
        ]);

        $entries = $this->normalizeEntries($validated);
        $staffIds = $entries->pluck('staff_id')->unique();

        if ($staffIds->isEmpty()) {
            return response()->json(['message' => 'No staff entries were provided.'], 422);
        }

        $user = $request->user();

        $staffMembers = Staff::query()
            ->where('school_id', $user->school_id)
            ->whereIn('id', $staffIds)
            ->get()
            ->keyBy('id');

        if ($staffMembers->count() !== $staffIds->count()) {
            $missing = $staffIds->diff($staffMembers->keys())->values();
            return response()->json([
                'message' => 'One or more staff records could not be found in your school.',
                'missing_staff_ids' => $missing,
            ], 422);
        }

        $date = Carbon::parse($validated['date'])->toDateString();
        $defaultBranch = $validated['branch_name'] ?? null;

        $created = 0;
        $updated = 0;

        DB::transaction(function () use (
            $entries,
            $staffMembers,
            $date,
            $defaultBranch,
            $user,
            &$created,
            &$updated
        ) {
            foreach ($entries as $entry) {
                $staff = $staffMembers->get($entry['staff_id']);

                $payload = [
                    'school_id' => $staff->school_id,
                    'status' => $entry['status'],
                    'branch_name' => $entry['branch_name'] ?? $defaultBranch,
                    'recorded_by' => $user->id,
                    'metadata' => $entry['metadata'] ?? null,
                ];

                $attendance = StaffAttendance::query()->updateOrCreate(
                    [
                        'staff_id' => $staff->id,
                        'date' => $date,
                    ],
                    $payload
                );

                if ($attendance->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            }
        });

        return response()->json([
            'message' => 'Staff attendance saved successfully.',
            'created' => $created,
            'updated' => $updated,
        ]);
    }

    public function update(StaffAttendance $staffAttendance, Request $request): JsonResponse
    {
        $this->authorizeAttendance($staffAttendance, $request);

        $validated = $request->validate([
            'date' => ['sometimes', 'date'],
            'status' => ['sometimes', Rule::in(self::STATUSES)],
            'branch_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);

        $staffAttendance->fill($validated);

        if (array_key_exists('date', $validated)) {
            $staffAttendance->date = Carbon::parse($validated['date'])->toDateString();
        }

        if ($staffAttendance->isDirty('date')) {
            $exists = StaffAttendance::query()
                ->where('staff_id', $staffAttendance->staff_id)
                ->whereDate('date', $staffAttendance->date)
                ->where('id', '!=', $staffAttendance->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'An attendance record already exists for this staff member on the specified date.',
                ], 422);
            }
        }

        if ($staffAttendance->isDirty()) {
            $staffAttendance->recorded_by = $request->user()->id;
            $staffAttendance->save();
        }

        $staffAttendance->loadMissing([
            'staff',
            'recorder',
        ]);

        return response()->json([
            'message' => 'Staff attendance updated.',
            'data' => $this->transformAttendance($staffAttendance),
        ]);
    }

    public function destroy(StaffAttendance $staffAttendance, Request $request): JsonResponse
    {
        $this->authorizeAttendance($staffAttendance, $request);
        $staffAttendance->delete();

        return response()->json([
            'message' => 'Staff attendance record deleted.',
        ]);
    }

    public function report(Request $request): JsonResponse
    {
        $query = $this->baseQuery($request);

        $statusBreakdown = (clone $query)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $dailyBreakdown = (clone $query)
            ->select([
                'date',
                DB::raw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present"),
                DB::raw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent"),
                DB::raw("SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late"),
                DB::raw("SUM(CASE WHEN status = 'on_leave' THEN 1 ELSE 0 END) as on_leave"),
            ])
            ->groupBy('date')
            ->orderBy('date')
            ->limit(120)
            ->get()
            ->map(fn ($row) => [
                'date' => Carbon::parse($row->date)->toDateString(),
                'present' => (int) $row->present,
                'absent' => (int) $row->absent,
                'late' => (int) $row->late,
                'on_leave' => (int) $row->on_leave,
            ]);

        $departmentBreakdown = (clone $query)
            ->join('staff', 'staff_attendances.staff_id', '=', 'staff.id')
            ->select('staff.role as department', DB::raw('COUNT(*) as total'))
            ->groupBy('staff.role')
            ->pluck('total', 'department')
            ->mapWithKeys(fn ($total, $department) => [$department ?? 'Unassigned' => $total])
            ->all();

        $summaryQuery = clone $query;

        $summary = [
            'total_records' => (clone $summaryQuery)->count(),
            'unique_staff' => (clone $summaryQuery)->distinct('staff_id')->count('staff_id'),
            'date_range' => [
                'from' => (clone $summaryQuery)->min('date'),
                'to' => (clone $summaryQuery)->max('date'),
            ],
        ];

        return response()->json([
            'summary' => $summary,
            'status_breakdown' => $statusBreakdown,
            'daily_breakdown' => $dailyBreakdown,
            'department_breakdown' => $departmentBreakdown,
        ]);
    }

    public function exportCsv(Request $request)
    {
        $records = $this->baseQuery($request)
            ->orderBy('date')
            ->orderBy('staff_id')
            ->limit(2000)
            ->get()
            ->map(fn (StaffAttendance $attendance) => $this->transformAttendance($attendance));

        $csvLines = [];
        $csvLines[] = implode(',', [
            'Date',
            'Staff Name',
            'Email',
            'Status',
            'Branch',
            'Department',
            'Recorded By',
        ]);

        foreach ($records as $record) {
            $csvLines[] = implode(',', [
                $record['date'],
                $this->escapeCsv($record['staff']['name'] ?? ''),
                $this->escapeCsv($record['staff']['email'] ?? ''),
                $record['status'],
                $this->escapeCsv($record['branch_name'] ?? ''),
                $this->escapeCsv($record['staff']['department'] ?? ''),
                $this->escapeCsv($record['recorded_by']['name'] ?? ''),
            ]);
        }

        return Response::make(implode("\n", $csvLines), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="staff-attendance-report.csv"',
        ]);
    }

    public function exportPdf(Request $request)
    {
        $records = $this->baseQuery($request)
            ->orderBy('date')
            ->orderBy('staff_id')
            ->limit(1000)
            ->get()
            ->map(fn (StaffAttendance $attendance) => $this->transformAttendance($attendance));

        $builder = new SimplePdfBuilder();
        $builder->addLine('Staff Attendance Report')
            ->addLine('Generated: ' . now()->toDateTimeString())
            ->addBlankLine();

        foreach ($records as $record) {
            $builder->addLine(sprintf(
                '%s | %s | %s | %s',
                $record['date'],
                $record['staff']['name'] ?? 'Unknown Staff',
                strtoupper($record['status']),
                $record['branch_name'] ?? 'N/A'
            ));
        }

        if ($records->isEmpty()) {
            $builder->addLine('No staff attendance records match the selected filters.');
        }

        return Response::make($builder->build(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="staff-attendance-report.pdf"',
        ]);
    }

    private function baseQuery(Request $request)
    {
        $user = $request->user();

        $query = StaffAttendance::query()
            ->with([
                'staff:id,full_name,email,phone,role,school_id',
                'recorder:id,name',
            ])
            ->where('staff_attendances.school_id', $user->school_id);

        return $this->applyFilters($query, $request);
    }

    private function applyFilters($query, Request $request)
    {
        if ($request->filled('date')) {
            $query->whereDate('date', Carbon::parse($request->input('date'))->toDateString());
        }

        if ($request->filled('from')) {
            $query->whereDate('date', '>=', Carbon::parse($request->input('from'))->toDateString());
        }

        if ($request->filled('to')) {
            $query->whereDate('date', '<=', Carbon::parse($request->input('to'))->toDateString());
        }

        if ($request->filled('status')) {
            $statuses = array_intersect(self::STATUSES, (array) $request->input('status'));
            if (! empty($statuses)) {
                $query->whereIn('status', $statuses);
            }
        }

        if ($request->filled('branch_name')) {
            $query->where('branch_name', $request->input('branch_name'));
        }

        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->input('staff_id'));
        }

        if ($request->filled('department')) {
            $department = $request->input('department');
            $query->whereHas('staff', fn ($staffQuery) => $staffQuery->where('role', $department));
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->whereHas('staff', function ($staffQuery) use ($search) {
                $staffQuery->where('full_name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%');
            });
        }

        return $query;
    }

    private function transformAttendance(StaffAttendance $attendance): array
    {
        $staff = $attendance->staff;

        return [
            'id' => $attendance->id,
            'date' => $attendance->date?->toDateString(),
            'status' => $attendance->status,
            'branch_name' => $attendance->branch_name,
            'metadata' => $attendance->metadata ?? [],
            'staff' => $staff ? [
                'id' => $staff->id,
                'name' => $staff->full_name,
                'email' => $staff->email,
                'phone' => $staff->phone,
                'department' => $staff->role,
            ] : null,
            'recorded_by' => $attendance->recorder ? [
                'id' => $attendance->recorder->id,
                'name' => $attendance->recorder->name,
            ] : null,
            'created_at' => optional($attendance->created_at)->toISOString(),
            'updated_at' => optional($attendance->updated_at)->toISOString(),
        ];
    }

    private function normalizeEntries(array $validated): Collection
    {
        if (! empty($validated['entries'])) {
            return collect($validated['entries'])->map(fn ($entry) => [
                'staff_id' => $entry['staff_id'],
                'status' => $entry['status'],
                'branch_name' => $entry['branch_name'] ?? null,
                'metadata' => $entry['metadata'] ?? null,
            ]);
        }

        return collect([[
            'staff_id' => $validated['staff_id'],
            'status' => $validated['status'],
            'branch_name' => $validated['branch_name'] ?? null,
            'metadata' => $validated['metadata'] ?? null,
        ]]);
    }

    private function authorizeAttendance(StaffAttendance $attendance, Request $request): void
    {
        $user = $request->user();

        if ($attendance->school_id !== $user->school_id) {
            abort(403, 'You are not authorized to modify this staff attendance record.');
        }
    }

    private function escapeCsv(?string $value): string
    {
        $value ??= '';
        $needsQuotes = str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n");

        $escaped = str_replace('"', '""', $value);

        return $needsQuotes ? '"' . $escaped . '"' : $escaped;
    }
}
