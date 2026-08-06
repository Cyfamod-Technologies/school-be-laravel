<?php

use App\Models\School;
use App\Models\SchoolParent;
use App\Models\Session;
use App\Models\Term;
use App\Models\SchoolClass;
use App\Models\ClassArm;
use App\Models\ClassSection;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->school = School::factory()->create();

    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'role' => 'admin',
        'status' => 'active',
    ]);

    Sanctum::actingAs($this->admin, [], 'sanctum');
});

it('allows creating a parent record from an existing staff email in the same school', function () {
    $staffUser = User::factory()->create([
        'school_id' => $this->school->id,
        'role' => 'staff',
        'status' => 'active',
        'email' => 'staff.parent@example.test',
    ]);

    $response = postJson('/api/v1/parents', [
        'first_name' => 'Grace',
        'last_name' => 'Okafor',
        'phone' => '08020000000',
        'email' => 'staff.parent@example.test',
        'address' => 'Market Road',
        'occupation' => 'Teacher',
    ]);

    $response->assertCreated()
        ->assertJsonPath('user_id', $staffUser->id);

    expect(SchoolParent::query()
        ->where('school_id', $this->school->id)
        ->where('user_id', $staffUser->id)
        ->exists())->toBeTrue();
});

it('allows bulk upload to link a parent using an existing staff email in the same school', function () {
    User::factory()->create([
        'school_id' => $this->school->id,
        'role' => 'staff',
        'status' => 'active',
        'email' => 'staff.parent@example.test',
    ]);

    $session = Session::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => '2025/2026',
        'slug' => '2025-2026',
        'start_date' => now()->subMonths(2),
        'end_date' => now()->addMonths(10),
        'status' => 'active',
    ]);

    $term = Term::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'session_id' => $session->id,
        'name' => 'First Term',
        'slug' => 'first-term',
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonths(3),
        'status' => 'active',
    ]);

    $class = SchoolClass::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => 'Grade 6',
        'slug' => 'grade-6',
    ]);

    $arm = ClassArm::create([
        'id' => (string) Str::uuid(),
        'school_class_id' => $class->id,
        'name' => 'Arm B',
        'slug' => 'arm-b',
    ]);

    $section = ClassSection::create([
        'id' => (string) Str::uuid(),
        'class_arm_id' => $arm->id,
        'name' => 'Section Blue',
        'slug' => 'section-blue',
    ]);

    $csv = implode("\n", [
        'Admission Number,First Name,Middle Name,Last Name,Gender (M/F/O),Date of Birth (YYYY-MM-DD),Admission Date (YYYY-MM-DD),Status (active/inactive/graduated/withdrawn),Student Nationality,Student State of Origin,Student LGA,House,Club,Student Address,Medical Information,Session (Name or ID),Term (Name or ID),Class (Name or ID),Class Arm (Name or ID),Class Section (Name or ID),Parent First Name,Parent Last Name,Parent Email,Parent Phone,Parent Address,Parent Occupation,Parent Nationality,Parent State of Origin,Parent LGA',
        '2025/010,Chinedu,,Okafor,M,2012-01-02,2024-09-01,active,Nigerian,Anambra,Onitsha,Red,Music,12 Unity Close,Asthma,2025/2026,First Term,Grade 6,Arm B,Section Blue,Grace,Okafor,staff.parent@example.test,08020000000,Market Road,Teacher,Nigerian,Anambra,Onitsha',
    ]);

    $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

    $previewResponse = post('/api/v1/students/bulk/preview', [
        'file' => $file,
    ]);

    $previewResponse->assertOk();

    $batchId = $previewResponse->json('batch_id');

    $commitResponse = postJson("/api/v1/students/bulk/{$batchId}/commit");
    $commitResponse->assertOk()
        ->assertJsonPath('summary.total_processed', 1);

    $student = Student::query()
        ->where('school_id', $this->school->id)
        ->where('admission_no', '2025/010')
        ->first();

    expect($student)->not->toBeNull();
    expect($student?->parent)->not->toBeNull();
    expect($student?->parent?->user?->email)->toBe('staff.parent@example.test');
});
