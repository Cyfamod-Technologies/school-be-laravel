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
use ZipArchive;

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

    private const DUPLICATE_ACTIONS = ['skip', 'overwrite', 'allow'];

    /**
     * Generate a clean CSV template for bulk student upload.
     *
     * @param School $school
     * @param array{session_id?: string|null, class_id?: string|null, class_arm_id?: string|null} $preselected
     * @return string
     */
    public function generateTemplate(School $school, array $preselected = []): string
    {
        $columns = $this->buildColumnDefinitions($preselected);

        // Resolve preselected entities for context info
        $preselectedSession = null;
        $preselectedClass = null;
        $preselectedArm = null;
        if (!empty($preselected['session_id'])) {
            $preselectedSession = $school->sessions()->find($preselected['session_id']);
        }
        if (!empty($preselected['class_id'])) {
            $preselectedClass = SchoolClass::where('school_id', $school->id)->find($preselected['class_id']);
        }
        if (!empty($preselected['class_arm_id']) && $preselectedClass) {
            $preselectedArm = $preselectedClass->class_arms()->find($preselected['class_arm_id']);
        }

        $handle = fopen('php://temp', 'w+');

        // Context header showing what class this template is for
        if ($preselectedSession && $preselectedClass) {
            $contextRow = [
                '# TEMPLATE FOR',
                "Session: {$preselectedSession->name}",
                "Class: {$preselectedClass->name}",
            ];

            if ($preselectedArm) {
                $contextRow[] = "Arm: {$preselectedArm->name}";
            }

            fputcsv($handle, $contextRow);
        }

        // Simple instruction
        fputcsv($handle, ['# INSTRUCTIONS', 'Fill student details starting from row 4. Row 3 has an example you can delete. Dates: YYYY-MM-DD format.']);

        // Header row
        $headerRow = [];
        $exampleRow = [];
        foreach ($columns as $column) {
            $headerRow[] = $column['header'];
            $exampleRow[] = $column['example'] ?? '';
        }

        fputcsv($handle, $headerRow);
        fputcsv($handle, $exampleRow);

        rewind($handle);
        return stream_get_contents($handle) ?: '';
    }

    /**
     * Validate and prepare uploaded CSV for bulk student creation.
     *
     * @param School $school
     * @param UploadedFile $file
     * @param User $user
     * @param array{session_id?: string|null, class_id?: string|null, class_arm_id?: string|null} $preselected
     * @return array<string, mixed>
     *
     * @throws BulkUploadValidationException
     */
    public function validateAndPrepare(
        School $school,
        UploadedFile $file,
        User $user,
        array $preselected = [],
        array $rowUpdates = []
    ): array
    {
        $hasSessionClassPreselection = ! empty($preselected['session_id']) && ! empty($preselected['class_id']);
        $hasArmPreselection = $hasSessionClassPreselection && ! empty($preselected['class_arm_id']);
        $columns = $this->buildColumnDefinitions($preselected);
        $columnMap = collect($columns)->keyBy('key');

        $sessions = $school->sessions()->orderBy('name')->get();
        $terms = $school->terms()
            ->orderBy('term_number')
            ->orderBy('start_date')
            ->get();
        $classes = SchoolClass::query()
            ->where('school_id', $school->id)
            ->with(['class_arms.class_sections'])
            ->get();

        // Resolve preselected entities
        $preselectedSession = null;
        $preselectedTerm = null;
        $preselectedClass = null;
        $preselectedArm = null;

        if ($hasSessionClassPreselection) {
            $preselectedSession = $sessions->firstWhere('id', $preselected['session_id']);
            $preselectedClass = $classes->firstWhere('id', $preselected['class_id']);

            // Get the first term of the session
            if ($preselectedSession) {
                $preselectedTerm = $terms->firstWhere('session_id', $preselectedSession->id);
            }

            if (! $preselectedSession || ! $preselectedClass) {
                throw new BulkUploadValidationException([], null, [], 'Invalid session or class selection.');
            }

            if (! $preselectedTerm) {
                throw new BulkUploadValidationException([], null, [], 'No term found for the selected session.');
            }

            if ($hasArmPreselection) {
                $preselectedArm = $preselectedClass->class_arms->firstWhere('id', $preselected['class_arm_id']);
                if (! $preselectedArm) {
                    throw new BulkUploadValidationException([], null, [], 'Invalid class arm selection for the selected class.');
                }
            }
        }

        $uploadedRows = $this->readUploadedRows($file);
        $header = null;
        $prefetchedRows = [];
        $rowCursor = 0;
        for ($i = 0; $i < 2 && $i < count($uploadedRows); $i++) {
            $prefetchedRows[] = $uploadedRows[$i]['values'];
        }

        $shouldSkipPrefetched = count($prefetchedRows) === 2
            && $this->isSkippableRow($prefetchedRows[0])
            && $this->isSkippableRow($prefetchedRows[1]);

        if ($shouldSkipPrefetched) {
            $rowCursor = 2;
        }

        while ($rowCursor < count($uploadedRows)) {
            $entry = $uploadedRows[$rowCursor++];
            $row = $entry['values'];
            if ($this->isSkippableRow($row)) {
                continue;
            }
            $header = $row;
            $headerLineNumber = $entry['number'];
            break;
        }

        if (! $header) {
            throw new BulkUploadValidationException([], null, [], 'The uploaded file is empty or unreadable.');
        }

        $normalizedHeader = $this->normalizeHeaderRow($header);
        $missingColumns = [];
        foreach ($columns as $definition) {
            if (! $definition['required']) {
                continue;
            }

            $headerKey = $definition['header_key'] ?? $this->normalizeHeaderValue($definition['header']);
            $legacyKey = Str::replace('.', '_', $definition['key']);

            if (
                ! array_key_exists($headerKey, $normalizedHeader)
                && ! array_key_exists($legacyKey, $normalizedHeader)
            ) {
                $missingColumns[] = $definition['header'];
            }
        }

        if (! empty($missingColumns)) {
            $detectedHeaders = array_filter(
                array_map(fn ($value) => trim((string) $value), $header),
                fn ($value) => $value !== ''
            );
            $detectedSummary = empty($detectedHeaders)
                ? 'none'
                : implode(', ', array_slice($detectedHeaders, 0, 12));

            throw new BulkUploadValidationException(
                [],
                null,
                [],
                'The uploaded file is missing required columns: '
                . implode(', ', $missingColumns)
                . ". Detected headers: {$detectedSummary}."
            );
        }

        $preparedRows = [];
        $previewCandidates = [];
        $errors = [];
        $normalizedRowUpdates = $this->normalizeRowUpdates($rowUpdates);

        $inFileComposite = [];
        $inFileAdmissionNumbers = [];

        while ($rowCursor < count($uploadedRows)) {
            $entry = $uploadedRows[$rowCursor++];
            $rowNumber = $entry['number'];
            $row = $entry['values'];

            if ($this->isSkippableRow($row)) {
                continue;
            }

            $rowData = $this->mapRowToData($row, $normalizedHeader, $columnMap);
            
            // Inject preselected values if they were provided
            if ($hasSessionClassPreselection) {
                $rowData['student.current_session_id'] = $preselectedSession->id;
                $rowData['student.current_term_id'] = $preselectedTerm?->id;
                $rowData['student.school_class_id'] = $preselectedClass->id;
            }
            if ($hasArmPreselection) {
                $rowData['student.class_arm_id'] = $preselectedArm->id;
            }
            $rowData = $this->applyPreviewRowUpdates($rowData, $rowNumber, $normalizedRowUpdates);
            if ($this->isRowMarkedDeleted((string) $rowNumber, $normalizedRowUpdates)) {
                continue;
            }

            [$rowPrepared, $rowErrors] = $this->validateRow(
                $rowNumber,
                $rowData,
                $columnMap,
                $sessions,
                $terms,
                $classes,
                $school,
                $inFileComposite,
                $inFileAdmissionNumbers
            );

            $previewCandidates[] = $rowPrepared;

            if (! empty($rowErrors)) {
                array_push($errors, ...$rowErrors);
                continue;
            }

            $preparedRows[] = $rowPrepared;
        }

        if (count($preparedRows) === 0) {
            $errorCsv = $this->buildErrorCsv([], $columns);
            throw new BulkUploadValidationException(
                $errors ?: [['row' => '-', 'column' => '-', 'message' => 'No valid rows found in the file.']],
                $errorCsv,
                $this->buildPreviewRows($previewCandidates, $sessions, $terms, $classes)
            );
        }

        if (! empty($errors)) {
            $errorCsv = $this->buildErrorCsv($errors, $columns, $preparedRows);
            throw new BulkUploadValidationException(
                $errors,
                $errorCsv,
                $this->buildPreviewRows($previewCandidates, $sessions, $terms, $classes)
            );
        }

        $this->attachDuplicates($school, $preparedRows);

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

        $previewRows = $this->buildPreviewRows($preparedRows, $sessions, $terms, $classes);

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
    public function commit(BulkUploadBatch $batch, array $decisions = [], array $rowUpdates = []): array
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
        $updatedStudents = 0;
        $skippedRows = 0;
        $createdParents = 0;

        $decisionMap = $this->normalizeDuplicateDecisions($decisions);
        $rowUpdateMap = $this->normalizeRowUpdates($rowUpdates);

        DB::transaction(function () use (&$createdStudents, &$updatedStudents, &$skippedRows, &$createdParents, $rows, $school, $user, $batch, $decisionMap, $rowUpdateMap) {
            foreach ($rows as $row) {
                $rowKey = (string) ($row['source_row'] ?? '');
                if ($rowKey !== '' && $this->isRowMarkedDeleted($rowKey, $rowUpdateMap)) {
                    $skippedRows++;
                    continue;
                }

                $row = $this->applyRowUpdates($row, $rowUpdateMap);
                $action = $this->resolveDuplicateAction($row, $decisionMap);

                if ($action === 'skip') {
                    $skippedRows++;
                    continue;
                }

                $parent = null;
                if (is_array($row['parent'] ?? null) && ! empty($row['parent']['email'])) {
                    $parent = $this->resolveParent($school, $row['parent'], $createdParents, $action === 'overwrite');
                }

                $studentData = $row['student'];
                $studentData['status'] = strtolower($studentData['status']);

                if ($action === 'overwrite') {
                    $existingStudent = $this->resolveDuplicateStudent($school, $row);
                    if (! $existingStudent) {
                        $action = 'allow';
                    } else {
                        $studentData['school_id'] = $school->id;
                        $studentData['parent_id'] = $parent?->id ?? $existingStudent->parent_id;
                        $studentData['admission_no'] = $existingStudent->admission_no;
                        $existingStudent->fill($studentData);
                        $existingStudent->save();
                        $updatedStudents++;
                        continue;
                    }
                }

                $studentData['id'] = (string) Str::uuid();
                $studentData['school_id'] = $school->id;
                $studentData['parent_id'] = $parent?->id;
                $studentData['portal_password'] = '123456';
                $session = Session::findOrFail($studentData['current_session_id']);
                if (empty($studentData['admission_no'])) {
                    $studentData['admission_no'] = Student::generateAdmissionNumber($school, $session);
                }

                $this->assertAdmissionNumberIsAvailable($school, $row, $studentData);

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
            'processed' => $createdStudents + $updatedStudents,
            'created' => $createdStudents,
            'updated' => $updatedStudents,
            'skipped' => $skippedRows,
            'parents_created' => $createdParents,
            'failed' => 0,
        ];
    }

    /**
     * Build column definitions for the CSV template.
     * 
     * @param array{session_id?: string|null, class_id?: string|null, class_arm_id?: string|null} $preselected
     * @return array<int, array<string, mixed>>
     */
    private function buildColumnDefinitions(array $preselected = []): array
    {
        $student = new Student();
        $studentFillable = collect($student->getFillable());

        // Determine which columns to exclude based on preselected values
        $excludeKeys = [];
        $hasSessionClassPreselection = ! empty($preselected['session_id']) && ! empty($preselected['class_id']);
        $hasArmPreselection = $hasSessionClassPreselection && ! empty($preselected['class_arm_id']);

        if ($hasSessionClassPreselection) {
            // If session/class are preselected, don't include them in the template
            $excludeKeys = [
                'student.current_session_id',
                'student.current_term_id',
                'student.school_class_id',
            ];
        }

        if ($hasArmPreselection) {
            $excludeKeys[] = 'student.class_arm_id';
        }

        $baseColumns = collect([
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
                'required' => false,
                'example' => now()->toDateString(),
            ],
            [
                'key' => 'student.status',
                'header' => 'Status',
                'required' => false,
                'example' => 'active',
            ],
            [
                'key' => 'student.admission_no',
                'header' => 'Admission Number',
                'required' => false,
                'example' => '',
            ],
            [
                'key' => 'student.nationality',
                'header' => 'Nationality',
                'required' => false,
                'example' => 'Nigerian',
            ],
            [
                'key' => 'student.state_of_origin',
                'header' => 'State of Origin',
                'required' => false,
                'example' => 'Enugu',
            ],
            [
                'key' => 'student.lga_of_origin',
                'header' => 'LGA of Origin',
                'required' => false,
                'example' => 'Nsukka',
            ],
            [
                'key' => 'student.address',
                'header' => 'Address',
                'required' => false,
                'example' => '12 Unity Close',
            ],
            [
                'key' => 'student.medical_information',
                'header' => 'Medical Info',
                'required' => false,
                'example' => '',
            ],
            [
                'key' => 'student.house',
                'header' => 'House',
                'required' => false,
                'example' => '',
            ],
            [
                'key' => 'student.club',
                'header' => 'Club',
                'required' => false,
                'example' => '',
            ],
            // Session/Class columns - only included if not preselected
            [
                'key' => 'student.current_session_id',
                'header' => 'Session',
                'required' => ! $hasSessionClassPreselection,
                'example' => '2025/2026',
            ],
            [
                'key' => 'student.current_term_id',
                'header' => 'Term',
                'required' => ! $hasSessionClassPreselection,
                'example' => 'First Term',
            ],
            [
                'key' => 'student.school_class_id',
                'header' => 'Class',
                'required' => ! $hasSessionClassPreselection,
                'example' => 'JSS 1',
            ],
            [
                'key' => 'student.class_arm_id',
                'header' => 'Class Arm',
                'required' => ! $hasArmPreselection,
                'example' => 'A',
            ],
            // Parent columns
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
                'example' => '',
            ],
            [
                'key' => 'parent.occupation',
                'header' => 'Parent Occupation',
                'required' => false,
                'example' => '',
            ],
        ]);

        // Filter out excluded columns (when preselected)
        $filteredColumns = $baseColumns->reject(fn ($col) => in_array($col['key'], $excludeKeys));

        // We explicitly define all necessary columns - no auto-appending of fillable fields
        // This keeps the template clean and predictable

        return $filteredColumns
            ->map(function (array $column) {
                $column['header_key'] = $this->normalizeHeaderValue($column['header']);
                return $column;
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{number: int, values: array<int, string|null>}>
     */
    private function readUploadedRows(UploadedFile $file): array
    {
        $extension = Str::lower($file->getClientOriginalExtension());

        if ($extension === 'xlsx') {
            return $this->readXlsxRows($file);
        }

        return $this->readCsvRows($file);
    }

    /**
     * @return array<int, array{number: int, values: array<int, string|null>}>
     */
    private function readCsvRows(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            throw new BulkUploadValidationException([], null, [], 'Unable to read the uploaded file.');
        }

        $delimiter = $this->detectDelimiter($handle);
        $rows = [];
        $rowNumber = 0;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;
            $rows[] = [
                'number' => $rowNumber,
                'values' => $row,
            ];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array<int, array{number: int, values: array<int, string|null>}>
     */
    private function readXlsxRows(UploadedFile $file): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new BulkUploadValidationException([], null, [], 'XLSX uploads require the PHP zip extension.');
        }

        $zip = new ZipArchive();
        if ($zip->open($file->getRealPath()) !== true) {
            throw new BulkUploadValidationException([], null, [], 'Unable to read the uploaded XLSX file.');
        }

        try {
            $sharedStrings = $this->readXlsxSharedStrings($zip);
            $dateStyleIndexes = $this->readXlsxDateStyleIndexes($zip);
            $worksheetXml = $zip->getFromName($this->firstWorksheetPath($zip));
            if ($worksheetXml === false) {
                throw new BulkUploadValidationException([], null, [], 'The XLSX file does not contain a readable worksheet.');
            }

            $worksheet = simplexml_load_string($worksheetXml);
            if (! $worksheet || ! isset($worksheet->sheetData)) {
                throw new BulkUploadValidationException([], null, [], 'The XLSX worksheet is empty or unreadable.');
            }

            $rows = [];
            foreach ($worksheet->sheetData->row as $worksheetRow) {
                $rowNumber = (int) ($worksheetRow['r'] ?? (count($rows) + 1));
                $values = [];

                foreach ($worksheetRow->c as $cell) {
                    $reference = (string) ($cell['r'] ?? '');
                    $columnIndex = $reference !== ''
                        ? $this->xlsxColumnIndex($reference)
                        : count($values);
                    $values[$columnIndex] = $this->xlsxCellValue($cell, $sharedStrings, $dateStyleIndexes);
                }

                if ($values === []) {
                    $values = [''];
                } else {
                    ksort($values);
                    $values = array_replace(array_fill(0, max(array_keys($values)) + 1, null), $values);
                }

                $rows[] = [
                    'number' => $rowNumber,
                    'values' => $values,
                ];
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array<int, string>
     */
    private function readXlsxSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $sharedStringXml = simplexml_load_string($xml);
        if (! $sharedStringXml) {
            return [];
        }

        $strings = [];
        foreach ($sharedStringXml->si as $item) {
            if (isset($item->t)) {
                $strings[] = (string) $item->t;
                continue;
            }

            $text = '';
            foreach ($item->r as $run) {
                $text .= (string) ($run->t ?? '');
            }
            $strings[] = $text;
        }

        return $strings;
    }

    /**
     * @return array<int, true>
     */
    private function readXlsxDateStyleIndexes(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/styles.xml');
        if ($xml === false) {
            return [];
        }

        $stylesXml = simplexml_load_string($xml);
        if (! $stylesXml) {
            return [];
        }

        $stylesXml->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $customDateFormats = [];
        foreach ($stylesXml->xpath('//main:numFmts/main:numFmt') ?: [] as $numFmt) {
            $formatId = (int) ($numFmt['numFmtId'] ?? 0);
            $formatCode = strtolower((string) ($numFmt['formatCode'] ?? ''));
            if ($this->xlsxLooksLikeDateFormat($formatCode)) {
                $customDateFormats[$formatId] = true;
            }
        }

        $dateStyleIndexes = [];
        foreach ($stylesXml->xpath('//main:cellXfs/main:xf') ?: [] as $styleIndex => $xf) {
            $formatId = (int) ($xf['numFmtId'] ?? 0);
            if ($this->xlsxIsBuiltInDateFormat($formatId) || isset($customDateFormats[$formatId])) {
                $dateStyleIndexes[(int) $styleIndex] = true;
            }
        }

        return $dateStyleIndexes;
    }

    private function firstWorksheetPath(ZipArchive $zip): string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbookXml === false || $relsXml === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $workbook = simplexml_load_string($workbookXml);
        $relationships = simplexml_load_string($relsXml);
        if (! $workbook || ! $relationships || ! isset($workbook->sheets->sheet[0])) {
            return 'xl/worksheets/sheet1.xml';
        }

        $namespaces = $workbook->sheets->sheet[0]->getNamespaces(true);
        $relationshipId = (string) ($workbook->sheets->sheet[0]->attributes($namespaces['r'] ?? '')->id ?? '');
        if ($relationshipId === '') {
            return 'xl/worksheets/sheet1.xml';
        }

        foreach ($relationships->Relationship as $relationship) {
            if ((string) $relationship['Id'] !== $relationshipId) {
                continue;
            }

            $target = ltrim((string) $relationship['Target'], '/');
            return Str::startsWith($target, 'xl/')
                ? $target
                : 'xl/' . $target;
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private function xlsxCellValue(\SimpleXMLElement $cell, array $sharedStrings, array $dateStyleIndexes): ?string
    {
        $type = (string) ($cell['t'] ?? '');

        if ($type === 's') {
            $index = (int) ($cell->v ?? -1);
            return $sharedStrings[$index] ?? '';
        }

        if ($type === 'inlineStr') {
            if (isset($cell->is->t)) {
                return (string) $cell->is->t;
            }

            $text = '';
            foreach ($cell->is->r as $run) {
                $text .= (string) ($run->t ?? '');
            }
            return $text;
        }

        if (isset($cell->v)) {
            $value = (string) $cell->v;
            $styleIndex = (int) ($cell['s'] ?? -1);
            if ($type === '' && isset($dateStyleIndexes[$styleIndex]) && is_numeric($value)) {
                return Carbon::create(1899, 12, 30)->addDays((int) floor((float) $value))->toDateString();
            }

            return $value;
        }

        return null;
    }

    private function xlsxIsBuiltInDateFormat(int $formatId): bool
    {
        return in_array($formatId, [14, 15, 16, 17, 22, 27, 30, 36, 45, 46, 47, 50, 57], true);
    }

    private function xlsxLooksLikeDateFormat(string $formatCode): bool
    {
        $formatCode = preg_replace('/\[[^\]]+\]/', '', $formatCode) ?? $formatCode;
        $formatCode = preg_replace('/"[^"]*"/', '', $formatCode) ?? $formatCode;

        return str_contains($formatCode, 'y')
            && (str_contains($formatCode, 'd') || str_contains($formatCode, 'm'));
    }

    private function xlsxColumnIndex(string $reference): int
    {
        preg_match('/^[A-Z]+/i', $reference, $matches);
        $letters = strtoupper($matches[0] ?? 'A');
        $index = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return max(0, $index - 1);
    }

    /**
     * @param  array<int, string>  $row
     */
    private function isSkippableRow(array $row): bool
    {
        $firstValue = trim($row[0] ?? '');
        // Strip UTF-8 BOM so "# TEMPLATE FOR" and "# INSTRUCTIONS" lines are detectable.
        $firstValue = ltrim($firstValue, "\xEF\xBB\xBF");

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
     * @param  array<string, array{row: int, name: string}>  $inFileAdmissionNumbers
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
        array &$inFileComposite,
        array &$inFileAdmissionNumbers
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
        $studentData['admission_no'] = $rawAdmissionNo !== '' ? $rawAdmissionNo : null;
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
            $getValue('student.admission_date', false),
            $rowNumber,
            $columns['student.admission_date']['header'],
            $errors
        );

        $status = strtolower((string) $getValue('student.status', false));
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

        $studentData['class_section_id'] = null;

        $parentData['first_name'] = $getValue('parent.first_name');
        $parentData['last_name'] = $getValue('parent.last_name');
        $parentData['email'] = $getValue('parent.email');
        $parentData['phone'] = $getValue('parent.phone');
        $parentData['address'] = $getValue('parent.address');
        $parentData['occupation'] = $getValue('parent.occupation');
        $parentData['nationality'] = $getValue('parent.nationality');
        $parentData['state_of_origin'] = $getValue('parent.state_of_origin');
        $parentData['local_government_area'] = $getValue('parent.local_government_area');

        $shouldLinkParent = trim((string) ($parentData['email'] ?? '')) !== '';

        if ($shouldLinkParent) {
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

                    if ($existingUser && $existingUser->school_id !== $school->id) {
                        $errors[] = [
                            'row' => $rowNumber,
                            'column' => $columns['parent.email']['header'],
                            'message' => 'Email already exists in another school.',
                        ];
                    }
                }
            }

            $parentData['first_name'] = trim((string) ($parentData['first_name'] ?: $studentData['first_name'] ?: 'Parent'));
            $parentData['last_name'] = trim((string) ($parentData['last_name'] ?: $studentData['last_name'] ?: 'Guardian'));
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

        if ($rawAdmissionNo !== '') {
            $normalizedAdmissionNo = strtolower($rawAdmissionNo);
            $currentStudentName = trim("{$studentData['first_name']} {$studentData['last_name']}");

            if (array_key_exists($normalizedAdmissionNo, $inFileAdmissionNumbers)) {
                $existingEntry = $inFileAdmissionNumbers[$normalizedAdmissionNo];
                $existingName = $existingEntry['name'] !== '' ? $existingEntry['name'] : 'Unknown student';
                $currentName = $currentStudentName !== '' ? $currentStudentName : 'Unknown student';
                $duplicateMessage = "This CSV contains two students with admission number {$rawAdmissionNo}: "
                    . "{$existingName} on row {$existingEntry['row']} and {$currentName} on row {$rowNumber}. "
                    . 'Each student in the CSV must have a unique admission number.';

                $errors[] = [
                    'row' => $existingEntry['row'],
                    'column' => $columns['student.admission_no']['header'],
                    'message' => $duplicateMessage,
                ];
                $errors[] = [
                    'row' => $rowNumber,
                    'column' => $columns['student.admission_no']['header'],
                    'message' => $duplicateMessage,
                ];
            } else {
                $inFileAdmissionNumbers[$normalizedAdmissionNo] = [
                    'row' => $rowNumber,
                    'name' => $currentStudentName,
                ];
            }
        }

        return [
            [
                'student' => $studentData,
                'parent' => $shouldLinkParent ? $parentData : null,
                'source_row' => $rowNumber,
                'admission_no_input' => $rawAdmissionNo !== '' ? $rawAdmissionNo : null,
            ],
            $errors,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function buildPreviewRows(
        array $rows,
        EloquentCollection $sessions,
        EloquentCollection $terms,
        Collection $classes
    ): array {
        return collect($rows)
            ->map(function (array $row) use ($sessions, $terms, $classes) {
                $class = $classes->firstWhere('id', $row['student']['school_class_id'] ?? null);
                $arm = $class?->class_arms->firstWhere('id', $row['student']['class_arm_id'] ?? null);
                $parent = is_array($row['parent'] ?? null) ? $row['parent'] : [];

                return [
                    'name' => trim((string) (($row['student']['first_name'] ?? '') . ' ' . ($row['student']['last_name'] ?? ''))),
                    'gender' => $row['student']['gender'] ?? null,
                    'admission_no' => ($row['student']['admission_no'] ?? null) ?: 'Auto-generated',
                    'session' => optional($sessions->firstWhere('id', $row['student']['current_session_id'] ?? null))->name,
                    'term' => optional($terms->firstWhere('id', $row['student']['current_term_id'] ?? null))->name,
                    'class' => $class?->name,
                    'class_arm' => $arm?->name,
                    'parent_email' => $parent['email'] ?? '—',
                    'duplicate' => $row['duplicate'] ?? null,
                    'duplicate_action' => $row['duplicate_action'] ?? null,
                    'source_row' => $row['source_row'] ?? null,
                ];
            })
            ->values()
            ->all();
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

    private function resolveParent(School $school, array $parentData, int &$createdParents, bool $updateExisting = false): SchoolParent
    {
        $parent = SchoolParent::query()
            ->where('school_id', $school->id)
            ->whereHas('user', fn ($query) => $query->where('email', $parentData['email']))
            ->first();

        if ($parent && ! $updateExisting) {
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
        $user->role = $user->exists ? ($user->role ?: 'parent') : 'parent';
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

        if ($parent) {
            $parent->fill([
                'first_name' => $parentData['first_name'],
                'last_name' => $parentData['last_name'],
                'phone' => $parentData['phone'],
                'address' => $parentData['address'],
                'occupation' => $parentData['occupation'],
                'nationality' => $parentData['nationality'],
                'state_of_origin' => $parentData['state_of_origin'],
                'local_government_area' => $parentData['local_government_area'],
            ]);
            $parent->save();
        } else {
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
        }

        return $parent;
    }

    private function attachDuplicates(School $school, array &$preparedRows): void
    {
        $admissionNos = [];
        $firstNames = [];
        $lastNames = [];
        $nameKeys = [];

        foreach ($preparedRows as $row) {
            $admissionNo = $row['admission_no_input'] ?? null;
            if ($admissionNo) {
                $admissionNos[] = strtolower($admissionNo);
            }

            $firstName = $row['student']['first_name'] ?? null;
            $lastName = $row['student']['last_name'] ?? null;
            if ($firstName && $lastName) {
                $firstLower = strtolower($firstName);
                $lastLower = strtolower($lastName);
                $nameKeys[] = "{$firstLower}|{$lastLower}";
                $firstNames[$firstLower] = true;
                $lastNames[$lastLower] = true;
            }
        }

        $admissionNos = array_values(array_unique($admissionNos));
        $nameKeys = array_values(array_unique($nameKeys));

        $byAdmissionNo = [];
        if (! empty($admissionNos)) {
            $students = Student::query()
                ->where('school_id', $school->id)
                ->whereIn('admission_no', $admissionNos)
                ->get();
            foreach ($students as $student) {
                $byAdmissionNo[strtolower($student->admission_no)] = $student;
            }
        }

        $byName = [];
        if (! empty($nameKeys) && ! empty($firstNames) && ! empty($lastNames)) {
            $students = Student::query()
                ->where('school_id', $school->id)
                ->whereIn(DB::raw('LOWER(TRIM(first_name))'), array_keys($firstNames))
                ->whereIn(DB::raw('LOWER(TRIM(last_name))'), array_keys($lastNames))
                ->get();

            foreach ($students as $student) {
                $key = strtolower(trim($student->first_name)) . '|' . strtolower(trim($student->last_name));
                $byName[$key] = $student;
            }
        }

        foreach ($preparedRows as &$row) {
            $duplicate = null;
            $admissionNo = $row['admission_no_input'] ?? null;
            if ($admissionNo) {
                $match = $byAdmissionNo[strtolower($admissionNo)] ?? null;
                if ($match) {
                    $duplicate = $this->formatDuplicateInfo($match, 'admission_no');
                }
            }

            if (! $duplicate) {
                $firstName = $row['student']['first_name'] ?? null;
                $lastName = $row['student']['last_name'] ?? null;
                if ($firstName && $lastName) {
                    $key = strtolower(trim($firstName)) . '|' . strtolower(trim($lastName));
                    $match = $byName[$key] ?? null;
                    if ($match) {
                        $duplicate = $this->formatDuplicateInfo($match, 'name');
                    }
                }
            }

            $row['duplicate'] = $duplicate;
            $row['duplicate_action'] = $duplicate ? 'allow' : 'create';
        }
        unset($row);
    }

    private function formatDuplicateInfo(Student $student, string $match): array
    {
        return [
            'id' => $student->id,
            'admission_no' => $student->admission_no,
            'name' => trim("{$student->first_name} {$student->last_name}"),
            'match' => $match,
        ];
    }

    private function normalizeDuplicateDecisions(array $decisions): array
    {
        $normalized = [];
        foreach ($decisions as $rowKey => $action) {
            if (! is_string($action)) {
                continue;
            }
            $action = strtolower(trim($action));
            if (! in_array($action, self::DUPLICATE_ACTIONS, true)) {
                continue;
            }
            $normalized[(string) $rowKey] = $action;
        }
        return $normalized;
    }

    /**
     * @param array<string, mixed> $rowUpdates
     * @return array<string, array<string, mixed>>
     */
    private function normalizeRowUpdates(array $rowUpdates): array
    {
        $normalized = [];

        foreach ($rowUpdates as $rowKey => $update) {
            if (! is_array($update)) {
                continue;
            }

            $admissionNo = isset($update['admission_no'])
                ? trim((string) $update['admission_no'])
                : '';
            $deleted = filter_var($update['deleted'] ?? false, FILTER_VALIDATE_BOOL);

            if ($admissionNo === '' && ! $deleted) {
                continue;
            }

            $normalized[(string) $rowKey] = [];
            if ($admissionNo !== '') {
                $normalized[(string) $rowKey]['admission_no'] = $admissionNo;
            }
            if ($deleted) {
                $normalized[(string) $rowKey]['deleted'] = true;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $rowData
     * @param array<string, array<string, mixed>> $rowUpdateMap
     * @return array<string, mixed>
     */
    private function applyPreviewRowUpdates(array $rowData, int $rowNumber, array $rowUpdateMap): array
    {
        $rowKey = (string) $rowNumber;
        if (! array_key_exists($rowKey, $rowUpdateMap)) {
            return $rowData;
        }

        $update = $rowUpdateMap[$rowKey];
        if (! empty($update['admission_no'])) {
            $rowData['student.admission_no'] = $update['admission_no'];
        }

        return $rowData;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, array<string, mixed>> $rowUpdateMap
     * @return array<string, mixed>
     */
    private function applyRowUpdates(array $row, array $rowUpdateMap): array
    {
        $rowKey = (string) ($row['source_row'] ?? '');
        if ($rowKey === '' || ! array_key_exists($rowKey, $rowUpdateMap)) {
            return $row;
        }

        $update = $rowUpdateMap[$rowKey];
        if (! empty($update['admission_no'])) {
            $row['student']['admission_no'] = $update['admission_no'];
            $row['admission_no_input'] = $update['admission_no'];

            if (($row['duplicate']['admission_no'] ?? null) !== $update['admission_no']) {
                $row['duplicate'] = null;
                $row['duplicate_action'] = 'create';
            }
        }

        return $row;
    }

    /**
     * @param array<string, array<string, mixed>> $rowUpdateMap
     */
    private function isRowMarkedDeleted(string $rowKey, array $rowUpdateMap): bool
    {
        return (bool) ($rowUpdateMap[$rowKey]['deleted'] ?? false);
    }

    private function resolveDuplicateAction(array $row, array $decisionMap): string
    {
        $rowKey = (string) ($row['source_row'] ?? '');
        if ($rowKey !== '' && array_key_exists($rowKey, $decisionMap)) {
            return $decisionMap[$rowKey];
        }

        $defaultAction = $row['duplicate_action'] ?? 'create';
        if ($defaultAction === 'create') {
            return 'allow';
        }

        return in_array($defaultAction, self::DUPLICATE_ACTIONS, true) ? $defaultAction : 'allow';
    }

    private function resolveDuplicateStudent(School $school, array $row): ?Student
    {
        $duplicateId = $row['duplicate']['id'] ?? null;
        if ($duplicateId) {
            return Student::query()
                ->where('school_id', $school->id)
                ->where('id', $duplicateId)
                ->first();
        }

        $admissionNo = $row['admission_no_input'] ?? null;
        if ($admissionNo) {
            return Student::query()
                ->where('school_id', $school->id)
                ->where('admission_no', $admissionNo)
                ->first();
        }

        $firstName = $row['student']['first_name'] ?? null;
        $lastName = $row['student']['last_name'] ?? null;
        if ($firstName && $lastName) {
            return Student::query()
                ->where('school_id', $school->id)
                ->whereRaw('LOWER(TRIM(first_name)) = ?', [strtolower(trim($firstName))])
                ->whereRaw('LOWER(TRIM(last_name)) = ?', [strtolower(trim($lastName))])
                ->first();
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $studentData
     *
     * @throws BulkUploadValidationException
     */
    private function assertAdmissionNumberIsAvailable(School $school, array $row, array $studentData): void
    {
        $admissionNo = trim((string) ($studentData['admission_no'] ?? ''));
        if ($admissionNo === '') {
            return;
        }

        $existingStudent = Student::query()
            ->where('admission_no', $admissionNo)
            ->first();

        if (! $existingStudent) {
            return;
        }

        $existingName = trim("{$existingStudent->first_name} {$existingStudent->last_name}");
        $existingClassContext = $this->formatStudentClassContext($existingStudent);
        $incomingClassContext = $this->formatIncomingClassContext($studentData);
        $rowNumber = $row['source_row'] ?? '-';

        $message = "CSV row {$rowNumber}: admission number {$admissionNo} is already used by an existing student record";
        if ($existingName !== '') {
            $message .= " as {$existingName}";
        }
        $message .= " in {$existingClassContext}.";
        $message .= " The CSV row you are uploading is for {$incomingClassContext}.";
        $message .= ' Change the admission number in the CSV or choose Overwrite for that row if you want to update the existing student.';

        throw new BulkUploadValidationException(
            [[
                'row' => $row['source_row'] ?? '-',
                'column' => 'Admission Number',
                'message' => $message,
            ]],
            null,
            [],
            $message
        );
    }

    private function formatStudentClassContext(Student $student): string
    {
        $student->loadMissing(['school_class', 'class_arm']);

        $className = $student->school_class?->name ?: 'No class';
        $armName = $student->class_arm?->name ?: 'None';

        return "{$className} / {$armName}";
    }

    /**
     * @param array<string, mixed> $studentData
     */
    private function formatIncomingClassContext(array $studentData): string
    {
        $className = 'No class';
        $armName = 'None';

        if (! empty($studentData['school_class_id'])) {
            $class = SchoolClass::find($studentData['school_class_id']);
            if ($class) {
                $className = $class->name;
                if (! empty($studentData['class_arm_id'])) {
                    $arm = $class->class_arms()->find($studentData['class_arm_id']);
                    if ($arm) {
                        $armName = $arm->name;
                    }
                }
            }
        }

        return "{$className} / {$armName}";
    }

    private function normalizeHeaderValue(string $value): string
    {
        $value = ltrim($value, "\xEF\xBB\xBF");

        return Str::of($value)
            ->lower()
            ->replaceMatches('/\s*\(.*?\)/', '')
            ->replace([' ', '/', '-'], '_')
            ->replace('.', '_')
            ->trim('_')
            ->value();
    }

    /**
     * Detect delimiter by inspecting the first non-skippable line.
     *
     * @param resource $handle
     */
    private function detectDelimiter($handle): string
    {
        $candidates = [',', ';', "\t", '|'];
        $delimiter = ',';
        $maxFields = 1;

        $lines = [];
        for ($i = 0; $i < 10; $i++) {
            $line = fgets($handle);
            if ($line === false) {
                break;
            }
            $lines[] = $line;
        }

        foreach ($lines as $line) {
            $trimmed = ltrim($line, "\xEF\xBB\xBF");
            $trimmed = trim($trimmed);
            if ($trimmed === '' || Str::startsWith($trimmed, '#')) {
                continue;
            }

            foreach ($candidates as $candidate) {
                $fields = str_getcsv($line, $candidate);
                $count = is_array($fields) ? count($fields) : 0;
                if ($count > $maxFields) {
                    $maxFields = $count;
                    $delimiter = $candidate;
                }
            }
            break;
        }

        rewind($handle);
        return $delimiter;
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
