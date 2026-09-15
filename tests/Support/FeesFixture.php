<?php

namespace Tests\Support;

use App\Models\ClassArm;
use App\Models\FeeItem;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Session;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Builds the school the fees specification uses in its worked example: JSS 2
 * with arms A and B, one student in each, and a fee-item catalogue to assign
 * from.
 */
class FeesFixture
{
    /**
     * @return array<string, mixed>
     */
    public static function make(): array
    {
        $school = School::factory()->create();

        $admin = User::factory()->create([
            'school_id' => $school->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        $session = Session::create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'name' => '2026/2027',
            'slug' => '2026-2027',
            'start_date' => Carbon::parse('2026-09-01'),
            'end_date' => Carbon::parse('2027-07-31'),
            'status' => 'active',
        ]);

        $term = Term::create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'session_id' => $session->id,
            'name' => 'First Term',
            'slug' => 'first-term',
            'start_date' => Carbon::parse('2026-09-01'),
            'end_date' => Carbon::parse('2026-12-15'),
            'status' => 'active',
        ]);

        $school->forceFill([
            'current_session_id' => $session->id,
            'current_term_id' => $term->id,
        ])->save();

        $jss2 = SchoolClass::create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'name' => 'JSS 2',
            'slug' => 'jss-2',
        ]);

        $jss3 = SchoolClass::create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'name' => 'JSS 3',
            'slug' => 'jss-3',
        ]);

        $armA = ClassArm::create([
            'id' => (string) Str::uuid(),
            'school_class_id' => $jss2->id,
            'name' => 'A',
            'slug' => 'a',
        ]);

        $armB = ClassArm::create([
            'id' => (string) Str::uuid(),
            'school_class_id' => $jss2->id,
            'name' => 'B',
            'slug' => 'b',
        ]);

        $jss3Arm = ClassArm::create([
            'id' => (string) Str::uuid(),
            'school_class_id' => $jss3->id,
            'name' => 'A',
            'slug' => 'a',
        ]);

        return [
            'school' => $school,
            'admin' => $admin,
            'session' => $session,
            'term' => $term,
            'jss2' => $jss2,
            'jss3' => $jss3,
            'armA' => $armA,
            'armB' => $armB,
            'jss3Arm' => $jss3Arm,
            'john' => self::student($school, $session, $term, $jss2, $armA, 'John', 'Doe'),
            'mary' => self::student($school, $session, $term, $jss2, $armB, 'Mary', 'James'),
            'david' => self::student($school, $session, $term, $jss3, $jss3Arm, 'David', 'Okoro'),
        ];
    }

    public static function student(
        School $school,
        Session $session,
        Term $term,
        SchoolClass $class,
        ClassArm $arm,
        string $firstName,
        string $lastName,
        string $status = 'active',
    ): Student {
        return Student::create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'admission_no' => 'ADM-'.Str::upper(Str::random(8)),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => 'M',
            'date_of_birth' => Carbon::parse('2014-05-16'),
            'current_session_id' => $session->id,
            'current_term_id' => $term->id,
            'school_class_id' => $class->id,
            'class_arm_id' => $arm->id,
            'admission_date' => Carbon::parse('2020-09-10'),
            'status' => $status,
        ]);
    }

    public static function feeItem(School $school, string $name, string $amount = '0'): FeeItem
    {
        return FeeItem::create([
            'school_id' => $school->id,
            'name' => $name,
            'default_amount' => $amount,
            'is_active' => true,
        ]);
    }
}
