<?php

use App\Models\ClassArm;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolParent;
use App\Models\Session;
use App\Models\SkillCategory;
use App\Models\SkillRating;
use App\Models\SkillType;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    $this->school = School::factory()->create([
        'status' => 'active',
    ]);

    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'role' => 'admin',
        'status' => 'active',
    ]);

    Sanctum::actingAs($this->user, [], 'sanctum');

    $this->session = Session::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => '2024/2025 Session',
        'slug' => '2024-2025-session',
        'start_date' => Carbon::parse('2024-09-01'),
        'end_date' => Carbon::parse('2025-07-31'),
        'status' => 'active',
    ]);

    $this->openTerm = Term::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'session_id' => $this->session->id,
        'name' => 'First Term',
        'slug' => 'first-term',
        'start_date' => Carbon::parse('2024-09-01'),
        'end_date' => Carbon::now()->addWeeks(4),
        'status' => 'active',
    ]);

    $this->closedTerm = Term::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'session_id' => $this->session->id,
        'name' => 'Second Term',
        'slug' => 'second-term',
        'start_date' => Carbon::parse('2025-01-10'),
        'end_date' => Carbon::now()->subWeek(),
        'status' => 'completed',
    ]);

    $this->skillCategory = SkillCategory::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => 'Behaviour',
    ]);

    $this->skillType = SkillType::create([
        'id' => (string) Str::uuid(),
        'skill_category_id' => $this->skillCategory->id,
        'school_id' => $this->school->id,
        'name' => 'Neatness',
    ]);

    $this->alternateSkillType = SkillType::create([
        'id' => (string) Str::uuid(),
        'skill_category_id' => $this->skillCategory->id,
        'school_id' => $this->school->id,
        'name' => 'Punctuality',
    ]);

    $this->class = SchoolClass::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => 'Grade 5',
        'slug' => 'grade-5',
    ]);

    $this->classArm = ClassArm::create([
        'id' => (string) Str::uuid(),
        'school_class_id' => $this->class->id,
        'name' => 'Arm A',
        'slug' => 'arm-a',
    ]);

    $this->parentUser = User::factory()->create([
        'school_id' => $this->school->id,
        'role' => 'parent',
        'status' => 'active',
    ]);

    $this->parent = SchoolParent::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'user_id' => $this->parentUser->id,
        'first_name' => 'Parent',
        'last_name' => 'Example',
    ]);

    $this->student = Student::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'admission_no' => '2024/001',
        'first_name' => 'John',
        'middle_name' => 'T.',
        'last_name' => 'Doe',
        'gender' => 'M',
        'date_of_birth' => Carbon::parse('2012-05-12'),
        'nationality' => 'Nigerian',
        'state_of_origin' => 'Lagos',
        'lga_of_origin' => 'Ikeja',
        'house' => 'Red',
        'club' => 'Art Club',
        'current_session_id' => $this->session->id,
        'current_term_id' => $this->openTerm->id,
        'school_class_id' => $this->class->id,
        'class_arm_id' => $this->classArm->id,
        'parent_id' => $this->parent->id,
        'admission_date' => Carbon::parse('2022-09-10'),
        'status' => 'active',
    ]);
});

it('lists skill ratings for a student and term', function () {
    SkillRating::create([
        'id' => (string) Str::uuid(),
        'student_id' => $this->student->id,
        'session_id' => $this->session->id,
        'term_id' => $this->openTerm->id,
        'skill_type_id' => $this->skillType->id,
        'rating_value' => 5,
    ]);

    getJson(route('students.skill-ratings.index', [
        'student' => $this->student->id,
        'session_id' => $this->session->id,
        'term_id' => $this->openTerm->id,
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.skill_type.name', 'Neatness');
});

it('lists available skill types for the student', function () {
    getJson(route('students.skill-ratings.types', [
        'student' => $this->student->id,
    ]))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Neatness');
});

it('hides class-scoped skills from other classes', function () {
    $otherClass = SchoolClass::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'name' => 'Grade 6',
        'slug' => 'grade-6',
    ]);

    $otherCategory = SkillCategory::create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'school_class_id' => $otherClass->id,
        'name' => 'Senior Skills',
    ]);

    SkillType::create([
        'id' => (string) Str::uuid(),
        'skill_category_id' => $otherCategory->id,
        'school_id' => $this->school->id,
        'school_class_id' => $otherClass->id,
        'name' => 'Debate',
    ]);

    $this->school->update([
        'skill_categories_separate_by_class' => true,
        'skill_types_separate_by_class' => true,
    ]);

    getJson(route('students.skill-ratings.types', [
        'student' => $this->student->id,
    ]))
        ->assertOk()
        ->assertJsonFragment(['name' => 'Neatness'])
        ->assertJsonMissing(['name' => 'Debate']);
});

it('creates a new skill rating for a student', function () {
    postJson(route('students.skill-ratings.store', [
        'student' => $this->student->id,
    ]), [
        'session_id' => $this->session->id,
        'term_id' => $this->openTerm->id,
        'skill_type_id' => $this->skillType->id,
        'rating_value' => 4,
    ])
        ->assertCreated()
        ->assertJsonPath('data.rating_value', 4);

    expect(SkillRating::where('student_id', $this->student->id)->count())->toBe(1);
});

it('prevents duplicate skill ratings for the same term', function () {
    SkillRating::create([
        'id' => (string) Str::uuid(),
        'student_id' => $this->student->id,
        'session_id' => $this->session->id,
        'term_id' => $this->openTerm->id,
        'skill_type_id' => $this->skillType->id,
        'rating_value' => 3,
    ]);

    postJson(route('students.skill-ratings.store', [
        'student' => $this->student->id,
    ]), [
        'session_id' => $this->session->id,
        'term_id' => $this->openTerm->id,
        'skill_type_id' => $this->skillType->id,
        'rating_value' => 4,
    ])->assertStatus(409);
});

it('updates a skill rating while the term is active', function () {
    $rating = SkillRating::create([
        'id' => (string) Str::uuid(),
        'student_id' => $this->student->id,
        'session_id' => $this->session->id,
        'term_id' => $this->openTerm->id,
        'skill_type_id' => $this->skillType->id,
        'rating_value' => 3,
    ]);

    putJson(route('students.skill-ratings.update', [
        'student' => $this->student->id,
        'skillRating' => $rating->id,
    ]), [
        'rating_value' => 5,
    ])
        ->assertOk()
        ->assertJsonPath('data.rating_value', 5);
});

it('blocks updates when the term has ended', function () {
    $rating = SkillRating::create([
        'id' => (string) Str::uuid(),
        'student_id' => $this->student->id,
        'session_id' => $this->session->id,
        'term_id' => $this->closedTerm->id,
        'skill_type_id' => $this->alternateSkillType->id,
        'rating_value' => 2,
    ]);

    putJson(route('students.skill-ratings.update', [
        'student' => $this->student->id,
        'skillRating' => $rating->id,
    ]), [
        'rating_value' => 3,
    ])->assertStatus(422);
});

it('removes a skill rating while the term is active', function () {
    $rating = SkillRating::create([
        'id' => (string) Str::uuid(),
        'student_id' => $this->student->id,
        'session_id' => $this->session->id,
        'term_id' => $this->openTerm->id,
        'skill_type_id' => $this->skillType->id,
        'rating_value' => 4,
    ]);

    deleteJson(route('students.skill-ratings.destroy', [
        'student' => $this->student->id,
        'skillRating' => $rating->id,
    ]))->assertNoContent();

    expect(SkillRating::where('id', $rating->id)->exists())->toBeFalse();
});
