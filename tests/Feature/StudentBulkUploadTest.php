<?php

use App\Models\BulkUploadBatch;
use App\Models\ClassArm;
use App\Models\ClassSection;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Session;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->school = School::factory()->create();

    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'role' => 'admin',
        'status' => 'active',
    ]);

    Sanctum::actingAs($this->user, [], 'sanctum');

    $this->session = Session::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => '2025/2026',
        'slug' => '2025-2026',
        'start_date' => now()->subMonths(2),
        'end_date' => now()->addMonths(10),
        'status' => 'active',
    ]);

    $this->term = Term::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'session_id' => $this->session->id,
        'name' => 'First Term',
        'slug' => 'first-term',
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonths(3),
        'status' => 'active',
    ]);

    $this->class = SchoolClass::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => 'Grade 6',
        'slug' => 'grade-6',
    ]);

    $this->arm = ClassArm::create([
        'id' => (string) Str::uuid(),
        'school_class_id' => $this->class->id,
        'name' => 'Arm B',
        'slug' => 'arm-b',
    ]);

    $this->section = ClassSection::create([
        'id' => (string) Str::uuid(),
        'class_arm_id' => $this->arm->id,
        'name' => 'Section Blue',
        'slug' => 'section-blue',
    ]);
});

it('downloads a dynamic student bulk template', function () {
    $response = get(route('students.bulk.template'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/csv');

    expect($response->streamedContent())
        ->toContain('Admission Number')
        ->toContain('Class (Name or ID)');
});

it('downloads template when session and class are selected without class arm', function () {
    $response = get(route('students.bulk.template', [
        'session_id' => $this->session->id,
        'class_id' => $this->class->id,
    ]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/csv');

    $content = $response->streamedContent();
    $lines = array_values(array_filter(array_map('trim', explode("\n", $content))));
    $headerLine = collect($lines)->first(fn ($line) => str_contains($line, 'First Name'));
    $headerColumns = $headerLine ? str_getcsv($headerLine) : [];

    expect($content)
        ->toContain('Session: 2025/2026')
        ->toContain('Class: Grade 6');
    expect($headerColumns)
        ->toContain('Class Arm')
        ->not->toContain('Session')
        ->not->toContain('Term')
        ->not->toContain('Class');
});

it('validates and commits a bulk student upload', function () {
    $csv = implode("\n", [
        'Admission Number,First Name,Middle Name,Last Name,Gender (M/F/O),Date of Birth (YYYY-MM-DD),Admission Date (YYYY-MM-DD),Status (active/inactive/graduated/withdrawn),Student Nationality,Student State of Origin,Student LGA,House,Club,Student Address,Medical Information,Session (Name or ID),Term (Name or ID),Class (Name or ID),Class Arm (Name or ID),Class Section (Name or ID),Parent First Name,Parent Last Name,Parent Email,Parent Phone,Parent Address,Parent Occupation,Parent Nationality,Parent State of Origin,Parent LGA',
        '2025/010,Chinedu,,Okafor,M,2012-01-02,2024-09-01,active,Nigerian,Anambra,Onitsha,Red,Music,12 Unity Close,Asthma,2025/2026,First Term,Grade 6,Arm B,Section Blue,Grace,Okafor,grace.okafor@example.test,08020000000,Market Road,Trader,Nigerian,Anambra,Onitsha',
    ]);

    $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

    $previewResponse = post(route('students.bulk.preview'), [
        'file' => $file,
    ]);

    $previewResponse->assertOk()
        ->assertJsonPath('summary.total_rows', 1)
        ->assertJsonCount(1, 'preview_rows');

    $batchId = $previewResponse->json('batch_id');

    expect(BulkUploadBatch::find($batchId))->not()->toBeNull();

    $commitResponse = postJson(route('students.bulk.commit', $batchId));
    $commitResponse->assertOk()
        ->assertJsonPath('summary.total_processed', 1);

    expect(Student::where('school_id', $this->school->id)->count())->toBe(1);
    expect(StudentEnrollment::count())->toBe(1);
});

it('does not require parent details during bulk student upload', function () {
    $csv = implode("\n", [
        'Admission Number,First Name,Middle Name,Last Name,Gender (M/F/O),Date of Birth (YYYY-MM-DD),Admission Date (YYYY-MM-DD),Status (active/inactive/graduated/withdrawn),Student Nationality,Student State of Origin,Student LGA,House,Club,Student Address,Medical Information,Session (Name or ID),Term (Name or ID),Class (Name or ID),Class Arm (Name or ID),Class Section (Name or ID),Parent First Name,Parent Last Name,Parent Email,Parent Phone,Parent Address,Parent Occupation,Parent Nationality,Parent State of Origin,Parent LGA',
        '2025/012,Zainab,,Musa,F,2012-05-06,2024-09-01,active,Nigerian,Kaduna,Kaduna North,Blue,Drama,22 Unity Close,,2025/2026,First Term,Grade 6,Arm B,Section Blue,Aisha,, ,08030000000,Central Road,Engineer,Nigerian,Kaduna,Kaduna North',
    ]);

    $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

    $previewResponse = post(route('students.bulk.preview'), [
        'file' => $file,
    ]);

    $previewResponse->assertOk()
        ->assertJsonPath('summary.total_rows', 1)
        ->assertJsonPath('preview_rows.0.parent_email', '—');

    $commitResponse = postJson(route('students.bulk.commit', $previewResponse->json('batch_id')));
    $commitResponse->assertOk()
        ->assertJsonPath('summary.total_processed', 1);

    $student = Student::where('school_id', $this->school->id)
        ->where('admission_no', '2025/012')
        ->first();

    expect($student)->not()->toBeNull();
    expect($student?->parent_id)->toBeNull();
});

it('validates and commits an xlsx bulk student upload', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('PHP zip extension is required to generate XLSX test files.');
    }

    $blankLookingAdmissionNo = html_entity_decode('&nbsp;', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $zeroWidthAdmissionNo = "\u{200B}";

    $rows = [
        [
            'Admission Number',
            'First Name',
            'Middle Name',
            'Last Name',
            'Gender (M/F/O)',
            'Date of Birth (YYYY-MM-DD)',
            'Admission Date (YYYY-MM-DD)',
            'Status (active/inactive/graduated/withdrawn)',
            'Student Nationality',
            'Student State of Origin',
            'Student LGA',
            'House',
            'Club',
            'Student Address',
            'Medical Information',
            'Session (Name or ID)',
            'Term (Name or ID)',
            'Class (Name or ID)',
            'Class Arm (Name or ID)',
            'Class Section (Name or ID)',
            'Parent First Name',
            'Parent Last Name',
            'Parent Email',
            'Parent Phone',
            'Parent Address',
            'Parent Occupation',
            'Parent Nationality',
            'Parent State of Origin',
            'Parent LGA',
        ],
        [
            '2025/011',
            'Amina',
            '',
            'Bello',
            'F',
            '2012-03-04',
            '2024-09-01',
            'active',
            'Nigerian',
            'Kano',
            'Kano Municipal',
            'Blue',
            'Drama',
            '22 Unity Close',
            '',
            '2025/2026',
            'First Term',
            'Grade 6',
            'Arm B',
            'Section Blue',
            'Maryam',
            'Bello',
            'maryam.bello@example.test',
            '08030000000',
            'Central Road',
            'Engineer',
            'Nigerian',
            'Kano',
            'Kano Municipal',
        ],
    ];

    $file = UploadedFile::fake()->createWithContent('students.xlsx', buildStudentBulkXlsx($rows));

    $previewResponse = post(route('students.bulk.preview'), [
        'file' => $file,
    ]);

    $previewResponse->assertOk()
        ->assertJsonPath('summary.total_rows', 1)
        ->assertJsonPath('preview_rows.0.name', 'Amina Bello');

    $commitResponse = postJson(route('students.bulk.commit', $previewResponse->json('batch_id')));
    $commitResponse->assertOk()
        ->assertJsonPath('summary.total_processed', 1);

    expect(Student::where('school_id', $this->school->id)->where('admission_no', '2025/011')->exists())->toBeTrue();
});

it('auto-generates admission numbers for xlsx rows without admission numbers', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('PHP zip extension is required to generate XLSX test files.');
    }

    $rows = [
        [
            'Admission No',
            'First Name',
            'Middle Name',
            'Last Name',
            'Gender (M/F/O)',
            'Date of Birth (YYYY-MM-DD)',
            'Admission Date (YYYY-MM-DD)',
            'Status (active/inactive/graduated/withdrawn)',
            'Student Nationality',
            'Student State of Origin',
            'Student LGA',
            'House',
            'Club',
            'Student Address',
            'Medical Information',
            'Session (Name or ID)',
            'Term (Name or ID)',
            'Class (Name or ID)',
            'Class Arm (Name or ID)',
            'Class Section (Name or ID)',
            'Parent First Name',
            'Parent Last Name',
            'Parent Email',
            'Parent Phone',
            'Parent Address',
            'Parent Occupation',
            'Parent Nationality',
            'Parent State of Origin',
            'Parent LGA',
        ],
        [
            $blankLookingAdmissionNo,
            'Fatima',
            '',
            'Garba',
            'F',
            '2012-07-08',
            '2024-09-01',
            'active',
            'Nigerian',
            'Katsina',
            'Daura',
            'Green',
            'Press',
            '18 School Road',
            '',
            '2025/2026',
            'First Term',
            'Grade 6',
            'Arm B',
            'Section Blue',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
        ],
        [
            $zeroWidthAdmissionNo,
            'Umar',
            '',
            'Sani',
            'M',
            '2012-09-10',
            '2024-09-01',
            'active',
            'Nigerian',
            'Katsina',
            'Daura',
            'Green',
            'Press',
            '19 School Road',
            '',
            '2025/2026',
            'First Term',
            'Grade 6',
            'Arm B',
            'Section Blue',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
        ],
    ];

    $file = UploadedFile::fake()->createWithContent('students.xlsx', buildStudentBulkXlsx($rows));

    $previewResponse = post(route('students.bulk.preview'), [
        'file' => $file,
    ]);

    $previewResponse->assertOk()
        ->assertJsonPath('summary.total_rows', 2)
        ->assertJsonPath('preview_rows.0.admission_no', 'Auto-generated')
        ->assertJsonPath('preview_rows.1.admission_no', 'Auto-generated');

    $commitResponse = postJson(route('students.bulk.commit', $previewResponse->json('batch_id')));
    $commitResponse->assertOk()
        ->assertJsonPath('summary.total_processed', 2);

    $student = Student::where('school_id', $this->school->id)
        ->where('first_name', 'Fatima')
        ->where('last_name', 'Garba')
        ->first();

    expect($student)->not()->toBeNull();
    expect($student?->admission_no)->not()->toBe('');
    expect($student?->admission_no)->not()->toBeNull();

    expect(Student::where('school_id', $this->school->id)
        ->where('first_name', 'Umar')
        ->where('last_name', 'Sani')
        ->whereNotNull('admission_no')
        ->exists())->toBeTrue();
});

it('returns all validated rows in the bulk upload preview', function () {
    $rows = [
        'Admission Number,First Name,Middle Name,Last Name,Gender (M/F/O),Date of Birth (YYYY-MM-DD),Admission Date (YYYY-MM-DD),Status (active/inactive/graduated/withdrawn),Student Nationality,Student State of Origin,Student LGA,House,Club,Student Address,Medical Information,Session (Name or ID),Term (Name or ID),Class (Name or ID),Class Arm (Name or ID),Class Section (Name or ID),Parent First Name,Parent Last Name,Parent Email,Parent Phone,Parent Address,Parent Occupation,Parent Nationality,Parent State of Origin,Parent LGA',
    ];

    for ($index = 1; $index <= 12; $index++) {
        $rows[] = implode(',', [
            sprintf('2025/%03d', $index),
            "Student{$index}",
            '',
            "Lastname{$index}",
            'M',
            '2012-01-02',
            '2024-09-01',
            'active',
            'Nigerian',
            'Anambra',
            'Onitsha',
            'Red',
            'Music',
            "{$index} Unity Close",
            '',
            '2025/2026',
            'First Term',
            'Grade 6',
            'Arm B',
            'Section Blue',
            "Parent{$index}",
            "Guardian{$index}",
            "parent{$index}@example.test",
            '08020000000',
            'Market Road',
            'Trader',
            'Nigerian',
            'Anambra',
            'Onitsha',
        ]);
    }

    $file = UploadedFile::fake()->createWithContent('students.csv', implode("\n", $rows));

    $previewResponse = post(route('students.bulk.preview'), [
        'file' => $file,
    ]);

    $previewResponse->assertOk()
        ->assertJsonPath('summary.total_rows', 12)
        ->assertJsonCount(12, 'preview_rows');
});

function buildStudentBulkXlsx(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'student-bulk-xlsx-');
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets><sheet name="Students" sheetId="1" r:id="rId1"/></sheets>
</workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>');
    $zip->addFromString('xl/worksheets/sheet1.xml', buildStudentBulkWorksheetXml($rows));
    $zip->close();

    $contents = file_get_contents($path);
    unlink($path);

    return $contents ?: '';
}

function buildStudentBulkWorksheetXml(array $rows): string
{
    $xmlRows = [];
    foreach ($rows as $rowIndex => $row) {
        $cells = [];
        foreach (array_values($row) as $columnIndex => $value) {
            $reference = xlsxColumnName($columnIndex + 1) . ($rowIndex + 1);
            $escaped = htmlspecialchars((string) $value, ENT_XML1);
            $cells[] = "<c r=\"{$reference}\" t=\"inlineStr\"><is><t>{$escaped}</t></is></c>";
        }
        $rowNumber = $rowIndex + 1;
        $xmlRows[] = "<row r=\"{$rowNumber}\">" . implode('', $cells) . '</row>';
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>' . implode('', $xmlRows) . '</sheetData>
</worksheet>';
}

function xlsxColumnName(int $columnNumber): string
{
    $name = '';
    while ($columnNumber > 0) {
        $remainder = ($columnNumber - 1) % 26;
        $name = chr(65 + $remainder) . $name;
        $columnNumber = intdiv($columnNumber - 1, 26);
    }

    return $name;
}

it('returns a friendly duplicate admission number message during commit', function () {
    Student::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'admission_no' => '22622',
        'first_name' => 'Existing',
        'middle_name' => '',
        'last_name' => 'Student',
        'gender' => 'M',
        'date_of_birth' => '2011-08-26',
        'admission_date' => '2026-04-14',
        'status' => 'active',
        'nationality' => 'Nigerian',
        'state_of_origin' => 'Niger',
        'lga_of_origin' => 'Bida',
        'house' => null,
        'club' => null,
        'address' => '',
        'medical_information' => '',
        'current_session_id' => $this->session->id,
        'current_term_id' => $this->term->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => null,
        'class_section_id' => $this->section->id,
        'parent_id' => null,
        'portal_password' => '123456',
    ]);

    $csv = implode("\n", [
        'Admission Number,First Name,Middle Name,Last Name,Gender (M/F/O),Date of Birth (YYYY-MM-DD),Admission Date (YYYY-MM-DD),Status (active/inactive/graduated/withdrawn),Student Nationality,Student State of Origin,Student LGA,House,Club,Student Address,Medical Information,Session (Name or ID),Term (Name or ID),Class (Name or ID),Class Arm (Name or ID),Class Section (Name or ID),Parent First Name,Parent Last Name,Parent Email,Parent Phone,Parent Address,Parent Occupation,Parent Nationality,Parent State of Origin,Parent LGA',
        '22622,ABDULKADIR,,MUHAMMAD,M,2011-08-26,2026-04-14,active,Nigerian,Niger,Bida,,, ,,2025/2026,First Term,Grade 6,Arm B,Section Blue,Grace,Okafor,grace.okafor@example.test,08020000000,Market Road,Trader,Nigerian,Niger,Bida',
    ]);

    $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

    $previewResponse = post(route('students.bulk.preview'), [
        'file' => $file,
    ]);

    $batchId = $previewResponse->json('batch_id');

    $commitResponse = postJson(route('students.bulk.commit', $batchId), [
        'decisions' => [
            4 => 'allow',
        ],
    ]);

    $commitResponse->assertStatus(422)
        ->assertJsonPath('errors.0.column', 'Admission Number');

    expect($commitResponse->json('message'))
        ->toContain('CSV row')
        ->toContain('Admission number 22622 is already used')
        ->toContain('Grade 6 / None')
        ->toContain('Grade 6 / Arm B');
});

it('commits successfully when admission number is corrected during confirm', function () {
    Student::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'admission_no' => '22622',
        'first_name' => 'Existing',
        'middle_name' => '',
        'last_name' => 'Student',
        'gender' => 'M',
        'date_of_birth' => '2011-08-26',
        'admission_date' => '2026-04-14',
        'status' => 'active',
        'nationality' => 'Nigerian',
        'state_of_origin' => 'Niger',
        'lga_of_origin' => 'Bida',
        'house' => null,
        'club' => null,
        'address' => '',
        'medical_information' => '',
        'current_session_id' => $this->session->id,
        'current_term_id' => $this->term->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => null,
        'class_section_id' => $this->section->id,
        'parent_id' => null,
        'portal_password' => '123456',
    ]);

    $csv = implode("\n", [
        'Admission Number,First Name,Middle Name,Last Name,Gender (M/F/O),Date of Birth (YYYY-MM-DD),Admission Date (YYYY-MM-DD),Status (active/inactive/graduated/withdrawn),Student Nationality,Student State of Origin,Student LGA,House,Club,Student Address,Medical Information,Session (Name or ID),Term (Name or ID),Class (Name or ID),Class Arm (Name or ID),Class Section (Name or ID),Parent First Name,Parent Last Name,Parent Email,Parent Phone,Parent Address,Parent Occupation,Parent Nationality,Parent State of Origin,Parent LGA',
        '22622,ABDULKADIR,,MUHAMMAD,M,2011-08-26,2026-04-14,active,Nigerian,Niger,Bida,,, ,,2025/2026,First Term,Grade 6,Arm B,Section Blue,Grace,Okafor,grace.okafor@example.test,08020000000,Market Road,Trader,Nigerian,Niger,Bida',
    ]);

    $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

    $previewResponse = post(route('students.bulk.preview'), [
        'file' => $file,
    ]);

    $batchId = $previewResponse->json('batch_id');
    $rowNumber = (string) $previewResponse->json('preview_rows.0.source_row');

    $commitResponse = postJson(route('students.bulk.commit', $batchId), [
        'decisions' => [
            $rowNumber => 'allow',
        ],
        'row_updates' => [
            $rowNumber => [
                'admission_no' => '22623',
            ],
        ],
    ]);

    $commitResponse->assertOk()
        ->assertJsonPath('summary.total_processed', 1);

    expect(Student::where('school_id', $this->school->id)->where('admission_no', '22623')->exists())->toBeTrue();
});

it('returns both csv rows when the file contains duplicate admission numbers', function () {
    $csv = implode("\n", [
        'Admission Number,First Name,Middle Name,Last Name,Gender (M/F/O),Date of Birth (YYYY-MM-DD),Admission Date (YYYY-MM-DD),Status (active/inactive/graduated/withdrawn),Student Nationality,Student State of Origin,Student LGA,House,Club,Student Address,Medical Information,Session (Name or ID),Term (Name or ID),Class (Name or ID),Class Arm (Name or ID),Class Section (Name or ID),Parent First Name,Parent Last Name,Parent Email,Parent Phone,Parent Address,Parent Occupation,Parent Nationality,Parent State of Origin,Parent LGA',
        '22622,ABDULKADIR,,MUHAMMAD,M,2011-08-26,2026-04-14,active,Nigerian,Niger,Bida,,, ,,2025/2026,First Term,Grade 6,Arm B,Section Blue,Grace,Okafor,grace1.okafor@example.test,08020000000,Market Road,Trader,Nigerian,Niger,Bida',
        '22622,ABDULKADIR,,SHEHU,M,2011-02-14,2026-04-14,active,Nigerian,Niger,Bida,,, ,,2025/2026,First Term,Grade 6,Arm B,Section Blue,Grace,Okafor,grace2.okafor@example.test,08020000001,Market Road,Trader,Nigerian,Niger,Bida',
    ]);

    $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

    $response = post(route('students.bulk.preview'), ['file' => $file]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.0.column', 'Admission Number')
        ->assertJsonCount(2, 'preview_rows');

    expect($response->json('message'))
        ->toContain('This CSV contains two students with admission number 22622')
        ->toContain('ABDULKADIR MUHAMMAD')
        ->toContain('ABDULKADIR SHEHU');
});

it('returns validation errors with downloadable csv when data is invalid', function () {
    $csv = implode("\n", [
        'Admission Number,First Name,Last Name,Gender (M/F/O),Date of Birth (YYYY-MM-DD),Admission Date (YYYY-MM-DD),Status (active/inactive/graduated/withdrawn),Session (Name or ID),Term (Name or ID),Class (Name or ID),Class Arm (Name or ID),Parent First Name,Parent Last Name,Parent Email',
        '2025/022,Ada,Okoh,F,2013-05-01,2024-09-01,active,Invalid Session,First Term,Grade 6,Arm B,Grace,Okoh,grace.okoh@example.test',
    ]);

    $file = UploadedFile::fake()->createWithContent('invalid.csv', $csv);

    $response = post(route('students.bulk.preview'), ['file' => $file]);

    $response->assertStatus(422)
        ->assertJsonStructure([
            'message',
            'errors' => [
                ['row', 'column', 'message'],
            ],
            'error_csv',
        ]);
});
