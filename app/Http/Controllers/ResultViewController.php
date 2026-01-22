<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\ClassArm;
use App\Models\ClassSection;
use App\Models\ClassTeacher;
use App\Models\GradeRange;
use App\Models\GradingScale;
use App\Models\Result;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Session;
use App\Models\SkillRating;
use App\Models\Student;
use App\Models\Term;
use App\Models\TermSummary;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ResultViewController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/students/{student}/results/print",
     *     tags={"school-v1.4"},
     *     summary="Print a student's result",
     *     description="Renders the printable result sheet for the selected student, session, and term.",
     *     @OA\Parameter(
     *         name="student",
     *         in="path",
     *         required=true,
     *         description="Student ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="session_id",
     *         in="query",
     *         required=false,
     *         description="Session ID to print (defaults to student's current session)",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="term_id",
     *         in="query",
     *         required=false,
     *         description="Term ID to print (defaults to student's current term)",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="Printable HTML view"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function show(Request $request, Student $student)
    {
        // Temporarily allow all users to view/print single student results (permission check disabled).

        $user = $request->user();
        $role = strtolower((string) ($user->role ?? ''));
        $isAdmin = $user instanceof User
            && (in_array($role, ['admin', 'super_admin'], true) || $user->hasAnyRole(['admin', 'super_admin']));

        $data = $this->buildResultPageData(
            $student,
            $request->input('session_id'),
            $request->input('term_id'),
            $isAdmin ? null : optional($user?->school)->id
        );

        return view('result', $data);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/students/{student}/early-years-report/print",
     *     tags={"school-v1.4"},
     *     summary="Print a student's early years report",
     *     description="Renders the printable early years report for the selected student, session, and term.",
     *     @OA\Parameter(
     *         name="student",
     *         in="path",
     *         required=true,
     *         description="Student ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="session_id",
     *         in="query",
     *         required=false,
     *         description="Session ID to print (defaults to student's current session)",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="term_id",
     *         in="query",
     *         required=false,
     *         description="Term ID to print (defaults to student's current term)",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="Printable HTML view"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function earlyYearsReport(Request $request, Student $student)
    {
        $user = $request->user();
        $role = strtolower((string) ($user->role ?? ''));
        $isAdmin = $user instanceof User
            && (in_array($role, ['admin', 'super_admin'], true) || $user->hasAnyRole(['admin', 'super_admin']));

        $data = $this->buildEarlyYearsReportData(
            $student,
            $request->input('session_id'),
            $request->input('term_id'),
            $isAdmin ? null : optional($user?->school)->id
        );

        return view('early-years-report', $data);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/results/bulk/print",
     *     tags={"school-v1.4"},
     *     summary="Bulk print class results",
     *     description="Generates printable result sheets for a class (optionally filtered by arm/section).",
     *     @OA\Parameter(
     *         name="session_id",
     *         in="query",
     *         required=true,
     *         description="Session ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="term_id",
     *         in="query",
     *         required=true,
     *         description="Term ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="school_class_id",
     *         in="query",
     *         required=true,
     *         description="Class ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="class_arm_id",
     *         in="query",
     *         required=false,
     *         description="Arm ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="class_section_id",
     *         in="query",
     *         required=false,
     *         description="Section ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="Printable HTML view or JSON error"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function bulkPrint(Request $request)
    {
        // Temporarily allow all users to bulk print results (permission check disabled).

        $validated = $request->validate([
            'session_id' => ['required', 'uuid'],
            'term_id' => ['required', 'uuid'],
            'school_class_id' => ['required', 'uuid'],
            'class_arm_id' => ['nullable', 'uuid'],
            'class_section_id' => ['nullable', 'uuid'],
        ]);

        $schoolId = null;

        try {
            $schoolId = optional($request->user()->school)->id;
            if (! $schoolId && ! empty($validated['school_class_id'])) {
                // Fallback for admins/super admins without a linked school record: infer school from the class.
                $schoolId = SchoolClass::query()
                    ->whereKey($validated['school_class_id'])
                    ->value('school_id');
            }

            if (! $schoolId) {
                abort(403, 'You are not linked to any school.');
            }

            $students = Student::query()
                ->with([
                    'school',
                    'school_class',
                    'class_arm',
                    'class_section',
                    'parent',
                ])
                ->where('school_id', $schoolId)
                ->where('school_class_id', $validated['school_class_id'])
                ->when($validated['class_arm_id'] ?? null, fn ($query, $arm) => $query->where('class_arm_id', $arm))
                ->when($validated['class_section_id'] ?? null, fn ($query, $section) => $query->where('class_section_id', $section))
                ->whereNotIn('status', ['inactive', 'Inactive'])
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->orderBy('middle_name')
                ->get();

            $pages = $students
                ->map(function (Student $record) use ($validated, $schoolId) {
                    try {
                        return $this->buildResultPageData(
                            $record,
                            $validated['session_id'],
                            $validated['term_id'],
                            $schoolId
                        );
                    } catch (\Exception $e) {
                        // Skip students without results or with errors instead of failing entire bulk print
                        // Log the error for debugging but continue with other students
                        \Log::info("Skipped student {$record->id} in bulk print: " . $e->getMessage());
                        return null;
                    }
                })
                ->filter() // Remove null entries (students without results)
                ->values();

            $session = Session::query()
                ->where('school_id', $schoolId)
                ->find($validated['session_id']);

            $term = Term::query()
                ->where('school_id', $schoolId)
                ->find($validated['term_id']);

            $class = SchoolClass::query()
                ->where('school_id', $schoolId)
                ->find($validated['school_class_id']);

            // If no students have results, return an error with context
            if ($pages->isEmpty()) {
                $sessionName = $session?->name ?? 'Unknown Session';
                $termName = $term?->name ?? 'Unknown Term';
                $className = $class?->name ?? 'Unknown Class';

                throw new HttpResponseException(
                    response()->json([
                        'message' => "No results found for any students in {$className} for {$sessionName} - {$termName}. Please ensure results have been added before attempting to print. Total students checked: {$students->count()}",
                        'session_id' => $validated['session_id'],
                        'term_id' => $validated['term_id'],
                        'class_id' => $validated['school_class_id'],
                        'students_checked' => $students->count(),
                    ], 422)
                );
            }

            $classArm = null;
            if (! empty($validated['class_arm_id'])) {
                $classArm = ClassArm::query()
                    ->whereKey($validated['class_arm_id'])
                    ->whereHas('school_class', fn ($query) => $query->where('school_id', $schoolId))
                    ->first();
            }

            $classSection = null;
            if (! empty($validated['class_section_id'])) {
                $classSection = ClassSection::query()
                    ->whereKey($validated['class_section_id'])
                    ->whereHas('class_arm.school_class', fn ($query) => $query->where('school_id', $schoolId))
                    ->first();
            }

            return view('result-bulk', [
                'pages' => $pages,
                'filters' => [
                    'session' => $session?->name,
                    'term' => $term?->name,
                    'class' => $class?->name,
                    'class_arm' => $classArm?->name,
                    'class_section' => $classSection?->name,
                    'student_count' => $pages->count(), // Count of students WITH results
                    'total_students' => $students->count(), // Total students in class
                ],
                'generatedAt' => Carbon::now()->format('jS F Y, h:i A'),
                'documentTitle' => sprintf(
                    '%s - %s - %s Results',
                    $class?->name ?? 'Class',
                    $session?->name ?? 'Session',
                    $term?->name ?? 'Term'
                ),
            ]);
        } catch (HttpResponseException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $errorRef = (string) Str::uuid();
            \Log::error('Bulk result printing failed', [
                'error_ref' => $errorRef,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'user_id' => optional($request->user())->id,
                'school_id' => $schoolId,
                'filters' => $validated ?? [],
            ]);

            throw new HttpResponseException(
                response()->json([
                    'message' => 'Bulk result printing failed. Please contact support with code: ' . $errorRef,
                ], 500)
            );
        }
    }

    public function buildResultPageData(
        Student $student,
        ?string $requestedSessionId = null,
        ?string $requestedTermId = null,
        ?string $requestingSchoolId = null
    ) {
        $user = auth()->user();
        $role = strtolower((string) ($user->role ?? ''));
        $isAdmin = $user instanceof User
            && (in_array($role, ['admin', 'super_admin'], true) || $user->hasAnyRole(['admin', 'super_admin']));

        $student->loadMissing([
            'school',
            'school_class',
            'class_arm',
            'class_section',
            'parent',
        ]);

        if (! $isAdmin && $requestingSchoolId !== null && $requestingSchoolId !== $student->school_id) {
            abort(403, 'You are not allowed to view this student result.');
        }

        $sessionId = $this->normalizeContextId($requestedSessionId);
        $termId = $this->normalizeContextId($requestedTermId);

        // Prefer explicit session/term, then school's current context, then student's
        $school = $student->school;
        if (! $sessionId && $school && $school->current_session_id) {
            $sessionId = $this->normalizeContextId($school->current_session_id);
        }
        if (! $termId && $school && $school->current_term_id) {
            $termId = $this->normalizeContextId($school->current_term_id);
        }

        if (! $sessionId) {
            $sessionId = $this->normalizeContextId($student->current_session_id);
        }
        if (! $termId) {
            $termId = $this->normalizeContextId($student->current_term_id);
        }

        $session = $sessionId
            ? Session::query()
                ->where('school_id', $student->school_id)
                ->find($sessionId)
            : null;

        $term = $termId
            ? Term::query()
                ->where('school_id', $student->school_id)
                ->find($termId)
            : null;

        if ($term && (! $session || $term->session_id !== $session->id)) {
            $session = $term->session()->where('school_id', $student->school_id)->first();
        }

        if (! $session && $student->current_session_id) {
            $session = $student->session()->where('school_id', $student->school_id)->first();
        }

        if (! $term && $student->current_term_id) {
            $term = $student->term()->where('school_id', $student->school_id)->first();
        }

        $results = Result::query()
            ->where('student_id', $student->id)
            ->when($session, fn ($query) => $query->where('session_id', $session->id))
            ->when($term, fn ($query) => $query->where('term_id', $term->id))
            ->with([
                'subject:id,name,code',
                'assessment_component:id,name,label,order',
                'grade_range:id,grade_label,description,min_score,max_score',
            ])
            ->get();

        if ($results->isEmpty()) {
            $hasAnyResults = Result::query()
                ->where('student_id', $student->id)
                ->exists();

            if (! $hasAnyResults) {
                $studentName = trim(collect([
                    $student->first_name,
                    $student->middle_name,
                    $student->last_name,
                ])->filter()->implode(' '));

                $message = $studentName
                    ? "Results have not been added for {$studentName} in the selected session/term."
                    : 'Results have not been added for this student in the selected session/term.';

                throw new HttpResponseException(
                    response()->json([
                        'message' => $message,
                        'student_id' => $student->id,
                        'session_id' => $sessionId,
                        'term_id' => $termId,
                    ], 422)
                );
            }
        }

        $gradeRanges = $this->resolveGradeRanges($student->school_id, $session?->id);
        $componentColumns = $this->buildComponentColumns($results);
        $subjectStatisticsData = $this->computeSubjectStatistics($student, $session?->id, $term?->id, $results);
        $subjectStats = $subjectStatisticsData['subjects'];
        $subjectRows = $this->buildSubjectRows($results, $componentColumns, $gradeRanges, $subjectStats);

        $classSize = Student::query()
            ->where('school_id', $student->school_id)
            ->where('school_class_id', $student->school_class_id)
            ->when($student->class_arm_id, fn ($query) => $query->where('class_arm_id', $student->class_arm_id))
            ->when($student->class_section_id, fn ($query) => $query->where('class_section_id', $student->class_section_id))
            ->whereNotIn('status', ['inactive', 'Inactive'])
            ->count();

        $classSizeFromResults = $subjectStatisticsData['class_size'] ?? 0;
        if ($classSizeFromResults > 0) {
            $classSize = $classSizeFromResults;
        }

        $overallStats = $this->computeOverallStatistics(
            $subjectStats,
            $subjectStatisticsData['overall_totals'],
            $student,
            $classSize
        );

        $termSummary = TermSummary::query()
            ->where('student_id', $student->id)
            ->when($session, fn ($query) => $query->where('session_id', $session->id))
            ->when($term, fn ($query) => $query->where('term_id', $term->id))
            ->first();

        $attendanceCounts = $this->computeAttendanceCounts($student, $session, $term);
        $attendancePresent = $termSummary?->days_present ?? $attendanceCounts['present'] ?? 0;
        $attendanceAbsent = $termSummary?->days_absent ?? $attendanceCounts['absent'] ?? 0;

        $skillRatingsByCategory = SkillRating::query()
            ->where('student_id', $student->id)
            ->when($session, fn ($query) => $query->where('session_id', $session->id))
            ->when($term, fn ($query) => $query->where('term_id', $term->id))
            ->with([
                'skill_type:id,name,skill_category_id',
                'skill_type.skill_category:id,name',
            ])
            ->get()
            ->filter(fn (SkillRating $rating) => $rating->skill_type !== null)
            ->sortBy(fn (SkillRating $rating) => Str::lower(optional($rating->skill_type->skill_category)->name ?? ''), SORT_NATURAL, false)
            ->groupBy(function (SkillRating $rating) {
                return optional($rating->skill_type->skill_category)->name ?? 'Other Skills';
            })
            ->map(function ($items, $category) {
                return [
                    'category' => $category,
                    'skills' => $items
                        ->sortBy(fn (SkillRating $rating) => Str::lower($rating->skill_type->name ?? ''), SORT_NATURAL, false)
                        ->map(function (SkillRating $rating) {
                            return [
                                'skill' => $rating->skill_type->name ?? null,
                                'value' => $rating->rating_value,
                            ];
                        })
                        ->filter(fn (array $entry) => ! empty($entry['skill']))
                        ->values()
                        ->all(),
                ];
            })
            ->filter(fn (array $entry) => ! empty($entry['skills']))
            ->values()
            ->all();

        $classTeacher = $this->resolveClassTeacher($student, $session?->id, $term?->id);

        $nextTerm = null;
        if ($term && $session) {
            $nextTerm = Term::query()
                ->where('school_id', $student->school_id)
                ->where('session_id', $session->id)
                ->when($term->end_date, fn ($query) => $query->where('start_date', '>', $term->end_date))
                ->orderBy('start_date')
                ->first();
        }

        $sessionName = $session?->name ?? optional($student->session)->name;
        $termName = $term?->name ?? optional($student->term)->name;
        $studentName = trim(collect([$student->first_name, $student->middle_name, $student->last_name])->filter()->implode(' '));

        // Auto-generate fallback comments for teacher and principal
        $teacherComment = $termSummary?->overall_comment;
        $principalComment = $termSummary?->principal_comment;

        if ($teacherComment === null) {
            $teacherComment = $this->generateTeacherComment(
                $termSummary?->average_score ?? $overallStats['average'] ?? null
            );
        }

        if ($principalComment === null) {
            $principalComment = $this->generatePrincipalComment(
                $termSummary?->average_score ?? $overallStats['average'] ?? null
            );
        }

        $data = [
            'student' => $student,
            'schoolName' => optional($student->school)->name ?? 'School',
            'schoolAddress' => optional($student->school)->address,
            'schoolPhone' => optional($student->school)->phone,
            'schoolEmail' => optional($student->school)->email,
            'schoolLogoUrl' => $this->resolveMediaUrl(optional($student->school)->logo_url),
            'sessionName' => $sessionName,
            'termName' => $termName,
            'termStart' => $term?->start_date?->format('jS F Y'),
            'termEnd' => $term?->end_date?->format('jS F Y'),
            'nextTermStart' => $nextTerm?->start_date?->format('jS F Y'),
            'reportDate' => Carbon::now()->format('jS F Y'),
            'classSize' => $overallStats['class_size'] ?? $classSize,
            'documentTitle' => $studentName ? "{$studentName} | Result Slip" : 'Result Slip',
            'studentInfo' => [
                'name' => $studentName,
                'admission_no' => $student->admission_no,
                'gender' => $student->gender,
                'class' => optional($student->school_class)->name,
                'class_arm' => optional($student->class_arm)->name,
                'class_section' => optional($student->class_section)->name,
            ],
            'studentPhotoUrl' => $this->resolveMediaUrl($student->photo_url),
            'resultsColumns' => $componentColumns->map(function (array $column) {
                return [
                    'id' => $column['id'],
                    'label' => $column['label'],
                ];
            })->values()->all(),
            'resultsRows' => $subjectRows->all(),
            'termSummary' => $termSummary,
            'attendance' => [
                'present' => $attendancePresent,
                'absent' => $attendanceAbsent,
            ],
            'aggregate' => [
                'total_obtained' => $overallStats['total_obtained'],
                'total_possible' => $overallStats['total_possible'],
                'average' => $overallStats['average'],
                'position' => $overallStats['position'],
                'class_average' => $overallStats['class_average'],
                'final_grade' => $termSummary?->final_grade,
                'class_teacher_comment' => $teacherComment,
                'principal_comment' => $principalComment,
            ],
            'gradeRanges' => $gradeRanges
                ->map(function ($range) {
                    return [
                        'label' => $range->grade_label,
                        'min' => $range->min_score,
                        'max' => $range->max_score,
                        'remarks' => $range->description,
                    ];
                })
                ->values()
                ->all(),
            'skillRatingsByCategory' => $skillRatingsByCategory,
            'classTeacherName' => $classTeacher?->staff?->full_name,
            'principalName' => optional($student->school)->owner_name,
            'principalSignatureUrl' => optional($student->school)->signature_url,
            'resultPageSettings' => $this->resolveResultPageSettings($student->school),
        ];

        return $data;
    }

    public function buildEarlyYearsReportData(
        Student $student,
        ?string $requestedSessionId = null,
        ?string $requestedTermId = null,
        ?string $requestingSchoolId = null
    ) {
        $user = auth()->user();
        $role = strtolower((string) ($user->role ?? ''));
        $isAdmin = $user instanceof User
            && (in_array($role, ['admin', 'super_admin'], true) || $user->hasAnyRole(['admin', 'super_admin']));

        $student->loadMissing([
            'school',
            'school_class',
            'class_arm',
            'class_section',
            'parent',
        ]);

        if (! $isAdmin && $requestingSchoolId !== null && $requestingSchoolId !== $student->school_id) {
            abort(403, 'You are not allowed to view this student report.');
        }

        $sessionId = $this->normalizeContextId($requestedSessionId);
        $termId = $this->normalizeContextId($requestedTermId);

        $school = $student->school;
        if (! $sessionId && $school && $school->current_session_id) {
            $sessionId = $this->normalizeContextId($school->current_session_id);
        }
        if (! $termId && $school && $school->current_term_id) {
            $termId = $this->normalizeContextId($school->current_term_id);
        }

        if (! $sessionId) {
            $sessionId = $this->normalizeContextId($student->current_session_id);
        }
        if (! $termId) {
            $termId = $this->normalizeContextId($student->current_term_id);
        }

        $session = $sessionId
            ? Session::query()
                ->where('school_id', $student->school_id)
                ->find($sessionId)
            : null;

        $term = $termId
            ? Term::query()
                ->where('school_id', $student->school_id)
                ->find($termId)
            : null;

        if ($term && (! $session || $term->session_id !== $session->id)) {
            $session = $term->session()->where('school_id', $student->school_id)->first();
        }

        if (! $session && $student->current_session_id) {
            $session = $student->session()->where('school_id', $student->school_id)->first();
        }

        if (! $term && $student->current_term_id) {
            $term = $student->term()->where('school_id', $student->school_id)->first();
        }

        $termSummary = TermSummary::query()
            ->where('student_id', $student->id)
            ->when($session, fn ($query) => $query->where('session_id', $session->id))
            ->when($term, fn ($query) => $query->where('term_id', $term->id))
            ->first();

        $attendanceCounts = $this->computeAttendanceCounts($student, $session, $term);
        $attendancePresent = $termSummary?->days_present ?? $attendanceCounts['present'] ?? null;
        $attendanceAbsent = $termSummary?->days_absent ?? $attendanceCounts['absent'] ?? null;
        $schoolOpenedDays = $attendancePresent !== null && $attendanceAbsent !== null
            ? $attendancePresent + $attendanceAbsent
            : null;

        $classSize = Student::query()
            ->where('school_id', $student->school_id)
            ->where('school_class_id', $student->school_class_id)
            ->when($student->class_arm_id, fn ($query) => $query->where('class_arm_id', $student->class_arm_id))
            ->when($student->class_section_id, fn ($query) => $query->where('class_section_id', $student->class_section_id))
            ->whereNotIn('status', ['inactive', 'Inactive'])
            ->count();

        $skillRatingsByCategory = SkillRating::query()
            ->where('student_id', $student->id)
            ->when($session, fn ($query) => $query->where('session_id', $session->id))
            ->when($term, fn ($query) => $query->where('term_id', $term->id))
            ->with([
                'skill_type:id,name,description,skill_category_id',
                'skill_type.skill_category:id,name',
            ])
            ->get()
            ->filter(fn (SkillRating $rating) => $rating->skill_type !== null)
            ->sortBy(fn (SkillRating $rating) => Str::lower(optional($rating->skill_type->skill_category)->name ?? ''), SORT_NATURAL, false)
            ->groupBy(function (SkillRating $rating) {
                return optional($rating->skill_type->skill_category)->name ?? 'Other Skills';
            })
            ->map(function ($items, $category) {
                return [
                    'category' => $category,
                    'skills' => $items
                        ->sortBy(fn (SkillRating $rating) => Str::lower($rating->skill_type->name ?? ''), SORT_NATURAL, false)
                        ->map(function (SkillRating $rating) {
                            $type = $rating->skill_type;
                            $description = is_string($type?->description) ? trim($type->description) : '';
                            $skillLabel = $description !== '' ? $description : ($type->name ?? null);

                            return [
                                'skill' => $skillLabel,
                                'grade' => $this->formatSkillGrade($rating->rating_value),
                            ];
                        })
                        ->filter(fn (array $entry) => ! empty($entry['skill']))
                        ->values()
                        ->all(),
                ];
            })
            ->filter(fn (array $entry) => ! empty($entry['skills']))
            ->values()
            ->all();

        $classTeacher = $this->resolveClassTeacher($student, $session?->id, $term?->id);

        $nextTerm = null;
        if ($term && $session) {
            $nextTerm = Term::query()
                ->where('school_id', $student->school_id)
                ->where('session_id', $session->id)
                ->when($term->end_date, fn ($query) => $query->where('start_date', '>', $term->end_date))
                ->orderBy('start_date')
                ->first();
        }

        $sessionName = $session?->name ?? optional($student->session)->name;
        $termName = $term?->name ?? optional($student->term)->name;

        $teacherComment = $termSummary?->overall_comment;
        if ($teacherComment === null) {
            $teacherComment = $this->generateTeacherComment($termSummary?->average_score);
        }

        return [
            'student' => $student,
            'schoolName' => optional($student->school)->name ?? 'School',
            'schoolAddress' => optional($student->school)->address,
            'schoolPhone' => optional($student->school)->phone,
            'schoolEmail' => optional($student->school)->email,
            'schoolLogoUrl' => $this->resolveMediaUrl(optional($student->school)->logo_url),
            'sessionName' => $sessionName,
            'termName' => $termName,
            'termStart' => $term?->start_date?->format('jS F Y'),
            'termEnd' => $term?->end_date?->format('jS F Y'),
            'nextTermStart' => $nextTerm?->start_date?->format('jS F Y'),
            'reportDate' => Carbon::now()->format('jS F Y'),
            'classSize' => $classSize,
            'schoolOpenedDays' => $schoolOpenedDays,
            'studentInfo' => [
                'name' => trim(collect([$student->first_name, $student->middle_name, $student->last_name])->filter()->implode(' ')),
                'admission_no' => $student->admission_no,
                'gender' => $student->gender,
                'class' => optional($student->school_class)->name,
                'class_arm' => optional($student->class_arm)->name,
                'class_section' => optional($student->class_section)->name,
            ],
            'studentPhotoUrl' => $this->resolveMediaUrl($student->photo_url),
            'attendance' => [
                'present' => $attendancePresent,
                'absent' => $attendanceAbsent,
            ],
            'skillRatingsByCategory' => $skillRatingsByCategory,
            'teacherComment' => $teacherComment,
            'classTeacherName' => $classTeacher?->staff?->full_name,
            'directorSignatureUrl' => $this->resolveMediaUrl(optional($student->school)->signature_url),
            'reportTitle' => 'Early Years Report',
        ];
    }

    private function generateTeacherComment(?float $average): string
    {
        if ($average === null) {
            return 'This student is good.';
        }

        if ($average >= 85) {
            return 'Excellent performance. Keep it up.';
        }

        if ($average >= 70) {
            return 'Very good performance. Keep working hard.';
        }

        if ($average >= 55) {
            return 'Good effort. There is room for improvement.';
        }

        if ($average >= 45) {
            return 'Fair performance. Encourage more focus and hard work.';
        }

        return 'Below expectation. Close monitoring and extra support are recommended.';
    }

    private function generatePrincipalComment(?float $average): string
    {
        if ($average === null) {
            return 'This student is hardworking.';
        }

        if ($average >= 85) {
            return 'An outstanding result. The school is proud of this performance.';
        }

        if ($average >= 70) {
            return 'A very good result. Maintain this level of commitment.';
        }

        if ($average >= 55) {
            return 'A good result. Greater consistency will yield even better outcomes.';
        }

        if ($average >= 45) {
            return 'A fair result. Increased effort and diligence are advised.';
        }

        return 'Performance is below the expected standard. Parents and teachers should work together to support this learner.';
    }

    private function normalizeContextId(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function computeAttendanceCounts(Student $student, ?Session $session, ?Term $term): array
    {
        if (! $session || ! $term) {
            return ['present' => null, 'absent' => null];
        }

        $counts = Attendance::query()
            ->selectRaw("SUM(CASE WHEN status IN ('present', 'late') THEN 1 ELSE 0 END) as present_count")
            ->selectRaw("SUM(CASE WHEN status IN ('absent', 'excused') THEN 1 ELSE 0 END) as absent_count")
            ->where('student_id', $student->id)
            ->where('session_id', $session->id)
            ->where('term_id', $term->id)
            ->first();

        return [
            'present' => $counts ? (int) ($counts->present_count ?? 0) : null,
            'absent' => $counts ? (int) ($counts->absent_count ?? 0) : null,
        ];
    }

    private function formatSkillGrade(?int $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $number = (int) $value;
        if ($number < 1 || $number > 5) {
            return null;
        }

        return 'Q' . $number;
    }

    private function buildComponentColumns(Collection $results): Collection
    {
        return $results
            ->filter(fn (Result $result) => $result->assessment_component !== null)
            ->groupBy(fn (Result $result) => $result->assessment_component_id)
            ->map(function (Collection $items) {
                $component = $items->first()->assessment_component;

                return [
                    'id' => $component->id,
                    'label' => strtoupper($component->label ?? $component->name ?? 'Component'),
                    'order' => $component->order ?? PHP_INT_MAX,
                ];
            })
            ->values()
            ->sortBy('order')
            ->values();
    }

    private function buildSubjectRows(Collection $results, Collection $componentColumns, Collection $gradeRanges, Collection $subjectStats): Collection
    {
        return $results
            ->groupBy('subject_id')
            ->map(function (Collection $items) use ($componentColumns, $gradeRanges, $subjectStats) {
                $first = $items->first();
                $subjectName = $first?->subject?->name ?? 'Subject';
                $subjectId = $first?->subject_id;
                $componentScores = [];

                $summary = [
                    'total' => null,
                    'grade' => null,
                    'remarks' => null,
                    'position' => null,
                    'class_average' => null,
                    'lowest' => null,
                    'highest' => null,
                ];

                foreach ($items as $result) {
                    if ($result->assessment_component_id) {
                        $componentScores[$result->assessment_component_id] = $result->total_score;
                    } else {
                        $summary['total'] = $result->total_score;
                        $summary['grade'] = $result->grade_range->grade_label ?? null;
                        $summary['remarks'] = $result->grade_range->description ?? null;
                        $summary['position'] = $result->position_in_subject;
                        $summary['class_average'] = $result->class_average;
                        $summary['lowest'] = $result->lowest_in_class;
                        $summary['highest'] = $result->highest_in_class;
                    }
                }

                if ($summary['total'] === null) {
                    $summary['total'] = collect($componentScores)->sum();
                }

                $summary['grade'] = $this->resolveGradeLabel($summary['total'], $summary['grade'], $gradeRanges);
                $matchedRange = $this->resolveGradeRange($summary['total'], $summary['grade'], $gradeRanges);
                if ($matchedRange) {
                    if (! $summary['grade']) {
                        $summary['grade'] = $matchedRange->grade_label;
                    }
                    if ($summary['remarks'] === null || $summary['remarks'] === '') {
                        $summary['remarks'] = $matchedRange->description;
                    }
                }

                $componentValues = [];
                foreach ($componentColumns as $column) {
                    $componentValues[$column['id']] = $componentScores[$column['id']] ?? null;
                }

                $stats = $subjectId ? $subjectStats->get($subjectId) : null;
                if ($stats) {
                    if ($summary['position'] === null) {
                        $summary['position'] = $stats['position'];
                    }
                    if ($summary['class_average'] === null) {
                        $summary['class_average'] = $stats['average'];
                    }
                    if ($summary['lowest'] === null) {
                        $summary['lowest'] = $stats['lowest'];
                    }
                    if ($summary['highest'] === null) {
                        $summary['highest'] = $stats['highest'];
                    }
                }

                return [
                    'subject_name' => $subjectName,
                    'component_values' => $componentValues,
                    'total' => $summary['total'],
                    'grade' => $summary['grade'],
                    'remarks' => $summary['remarks'],
                    'position' => $summary['position'],
                    'class_average' => $summary['class_average'],
                    'lowest' => $summary['lowest'],
                    'highest' => $summary['highest'],
                ];
            })
            ->values()
            ->sortBy(fn ($row) => Str::lower($row['subject_name'] ?? ''), SORT_NATURAL)
            ->values();
    }

    private function computeSubjectStatistics(Student $student, ?string $sessionId, ?string $termId, Collection $results): array
    {
        if (! $sessionId || ! $termId || ! $student->school_class_id) {
            return [
                'subjects' => collect(),
                'overall_totals' => collect(),
                'class_size' => 0,
            ];
        }

        $subjectIds = $results
            ->pluck('subject_id')
            ->filter()
            ->unique()
            ->values();

        if ($subjectIds->isEmpty()) {
            return [
                'subjects' => collect(),
                'overall_totals' => collect(),
                'class_size' => 0,
            ];
        }

        $rows = Result::query()
            ->select(['subject_id', 'student_id', 'total_score', 'assessment_component_id'])
            ->whereIn('subject_id', $subjectIds)
            ->where('session_id', $sessionId)
            ->where('term_id', $termId)
            ->whereHas('student', function ($query) use ($student) {
                $query->where('school_class_id', $student->school_class_id)
                    ->when($student->class_arm_id, fn ($builder) => $builder->where('class_arm_id', $student->class_arm_id))
                    ->when($student->class_section_id, fn ($builder) => $builder->where('class_section_id', $student->class_section_id));
            })
            ->get();

        if ($rows->isEmpty()) {
            return [
                'subjects' => collect(),
                'overall_totals' => collect(),
                'class_size' => 0,
            ];
        }

        $overallTotals = [];

        $subjects = $rows->groupBy('subject_id')->map(function (Collection $subjectEntries) use ($student, &$overallTotals) {
            $totalsByStudent = $subjectEntries
                ->groupBy('student_id')
                ->map(function (Collection $entries) {
                    $overall = $entries->first(function ($row) {
                        return $row->assessment_component_id === null && $row->total_score !== null;
                    });

                    if ($overall) {
                        return (float) $overall->total_score;
                    }

                    return $entries
                        ->filter(fn ($row) => $row->assessment_component_id !== null)
                        ->pluck('total_score')
                        ->filter(fn ($value) => $value !== null)
                        ->sum();
                })
                ->filter(fn ($total) => $total !== null);

            if ($totalsByStudent->isEmpty()) {
                return [
                    'average' => null,
                    'highest' => null,
                    'lowest' => null,
                    'position' => null,
                    'total_possible' => null,
                    'total_obtained_by_student' => null,
                ];
            }

            foreach ($totalsByStudent as $studentId => $total) {
                $overallTotals[$studentId] = ($overallTotals[$studentId] ?? 0) + $total;
            }

            $studentScore = $totalsByStudent->get($student->id);

            $sortedDescending = $totalsByStudent
                ->values()
                ->map(fn ($score) => (float) $score)
                ->sortDesc()
                ->values();

            $position = null;
            if ($studentScore !== null) {
                $higherCount = $sortedDescending->filter(fn ($score) => $score > (float) $studentScore)->count();
                $position = $higherCount + 1;
            }

            return [
                'average' => round($sortedDescending->average(), 2),
                'highest' => round($sortedDescending->max(), 2),
                'lowest' => round($sortedDescending->min(), 2),
                'position' => $position,
                'total_possible' => $totalsByStudent->max(),
                'total_obtained_by_student' => $studentScore,
            ];
        });

        return [
            'subjects' => $subjects,
            'overall_totals' => collect($overallTotals),
            'class_size' => count($overallTotals),
        ];
    }

    private function computeOverallStatistics(Collection $subjectStats, Collection $overallTotals, Student $student, int $existingClassSize): array
    {
        $subjectCount = max(1, $subjectStats->count());

        $studentTotal = $overallTotals->get($student->id);

        $classAverage = $subjectStats
            ->pluck('average')
            ->filter(fn ($value) => $value !== null)
            ->average();

        $totalPossible = $subjectStats
            ->pluck('total_possible')
            ->filter(fn ($value) => $value !== null)
            ->sum();

        $classSize = max($existingClassSize, $overallTotals->count());

        $position = null;
        if ($studentTotal !== null) {
            $higherCount = $overallTotals->filter(fn ($total) => $total > $studentTotal)->count();
            $position = $higherCount + 1;
        }

        return [
            'total_obtained' => $studentTotal ?: null,
            'total_possible' => $totalPossible ?: null,
            'average' => ($studentTotal !== null && $subjectCount > 0) ? round($studentTotal / $subjectCount, 2) : null,
            'class_average' => $classAverage !== null ? round($classAverage, 2) : null,
            'position' => $position,
            'class_size' => $classSize ?: $existingClassSize,
        ];
    }

    private function resolveGradeRanges(string $schoolId, ?string $sessionId): Collection
    {
        $defaultQuery = GradingScale::query()
            ->where('school_id', $schoolId)
            ->with(['grade_ranges' => fn ($query) => $query->orderByDesc('min_score')]);

        $gradeScale = null;

        if ($sessionId) {
            $gradeScale = (clone $defaultQuery)
                ->where('session_id', $sessionId)
                ->first();
        }

        if (! $gradeScale) {
            $gradeScale = (clone $defaultQuery)
                ->whereNull('session_id')
                ->first();
        }

        if (! $gradeScale) {
            return collect();
        }

        return $gradeScale->grade_ranges->sortByDesc('min_score')->values();
    }

    private function resolveGradeRange(?float $score, ?string $gradeLabel, Collection $gradeRanges): ?GradeRange
    {
        if ($gradeLabel) {
            $normalized = Str::lower(trim($gradeLabel));
            $byLabel = $gradeRanges->first(function (GradeRange $range) use ($normalized) {
                return Str::lower(trim((string) $range->grade_label)) === $normalized;
            });

            if ($byLabel) {
                return $byLabel;
            }
        }

        if ($score === null) {
            return null;
        }

        return $gradeRanges->first(function (GradeRange $range) use ($score) {
            return $score >= $range->min_score && $score <= $range->max_score;
        });
    }

    private function resolveGradeLabel(?float $score, ?string $existing, Collection $gradeRanges): ?string
    {
        if ($existing) {
            return $existing;
        }

        $match = $this->resolveGradeRange($score, null, $gradeRanges);

        return $match?->grade_label ?? null;
    }

    private function resolveResultPageSettings(?School $school): array
    {
        return [
            'show_grade' => $school?->result_show_grade ?? true,
            'show_position' => $school?->result_show_position ?? true,
            'show_class_average' => $school?->result_show_class_average ?? true,
            'show_lowest' => $school?->result_show_lowest ?? true,
            'show_highest' => $school?->result_show_highest ?? true,
            'show_remarks' => $school?->result_show_remarks ?? true,
        ];
    }

    private function resolveClassTeacher(Student $student, ?string $sessionId, ?string $termId): ?ClassTeacher
    {
        return ClassTeacher::query()
            ->when($student->school_class_id, fn ($query, $classId) => $query->where('school_class_id', $classId))
            ->when($student->class_arm_id, fn ($query) => $query->where('class_arm_id', $student->class_arm_id))
            ->when($student->class_section_id, fn ($query) => $query->where('class_section_id', $student->class_section_id))
            ->when($sessionId, fn ($query) => $query->where('session_id', $sessionId))
            ->when($termId, fn ($query) => $query->where('term_id', $termId))
            ->whereHas('school_class', fn ($query) => $query->where('school_id', $student->school_id))
            ->with('staff:id,full_name')
            ->orderByDesc('created_at')
            ->first();
    }

    private function resolveMediaUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', 'data:'])) {
            return $path;
        }

        $trimmed = ltrim($path, '/');

        if (Str::startsWith($trimmed, 'storage/')) {
            return asset($trimmed);
        }

        return asset('storage/' . $trimmed);
    }
}
