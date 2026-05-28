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
        ->toContain('Admission number 22622 is already used')
        ->toContain('Grade 6 / None')
        ->toContain('Grade 6 / Arm B');
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
