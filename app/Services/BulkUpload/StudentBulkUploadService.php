<?php

namespace App\Services\BulkUpload;

use App\Exceptions\BulkUploadValidationException;
use App\Models\BulkUploadBatch;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolParent;
use App\Models\Session;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class StudentBulkUploadService
{
    private const BULK_TYPE = 'students';

    private const STATUS_OPTIONS = ['active', 'inactive', 'graduated', 'withdrawn'];

    private const GENDER_MAP = [
        'male' => 'M',
        'm' => 'M',
        'female' => 'F',
        'f' => 'F',
        'other' => 'O',
        'others' => 'O',
        'o' => 'O',
    ];

    public function generateTemplate(School $school): string
    {
        $columns = $this->buildColumnDefinitions();

        $sessions = $school->sessions()->orderBy('name')->get();
        $terms = $school->terms()->orderBy('name')->get();
        $classes = SchoolClass::query()
            ->where('school_id', $school->id)
            ->with(['class_arms.class_sections'])
            ->orderBy('name')
            ->get();

        $handle = fopen('php://temp', 'w+');

        // Introductory helper rows
        fputcsv($handle, ['# NOTE', 'Fill data starting from row 3. Row 2 contains examples and can be removed. Dates must use YYYY-MM-DD. Values are case-insensitive unless noted.']);
        fputcsv($handle, ['# STEP GUIDE', '1. Download template  2. Fill student & parent details  3. Upload file  4. Preview & confirm']);

        $headerRow = [];
        $exampleRow = [];
        foreach ($columns as $column) {
            $headerRow[] = $column['header'];
            $exampleRow[] = $column['example'] ?? '';
        }

        fputcsv($handle, $headerRow);
        fputcsv($handle, $exampleRow);

        // Blank spacer
        fputcsv($handle, ['']);

        // Reference data
        $this->writeReferenceRow($handle, 'Valid Genders', 'M (Male), F (Female), O (Others)');
        $this->writeReferenceRow($handle, 'Valid Statuses', implode(', ', array_map('ucwords', self::STATUS_OPTIONS)));
        $this->writeReferenceRow($handle, 'Sessions', $sessions->map(fn ($session) => "{$session->name} ({$session->id})")->implode(' | '));

        $this->writeReferenceRow(
            $handle,
            'Terms',
            $terms->map(fn ($term) => "{$term->name} (Session: {$sessions->firstWhere('id', $term->session_id)?->name})")->implode(' | ')
        );

        $classReference = $classes->map(function (SchoolClass $class) {
            $arms = $class->class_arms->map(function ($arm) {
                $sections = $arm->class_sections->pluck('name')->implode('; ');
                $sectionInfo = $sections ? " Sections: {$sections}" : '';
                return "{$arm->name}{$sectionInfo}";
            })->implode(' || ');

            return "{$class->name} ({$class->id}) Arms: {$arms}";
        })->implode(' | ');

        $this->writeReferenceRow($handle, 'Classes / Arms / Sections', $classReference ?: 'No classes available');

        rewind($handle);
        return stream_get_contents($handle) ?: '';
    }

    /**
     * @return array<string, mixed>
     *
     * @throws BulkUploadValidationException
     */
    public function validateAndPrepare(School $school, UploadedFile $file, User $user): array
    {
        $columns = $this->buildColumnDefinitions();
        $columnMap = collect($columns)->keyBy('key');

        $sessions = $school->sessions()->orderBy('name')->get();
        $terms = $school->terms()->orderBy('name')->get();
        $classes = SchoolClass::query()
            ->where('school_id', $school->id)
            ->with(['class_arms.class_sections'])
            ->get();

        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            throw new BulkUploadValidationException([], null, 'Unable to read the uploaded file.');
        }

        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);
            throw new BulkUploadValidationException([], null, 'The uploaded file is empty or unreadable.');
        }

        $normalizedHeader = $this->normalizeHeaderRow($header);
        $missingColumns = [];
        foreach ($columns as $definition) {
            if ($definition['required'] && ! array_key_exists($definition['key'], $normalizedHeader)) {
                $missingColumns[] = $definition['header'];
            }
        }

        if (! empty($missingColumns)) {
            fclose($handle);
            throw new BulkUploadValidationException([], null, 'The uploaded file is missing required columns: ' . implode(', ', $missingColumns));
        }

        $rowNumber = 1;
        $preparedRows = [];
        $errors = [];

        $inFileComposite = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($this->isSkippableRow($row)) {
                continue;
            }

            $rowData = $this->mapRowToData($row, $normalizedHeader, $columnMap);
            [$rowPrepared, $rowErrors] = $this->validateRow(
                $rowNumber,
                $rowData,
                $columnMap,
                $sessions,
                $terms,
                $classes,
                $school,
                $inFileComposite
            );

            if (! empty($rowErrors)) {
                array_push($errors, ...$rowErrors);
                continue;
            }

            $preparedRows[] = $rowPrepared;
        }

        fclose($handle);

        if (count($preparedRows) === 0) {
            $errorCsv = $this->buildErrorCsv([], $columns);
            throw new BulkUploadValidationException(
                $errors ?: [['row' => '-', 'column' => '-', 'message' => 'No valid rows found in the file.']],
                $errorCsv
            );
        }

        if (! empty($errors)) {
            $errorCsv = $this->buildErrorCsv($errors, $columns, $preparedRows);
            throw new BulkUploadValidationException($errors, $errorCsv);
        }

        $batch = BulkUploadBatch::create([
            'school_id' => $school->id,
            'user_id' => $user->id,
            'type' => self::BULK_TYPE,
            'status' => 'pending',
            'total_rows' => count($preparedRows),
            'payload' => [
                'rows' => $preparedRows,
                'columns' => $columns,
            ],
            'meta' => [
                'filename' => $file->getClientOriginalName(),
                'filesize' => $file->getSize(),
            ],
            'expires_at' => now()->addHours(6),
        ]);

        $previewRows = collect($preparedRows)
            ->take(10)
            ->map(function (array $row) use ($sessions, $terms, $classes) {
                $class = $classes->firstWhere('id', $row['student']['school_class_id']);
                $arm = $class?->class_arms->firstWhere('id', $row['student']['class_arm_id']);
                $section = $arm?->class_sections->firstWhere('id', $row['student']['class_section_id']);
                $parent = is_array($row['parent'] ?? null) ? $row['parent'] : [];
                return [
                    'name' => trim("{$row['student']['first_name']} {$row['student']['last_name']}"),
                    'gender' => $row['student']['gender'],
                    'admission_no' => $row['student']['admission_no'] ?: 'Auto-generated',
                    'session' => optional($sessions->firstWhere('id', $row['student']['current_session_id']))->name,
                    'term' => optional($terms->firstWhere('id', $row['student']['current_term_id']))->name,
                    'class' => $class?->name,
                    'class_arm' => $arm?->name,
                    'class_section' => $section?->name,
                    'parent_email' => $parent['email'] ?? '—',
                ];
            })
            ->values();

        return [
            'batch' => $batch,
            'summary' => [
                'total_rows' => count($preparedRows),
                'sessions' => collect($preparedRows)->pluck('student.current_session_id')->unique()->count(),
                'classes' => collect($preparedRows)->pluck('student.school_class_id')->unique()->count(),
            ],
            'preview_rows' => $previewRows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function commit(BulkUploadBatch $batch): array
    {
        if ($batch->type !== self::BULK_TYPE) {
            throw new \InvalidArgumentException('Invalid batch type supplied.');
        }

        if ($batch->status !== 'pending') {
            throw new \RuntimeException('This batch has already been processed.');
        }

        if ($batch->expires_at && now()->greaterThan($batch->expires_at)) {
            throw new \RuntimeException('This batch has expired. Please re-upload the file.');
        }

        $payload = $batch->payload ?? [];
        $rows = collect($payload['rows'] ?? []);
        if ($rows->isEmpty()) {
            throw new \RuntimeException('Bulk upload batch payload is empty.');
        }

        $school = $batch->school()->firstOrFail();
        $user = $batch->user()->firstOrFail();

        $createdStudents = 0;
        $createdParents = 0;

        DB::transaction(function () use (&$createdStudents, &$createdParents, $rows, $school, $user, $batch) {
            foreach ($rows as $row) {
                $parent = null;
                if (is_array($row['parent'] ?? null) && ! empty($row['parent']['email'])) {
                    $parent = $this->resolveParent($school, $row['parent'], $createdParents);
                }

                $studentData = $row['student'];
                $studentData['id'] = (string) Str::uuid();
                $studentData['school_id'] = $school->id;
                $studentData['parent_id'] = $parent?->id;
                $studentData['status'] = strtolower($studentData['status']);
                $session = Session::findOrFail($studentData['current_session_id']);
                $studentData['admission_no'] = Student::generateAdmissionNumber($school, $session);

                $student = Student::create($studentData);
                $createdStudents++;

                if (! empty($studentData['class_section_id'])) {
                    StudentEnrollment::create([
                        'id' => (string) Str::uuid(),
                        'student_id' => $student->id,
                        'class_section_id' => $studentData['class_section_id'],
                        'session_id' => $studentData['current_session_id'],
                        'term_id' => $studentData['current_term_id'],
                    ]);
                }
            }

            $batch->update([
                'status' => 'processed',
                'payload' => null,
                'meta' => array_merge($batch->meta ?? [], [
                    'processed_at' => now()->toIso8601String(),
                ]),
            ]);
        });

        return [
            'processed' => $createdStudents,
            'parents_created' => $createdParents,
            'failed' => 0,
        ];
    }

    private function buildColumnDefinitions(): array
    {
        $student = new Student();
        $studentFillable = collect($student->getFillable());

        $baseColumns = collect([
            [
                'key' => 'student.admission_no',
                'header' => 'Admission Number (Auto-generated)',
                'required' => false,
                'example' => 'NC001-2024/2025/1',
            ],
            [
                'key' => 'student.first_name',
                'header' => 'First Name',
                'required' => true,
                'example' => 'Ada',
            ],
            [
                'key' => 'student.middle_name',
                'header' => 'Middle Name',
                'required' => false,
                'example' => '',
            ],
            [
                'key' => 'student.last_name',
                'header' => 'Last Name',
                'required' => true,
                'example' => 'Obi',
            ],
            [
                'key' => 'student.gender',
                'header' => 'Gender (M/F/O)',
                'required' => true,
                'example' => 'F',
            ],
            [
                'key' => 'student.date_of_birth',
                'header' => 'Date of Birth (YYYY-MM-DD)',
                'required' => true,
                'example' => '2014-05-16',
            ],
            [
                'key' => 'student.admission_date',
                'header' => 'Admission Date (YYYY-MM-DD)',
                'required' => true,
                'example' => now()->toDateString(),
            ],
            [
                'key' => 'student.status',
                'header' => 'Status (active/inactive/graduated/withdrawn)',
                'required' => true,
                'example' => 'active',
            ],
            [
                'key' => 'student.nationality',
                'header' => 'Student Nationality',
                'required' => false,
                'example' => 'Nigerian',
            ],
            [
                'key' => 'student.state_of_origin',
                'header' => 'Student State of Origin',
                'required' => false,
                'example' => 'Enugu',
            ],
            [
                'key' => 'student.lga_of_origin',
                'header' => 'Student LGA',
                'required' => false,
                'example' => 'Nsukka',
            ],
            [
                'key' => 'student.house',
                'header' => 'House',
                'required' => false,
                'example' => 'Blue',
            ],
            [
                'key' => 'student.club',
                'header' => 'Club',
                'required' => false,
                'example' => 'Science',
            ],
            [
                'key' => 'student.address',
                'header' => 'Student Address',
                'required' => false,
                'example' => '12 Unity Close',
            ],
            [
                'key' => 'student.medical_information',
                'header' => 'Medical Information',
                'required' => false,
                'example' => 'Asthmatic - inhaler required',
            ],
            [
                'key' => 'student.current_session_id',
                'header' => 'Session (Name or ID)',
                'required' => true,
                'example' => '2025/2026',
            ],
            [
                'key' => 'student.current_term_id',
                'header' => 'Term (Name or ID)',
                'required' => true,
                'example' => 'First Term',
            ],
            [
                'key' => 'student.school_class_id',
                'header' => 'Class (Name or ID)',
                'required' => true,
                'example' => 'Grade 5',
            ],
            [
                'key' => 'student.class_arm_id',
                'header' => 'Class Arm (Name or ID)',
                'required' => true,
                'example' => 'Arm A',
            ],
            [
                'key' => 'student.class_section_id',
                'header' => 'Class Section (Name or ID)',
                'required' => false,
                'example' => '',
            ],
            [
                'key' => 'parent.first_name',
                'header' => 'Parent First Name',
                'required' => false,
                'example' => 'Grace',
            ],
            [
                'key' => 'parent.last_name',
                'header' => 'Parent Last Name',
                'required' => false,
                'example' => 'Williams',
            ],
            [
                'key' => 'parent.email',
                'header' => 'Parent Email',
                'required' => false,
                'example' => 'parent@example.com',
            ],
            [
                'key' => 'parent.phone',
                'header' => 'Parent Phone',
                'required' => false,
                'example' => '08012345678',
            ],
            [
                'key' => 'parent.address',
                'header' => 'Parent Address',
                'required' => false,
                'example' => '45 Market Street',
            ],
            [
                'key' => 'parent.occupation',
                'header' => 'Parent Occupation',
                'required' => false,
                'example' => 'Engineer',
            ],
            [
                'key' => 'parent.nationality',
                'header' => 'Parent Nationality',
                'required' => false,
                'example' => 'Nigerian',
            ],
            [
                'key' => 'parent.state_of_origin',
                'header' => 'Parent State of Origin',
                'required' => false,
                'example' => 'Lagos',
            ],
            [
                'key' => 'parent.local_government_area',
                'header' => 'Parent LGA',
                'required' => false,
                'example' => 'Ikeja',
            ],
        ]);

        // Append any remaining fillable student fields not explicitly defined
        $explicitStudentFields = $baseColumns
            ->filter(fn ($column) => str_starts_with($column['key'], 'student.'))
            ->map(fn ($column) => Str::after($column['key'], 'student.'))
            ->values()
            ->all();

        $remainingFields = $studentFillable
            ->unique()
            ->reject(fn ($field) => in_array($field, array_merge([
                'school_id',
                'parent_id',
                'photo_url',
                'blood_group_id',
            ], $explicitStudentFields), true))
            ->map(function ($field) {
                return [
                    'key' => "student.{$field}",
                    'header' => Str::headline($field),
                    'required' => false,
                    'example' => '',
                ];
            });

        return $baseColumns
            ->concat($remainingFields)
            ->map(function (array $column) {
                $column['header_key'] = $this->normalizeHeaderValue($column['header']);
                return $column;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $row
     */
    private function isSkippableRow(array $row): bool
    {
        $firstValue = trim($row[0] ?? '');

        if ($firstValue === '' && count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
            return true;
        }

        if (Str::startsWith($firstValue, '#')) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<int, string>  $header
     *
     * @return array<string, int>
     */
    private function normalizeHeaderRow(array $header): array
    {
        $normalized = [];
        foreach ($header as $index => $value) {
            $key = $this->normalizeHeaderValue((string) $value);
            if ($key === '') {
                continue;
            }

            $normalized[$key] = $index;
        }

        return $normalized;
    }

    /**
     * @param  array<int, string>  $row
     * @param  array<string, int>  $headerIndex
     * @param  Collection<string, array<string, mixed>>  $columns
     *
     * @return array<string, string|null>
     */
    private function mapRowToData(array $row, array $headerIndex, Collection $columns): array
    {
        $mapped = [];

        foreach ($columns as $definition) {
            $key = $definition['key'];
            $value = null;

            $headerKey = $definition['header_key'] ?? null;

            if ($headerKey && array_key_exists($headerKey, $headerIndex)) {
                $value = $row[$headerIndex[$headerKey]] ?? null;
            } else {
                $legacyKey = Str::replace('.', '_', $key);
                if (array_key_exists($legacyKey, $headerIndex)) {
                    $value = $row[$headerIndex[$legacyKey]] ?? null;
                }
            }

            $mapped[$key] = $value !== null ? trim((string) $value) : null;
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $rowData
     * @param  Collection<string, array<string, mixed>>  $columns
     * @param  EloquentCollection<int, \App\Models\Session>  $sessions
     * @param  EloquentCollection<int, \App\Models\Term>  $terms
     * @param  Collection<int, SchoolClass>  $classes
     * @param  array<int, string>  $inFileComposite
     *
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    private function validateRow(
        int $rowNumber,
        array $rowData,
        Collection $columns,
        EloquentCollection $sessions,
        EloquentCollection $terms,
        Collection $classes,
        School $school,
        array &$inFileComposite
    ): array {
        $errors = [];

        $studentData = [];
        $parentData = [];

        $getValue = function (string $columnKey, bool $required = false) use ($rowData, $columns, $rowNumber, &$errors) {
            $value = $rowData[$columnKey] ?? null;
            if ($required && ($value === null || $value === '')) {
                $errors[] = [
                    'row' => $rowNumber,
                    'column' => $columns[$columnKey]['header'] ?? $columnKey,
                    'message' => 'This field is required.',
                ];
            }
            return $value;
        };

        $rawAdmissionNo = trim((string) ($getValue('student.admission_no') ?? ''));
        if ($rawAdmissionNo !== '') {
            $errors[] = [
                'row' => $rowNumber,
                'column' => $columns['student.admission_no']['header'],
                'message' => 'Admission numbers are generated automatically. Leave this column blank.',
            ];
        }
        $studentData['admission_no'] = null;
        $studentData['first_name'] = $getValue('student.first_name', true);
        $studentData['middle_name'] = $getValue('student.middle_name');
        $studentData['last_name'] = $getValue('student.last_name', true);

        $genderRaw = $getValue('student.gender', true);
        if ($genderRaw) {
            $genderKey = strtolower($genderRaw);
            if (! array_key_exists($genderKey, self::GENDER_MAP)) {
                $errors[] = [
                    'row' => $rowNumber,
                    'column' => $columns['student.gender']['header'],
                    'message' => 'Gender must be one of M, F, or O.',
                ];
            } else {
                $studentData['gender'] = self::GENDER_MAP[$genderKey];
            }
        }

        $studentData['date_of_birth'] = $this->validateDate(
            $getValue('student.date_of_birth', true),
            $rowNumber,
            $columns['student.date_of_birth']['header'],
            $errors
        );

        $studentData['admission_date'] = $this->validateDate(
            $getValue('student.admission_date', true),
            $rowNumber,
            $columns['student.admission_date']['header'],
            $errors
        );

        $status = strtolower((string) $getValue('student.status', true));
        if ($status && ! in_array($status, self::STATUS_OPTIONS, true)) {
            $errors[] = [
                'row' => $rowNumber,
                'column' => $columns['student.status']['header'],
                'message' => 'Status must be one of: ' . implode(', ', self::STATUS_OPTIONS),
            ];
        } else {
            $studentData['status'] = $status ?: 'active';
        }

        $studentData['nationality'] = $getValue('student.nationality');
        $studentData['state_of_origin'] = $getValue('student.state_of_origin');
        $studentData['lga_of_origin'] = $getValue('student.lga_of_origin');
        $houseValue = $getValue('student.house');
        $studentData['house'] = ($houseValue !== null && trim((string) $houseValue) !== '')
            ? trim((string) $houseValue)
            : null;

        $clubValue = $getValue('student.club');
        $studentData['club'] = ($clubValue !== null && trim((string) $clubValue) !== '')
            ? trim((string) $clubValue)
            : null;
        $studentData['address'] = $getValue('student.address');
        $studentData['medical_information'] = $getValue('student.medical_information');

        $sessionValue = $getValue('student.current_session_id', true);
        $session = $this->resolveModelByNameOrId($sessions, $sessionValue);
        if (! $session) {
            $errors[] = [
                'row' => $rowNumber,
                'column' => $columns['student.current_session_id']['header'],
                'message' => 'Session not found. Use the exact name or ID shown in the template.',
            ];
        } else {
            $studentData['current_session_id'] = $session->id;
        }

        $termValue = $getValue('student.current_term_id', true);
        $term = $this->resolveModelByNameOrId($terms, $termValue);
        if (! $term) {
            $errors[] = [
                'row' => $rowNumber,
                'column' => $columns['student.current_term_id']['header'],
                'message' => 'Term not found. Use the exact name or ID shown in the template.',
            ];
        } elseif ($session && $term->session_id !== $session->id) {
            $errors[] = [
                'row' => $rowNumber,
                'column' => $columns['student.current_term_id']['header'],
                'message' => 'Term does not belong to the selected session.',
            ];
        } else {
            $studentData['current_term_id'] = $term?->id;
        }

        $classValue = $getValue('student.school_class_id', true);
        $class = $this->resolveModelByNameOrId($classes, $classValue);
        if (! $class) {
            $errors[] = [
                'row' => $rowNumber,
                'column' => $columns['student.school_class_id']['header'],
                'message' => 'Class not found. Use the exact name or ID shown in the template.',
            ];
        } else {
            $studentData['school_class_id'] = $class->id;
        }

        $armValue = $getValue('student.class_arm_id', true);
        $classArm = $class?->class_arms->first(function ($arm) use ($armValue) {
            return $this->matchesNameOrId($armValue, $arm->id, $arm->name);
        });
        if (! $classArm) {
            $errors[] = [
                'row' => $rowNumber,
                'column' => $columns['student.class_arm_id']['header'],
                'message' => 'Class arm not found for the selected class.',
            ];
        } else {
            $studentData['class_arm_id'] = $classArm->id;
        }

        $sectionValue = $getValue('student.class_section_id');
        if ($sectionValue !== null && $sectionValue !== '') {
            $classSection = $classArm?->class_sections->first(function ($section) use ($sectionValue) {
                return $this->matchesNameOrId($sectionValue, $section->id, $section->name);
            });

            if (! $classSection) {
                $errors[] = [
                    'row' => $rowNumber,
                    'column' => $columns['student.class_section_id']['header'],
                    'message' => 'Class section not found for the selected class arm.',
                ];
            } else {
                $studentData['class_section_id'] = $classSection->id;
            }
        } else {
            $studentData['class_section_id'] = null;
        }

        $parentData['first_name'] = $getValue('parent.first_name');
        $parentData['last_name'] = $getValue('parent.last_name');
        $parentData['email'] = $getValue('parent.email');
        $parentData['phone'] = $getValue('parent.phone');
        $parentData['address'] = $getValue('parent.address');
        $parentData['occupation'] = $getValue('parent.occupation');
        $parentData['nationality'] = $getValue('parent.nationality');
        $parentData['state_of_origin'] = $getValue('parent.state_of_origin');
        $parentData['local_government_area'] = $getValue('parent.local_government_area');

        $parentFieldsProvided = collect($parentData)->filter(function ($value) {
            return $value !== null && $value !== '';
        })->isNotEmpty();

        if ($parentFieldsProvided) {
            foreach (['first_name', 'last_name', 'email'] as $requiredParentField) {
                if (! ($parentData[$requiredParentField] ?? null)) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'column' => $columns["parent.{$requiredParentField}"]['header'] ?? $requiredParentField,
                        'message' => 'This field is required when linking a parent.',
                    ];
                }
            }

            if ($parentData['email']) {
                if (! filter_var($parentData['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'column' => $columns['parent.email']['header'],
                        'message' => 'Invalid email address.',
                    ];
                } else {
                    $existingParent = SchoolParent::query()
                        ->where('school_id', $school->id)
                        ->whereHas('user', fn ($query) => $query->where('email', $parentData['email']))
                        ->first();

                    if (! $existingParent) {
                        $existingUser = User::query()
                            ->where('email', $parentData['email'])
                            ->first();

                        if ($existingUser) {
                            if ($existingUser->school_id !== $school->id) {
                                $errors[] = [
                                    'row' => $rowNumber,
                                    'column' => $columns['parent.email']['header'],
                                    'message' => 'Email already exists in another school.',
                                ];
                            } elseif (! $existingUser->hasRole('parent') && $existingUser->role !== 'parent') {
                                $errors[] = [
                                    'row' => $rowNumber,
                                    'column' => $columns['parent.email']['header'],
                                    'message' => 'Email already in use by a non-parent account.',
                                ];
                            }
                        }
                    }
                }
            }
        } else {
            foreach ($parentData as $key => $value) {
                $parentData[$key] = null;
            }
        }

        $compositeKey = strtolower("{$studentData['first_name']}|{$studentData['last_name']}|{$studentData['date_of_birth']}");
        if (in_array($compositeKey, $inFileComposite, true)) {
            $errors[] = [
                'row' => $rowNumber,
                'column' => 'Student Name / Date of Birth',
                'message' => 'Duplicate student name and date of birth combination within the file.',
            ];
        } else {
            $inFileComposite[] = $compositeKey;
        }

        return [
            [
                'student' => $studentData,
                'parent' => $parentFieldsProvided ? $parentData : null,
                'source_row' => $rowNumber,
            ],
            $errors,
        ];
    }

    private function validateDate(?string $value, int $rowNumber, string $columnLabel, array &$errors): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $exception) {
            $errors[] = [
                'row' => $rowNumber,
                'column' => $columnLabel,
                'message' => 'Invalid date format. Use YYYY-MM-DD.',
            ];
        }

        return null;
    }

    private function resolveModelByNameOrId(EloquentCollection $collection, ?string $value)
    {
        if (! $value) {
            return null;
        }

        $value = trim($value);

        if (Str::isUuid($value)) {
            return $collection->firstWhere('id', $value);
        }

        return $collection->first(fn ($item) => Str::lower($item->name) === Str::lower($value));
    }

    private function matchesNameOrId(?string $input, string $id, string $name): bool
    {
        if ($input === null || $input === '') {
            return false;
        }

        $input = trim($input);

        if (Str::isUuid($input) && $input === $id) {
            return true;
        }

        return Str::lower($input) === Str::lower($name);
    }

    private function buildErrorCsv(array $errors, array $columns, array $validRows = []): string
    {
        $handle = fopen('php://temp', 'w+');
        $header = ['Row', 'Column', 'Message'];
        fputcsv($handle, $header);

        foreach ($errors as $error) {
            fputcsv($handle, [
                $error['row'] ?? '-',
                $error['column'] ?? '-',
                $error['message'] ?? '-',
            ]);
        }

        rewind($handle);
        return stream_get_contents($handle) ?: '';
    }

    private function resolveParent(School $school, array $parentData, int &$createdParents): SchoolParent
    {
        $parent = SchoolParent::query()
            ->where('school_id', $school->id)
            ->whereHas('user', fn ($query) => $query->where('email', $parentData['email']))
            ->first();

        if ($parent) {
            return $parent;
        }

        $user = User::query()->firstOrNew(['email' => $parentData['email']]);
        if (! $user->exists) {
            $user->fill([
                'id' => (string) Str::uuid(),
                'password' => Hash::make(Str::random(12)),
            ]);
        }

        $user->name = trim("{$parentData['first_name']} {$parentData['last_name']}");
        $user->role = 'parent';
        $user->status = 'active';
        $user->school_id = $school->id;
        $user->phone = $parentData['phone'];
        $user->address = $parentData['address'];
        $user->occupation = $parentData['occupation'];
        $user->nationality = $parentData['nationality'];
        $user->state_of_origin = $parentData['state_of_origin'];
        $user->local_government_area = $parentData['local_government_area'];
        $user->save();

        $parentRole = Role::query()->updateOrCreate(
            [
                'name' => 'parent',
                'school_id' => $school->id,
            ],
            [
                'guard_name' => config('permission.default_guard', 'sanctum'),
                'description' => 'Parent or guardian',
            ]
        );

        $this->withTeamContext($school->id, function () use ($user, $parentRole) {
            if (! $user->hasRole($parentRole)) {
                $user->assignRole($parentRole);
            }
        });

        $parent = SchoolParent::create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'user_id' => $user->id,
            'first_name' => $parentData['first_name'],
            'last_name' => $parentData['last_name'],
            'phone' => $parentData['phone'],
            'address' => $parentData['address'],
            'occupation' => $parentData['occupation'],
            'nationality' => $parentData['nationality'],
            'state_of_origin' => $parentData['state_of_origin'],
            'local_government_area' => $parentData['local_government_area'],
        ]);

        $createdParents++;

        return $parent;
    }

    private function normalizeHeaderValue(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/\s*\(.*?\)/', '')
            ->replace([' ', '/', '-'], '_')
            ->replace('.', '_')
            ->trim('_')
            ->value();
    }

    private function writeReferenceRow($handle, string $label, string $value): void
    {
        fputcsv($handle, ["# {$label}", $value]);
    }

    private function withTeamContext(string $schoolId, callable $callback)
    {
        /** @var PermissionRegistrar $registrar */
        $registrar = app(PermissionRegistrar::class);
        $previousTeam = method_exists($registrar, 'getPermissionsTeamId')
            ? $registrar->getPermissionsTeamId()
            : null;

        $registrar->setPermissionsTeamId($schoolId);

        try {
            return $callback();
        } finally {
            $registrar->setPermissionsTeamId($previousTeam);
        }
    }
}
