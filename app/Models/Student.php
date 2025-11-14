<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\BloodGroup;
use RuntimeException;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;

/**
 * Class Student
 *
 * @property string $id
 * @property string $school_id
 * @property string $admission_no
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string $gender
 * @property Carbon $date_of_birth
 * @property string|null $nationality
 * @property string|null $state_of_origin
 * @property string|null $lga_of_origin
 * @property string|null $house
 * @property string|null $club
 * @property string $current_session_id
 * @property string $current_term_id
 * @property string $school_class_id
 * @property string $class_arm_id
 * @property string|null $class_section_id
 * @property string|null $parent_id
 * @property Carbon $admission_date
 * @property string|null $photo_url
 * @property string $status
 * @property string|null $address
 * @property string|null $medical_information
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property SchoolClass $school_class
 * @property ClassArm $class_arm
 * @property ClassSection|null $class_section
 * @property SchoolParent|null $parent
 * @property School $school
 * @property Session $session
 * @property Term $term
 * @property Collection|Attendance[] $attendances
 * @property Collection|FeePayment[] $fee_payments
 * @property Collection|PerformanceReport[] $performance_reports
 * @property Collection|ResultPin[] $result_pins
 * @property Collection|Result[] $results
 * @property Collection|SkillRating[] $skill_ratings
 * @property Collection|StudentEnrollment[] $student_enrollments
 * @property Collection|TermSummary[] $term_summaries
 *
 * @package App\Models
 */
class Student extends Model implements AuthenticatableContract
{
    use HasApiTokens, AuthenticatableTrait;

    protected $table = 'students';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'date_of_birth' => 'datetime',
        'admission_date' => 'datetime',
    ];

    protected $fillable = [
        'id',
        'school_id',
        'admission_no',
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'date_of_birth',
        'nationality',
        'state_of_origin',
		'lga_of_origin',
		'blood_group_id',
        'house',
        'club',
        'current_session_id',
        'current_term_id',
        'school_class_id',
        'class_arm_id',
        'class_section_id',
		'parent_id',
		'blood_group_id',
        'admission_date',
        'photo_url',
        'status',
        'address',
        'medical_information',
        'portal_password',
        'portal_password_changed_at',
    ];

    protected $hidden = [
        'portal_password',
    ];

    protected static array $uuidForeignKeys = [
        'school_id',
        'current_session_id',
        'current_term_id',
        'school_class_id',
        'class_arm_id',
        'class_section_id',
        'parent_id',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('validUuids', function ($builder) {
            $builder->whereRaw('(class_arm_id IS NULL OR CHAR_LENGTH(CAST(class_arm_id AS CHAR)) = 36)')
                ->whereRaw('(class_section_id IS NULL OR CHAR_LENGTH(CAST(class_section_id AS CHAR)) = 36)')
                ->whereRaw('(parent_id IS NULL OR CHAR_LENGTH(CAST(parent_id AS CHAR)) = 36)')
                ->whereRaw('(school_class_id IS NULL OR CHAR_LENGTH(CAST(school_class_id AS CHAR)) = 36)')
                ->whereRaw('(current_session_id IS NULL OR CHAR_LENGTH(CAST(current_session_id AS CHAR)) = 36)')
                ->whereRaw('(current_term_id IS NULL OR CHAR_LENGTH(CAST(current_term_id AS CHAR)) = 36)');
        });

        static::retrieved(function (Student $student): void {
            $student->sanitizeUuidForeignKeys();
        });

        static::saving(function (Student $student): void {
            $student->sanitizeUuidForeignKeys();
        });
    }

    public function getGenderAttribute($value)
    {
        return match ($value) {
            'M' => 'Male',
            'F' => 'Female',
            'O' => 'Others',
            null => null,
            default => $value,
        };
    }

	public function setGenderAttribute($value): void
	{
		if ($value === null) {
			$this->attributes['gender'] = null;
			return;
		}

		$normalized = strtolower((string) $value);
		$map = [
			'male' => 'M',
			'm' => 'M',
			'female' => 'F',
			'f' => 'F',
			'other' => 'O',
			'others' => 'O',
			'o' => 'O',
		];

		$this->attributes['gender'] = $map[$normalized] ?? $value;
	}

	public function getPhotoUrlAttribute($value)
	{
		if ($value === null || $value === '') {
			return null;
		}

		if (Str::startsWith($value, ['http://', 'https://'])) {
			return $value;
		}

		$appUrl = rtrim(config('app.url'), '/');

		if (Str::startsWith($value, '/storage/')) {
			return $appUrl . $value;
		}

		return $appUrl . Storage::url($value);
    }

    protected function normalizeNullableUuid($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        if (in_array($trimmed, ['', '0'], true)) {
            return null;
        }

        if (! Str::isUuid($trimmed)) {
            return null;
        }

        return $trimmed;
    }

	public static function generateAdmissionNumber(School $school, Session $session): string
	{
		DB::table('schools')
			->where('id', $school->id)
			->lockForUpdate()
			->value('id');

		$school = $school->fresh();
		$session = $session->fresh();

		$sessionName = trim((string) ($session->name ?? ''));

		if ($sessionName === '') {
			throw new RuntimeException('Cannot generate admission number without a session name.');
		}

		$acronym = $school->resolved_acronym;
		$code = $school->formatted_code_sequence;

		if ($code === '000') {
			$sequenceValue = (int) ($school->code_sequence ?? 0);
			$code = str_pad((string) ($sequenceValue > 0 ? $sequenceValue : 1), 3, '0', STR_PAD_LEFT);
		}

		$prefix = "{$acronym}{$code}-{$sessionName}";

		$maxSequence = (int) DB::table('students')
			->where('school_id', $school->id)
			->where('current_session_id', $session->id)
			->where('admission_no', 'like', $prefix . '/%')
			->lockForUpdate()
			->selectRaw('MAX(CAST(SUBSTRING_INDEX(admission_no, "/", -1) AS UNSIGNED)) as max_sequence')
			->value('max_sequence');

		$nextSequence = $maxSequence > 0 ? $maxSequence + 1 : 1;

		$candidate = "{$prefix}/{$nextSequence}";

		while (
			DB::table('students')
				->where('admission_no', $candidate)
				->exists()
		) {
			$nextSequence++;
			$candidate = "{$prefix}/{$nextSequence}";
		}

		return $candidate;
	}

    protected function sanitizeUuidForeignKeys(): void
    {
        foreach (self::$uuidForeignKeys as $key) {
            if (! array_key_exists($key, $this->attributes)) {
                continue;
            }

            $normalized = $this->normalizeNullableUuid($this->attributes[$key] ?? null);
            $this->attributes[$key] = $normalized;
        }
    }

	public static function fixLegacyForeignKeys(): void
	{
		$fields = [
			'class_arm_id',
			'class_section_id',
			'parent_id',
			'school_class_id',
			'current_session_id',
			'current_term_id',
			'blood_group_id',
		];

        foreach ($fields as $field) {
            // Use raw SQL to avoid type comparison issues
            DB::statement("
                UPDATE students 
                SET {$field} = NULL 
                WHERE {$field} IS NOT NULL 
                AND (
                    CAST({$field} AS CHAR) = '0' 
                    OR CAST({$field} AS CHAR) = '' 
                    OR CHAR_LENGTH(CAST({$field} AS CHAR)) <> 36
                )
            ");
        }
    }

    public function getSchoolClassIdAttribute($value)
    {
        return $this->normalizeNullableUuid($value);
    }

    public function setSchoolClassIdAttribute($value): void
    {
        $this->attributes['school_class_id'] = $this->normalizeNullableUuid($value);
    }

    public function getClassArmIdAttribute($value)
    {
        return $this->normalizeNullableUuid($value);
    }

    public function setClassArmIdAttribute($value): void
    {
        $this->attributes['class_arm_id'] = $this->normalizeNullableUuid($value);
    }

    public function getClassSectionIdAttribute($value)
    {
        return $this->normalizeNullableUuid($value);
    }

    public function setClassSectionIdAttribute($value): void
    {
        $this->attributes['class_section_id'] = $this->normalizeNullableUuid($value);
    }

    public function getParentIdAttribute($value)
    {
        return $this->normalizeNullableUuid($value);
    }

    public function setParentIdAttribute($value): void
    {
        $this->attributes['parent_id'] = $this->normalizeNullableUuid($value);
    }

    public function getCurrentSessionIdAttribute($value)
    {
        return $this->normalizeNullableUuid($value);
    }

    public function setCurrentSessionIdAttribute($value): void
    {
        $this->attributes['current_session_id'] = $this->normalizeNullableUuid($value);
    }

    public function getCurrentTermIdAttribute($value)
    {
        return $this->normalizeNullableUuid($value);
    }

    public function setCurrentTermIdAttribute($value): void
    {
        $this->attributes['current_term_id'] = $this->normalizeNullableUuid($value);
    }

    public function school_class()
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function class_arm()
    {
        return $this->belongsTo(ClassArm::class);
    }

    public function class_section()
    {
        return $this->belongsTo(ClassSection::class);
    }

    public function parent()
    {
        return $this->belongsTo(SchoolParent::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function session()
    {
        return $this->belongsTo(Session::class, 'current_session_id');
    }

    public function term()
    {
        return $this->belongsTo(Term::class, 'current_term_id');
    }

	public function blood_group()
    {
        return $this->belongsTo(BloodGroup::class);
    }

    public function setPortalPasswordAttribute($value): void
    {
        if (empty($value)) {
            $this->attributes['portal_password'] = null;
            return;
        }

        if (is_string($value) && Str::startsWith($value, '$2y$')) {
            $this->attributes['portal_password'] = $value;
            return;
        }

        $this->attributes['portal_password'] = Hash::make($value);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function fee_payments()
    {
        return $this->hasMany(FeePayment::class);
    }

    public function performance_reports()
    {
        return $this->hasMany(PerformanceReport::class);
    }

    public function result_pins()
    {
        return $this->hasMany(ResultPin::class);
    }

    public function results()
    {
        return $this->hasMany(Result::class);
    }

    public function skill_ratings()
    {
        return $this->hasMany(SkillRating::class);
    }

    public function student_enrollments()
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function term_summaries()
    {
        return $this->hasMany(TermSummary::class);
    }
}
