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
        ->assertJsonPath('summary.total_rows', 1);

    $batchId = $previewResponse->json('batch_id');

    expect(BulkUploadBatch::find($batchId))->not()->toBeNull();

    $commitResponse = postJson(route('students.bulk.commit', $batchId));
    $commitResponse->assertOk()
        ->assertJsonPath('summary.total_processed', 1);

    expect(Student::where('school_id', $this->school->id)->count())->toBe(1);
    expect(StudentEnrollment::count())->toBe(1);
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
