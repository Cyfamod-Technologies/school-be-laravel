<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Class Term
 *
 * @property string $id
 * @property string $school_id
 * @property string $session_id
 * @property string $name
 * @property int $term_number
 * @property string $slug
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property School $school
 * @property Session $session
 * @property Collection|AssessmentComponent[] $assessment_components
 * @property Collection|Attendance[] $attendances
 * @property Collection|ClassTeacher[] $class_teachers
 * @property Collection|FeePayment[] $fee_payments
 * @property Collection|Result[] $results
 * @property Collection|SkillRating[] $skill_ratings
 * @property Collection|StudentEnrollment[] $student_enrollments
 * @property Collection|Student[] $students
 * @property Collection|SubjectTeacherAssignment[] $subject_teacher_assignments
 * @property Collection|TermSummary[] $term_summaries
 *
 * @package App\Models
 */
class Term extends Model
{
	protected $table = 'terms';
	public $incrementing = false;
	protected $keyType = 'string';

	protected $casts = [
		'start_date' => 'datetime',
		'end_date' => 'datetime',
		'term_number' => 'integer',
		'student_count_snapshot' => 'integer',
		'amount_due' => 'decimal:2',
		'amount_paid' => 'decimal:2',
		'midterm_amount_due' => 'decimal:2',
		'midterm_amount_paid' => 'decimal:2',
		'outstanding_balance' => 'decimal:2',
		'payment_due_date' => 'date',
		'has_midterm_additions' => 'boolean',
	];

	protected $fillable = [
		'id',
		'school_id',
		'session_id',
		'name',
		'term_number',
		'slug',
		'start_date',
		'end_date',
		'status',
		'payment_status',
		'invoice_id',
		'student_count_snapshot',
		'amount_due',
		'amount_paid',
		'payment_due_date',
		'has_midterm_additions',
		'midterm_amount_due',
		'midterm_amount_paid',
	];

	protected static function booted(): void
	{
		static::creating(function (self $term): void {
			if (empty($term->term_number)) {
				$term->term_number = static::resolveTermNumber($term);
			}

			if (empty($term->slug) && ! empty($term->name)) {
				$term->slug = Str::slug($term->name.'-'.$term->term_number);
			}
		});
	}

	private static function resolveTermNumber(self $term): int
	{
		$usedNumbers = static::query()
			->when($term->session_id, fn ($query) => $query->where('session_id', $term->session_id))
			->pluck('term_number')
			->filter()
			->map(fn ($value) => (int) $value)
			->values();

		$inferred = static::inferTermNumber((string) $term->name);
		if ($inferred !== null && ! $usedNumbers->contains($inferred)) {
			return $inferred;
		}

		$candidate = 1;
		while ($usedNumbers->contains($candidate)) {
			$candidate++;
		}

		return $candidate;
	}

	public static function inferTermNumber(string $name): ?int
	{
		$normalized = Str::of($name)
			->lower()
			->replaceMatches('/\s+/', ' ')
			->trim()
			->toString();

		if ($normalized === '') {
			return null;
		}

		if (preg_match('/\b(1st|first)\b/', $normalized) === 1) {
			return 1;
		}

		if (preg_match('/\b(2nd|second)\b/', $normalized) === 1) {
			return 2;
		}

		if (preg_match('/\b(3rd|third)\b/', $normalized) === 1) {
			return 3;
		}

		return null;
	}

	public function school()
	{
		return $this->belongsTo(School::class);
	}

	public function session()
	{
		return $this->belongsTo(Session::class);
	}

	public function assessment_components()
	{
		return $this->hasMany(AssessmentComponent::class);
	}

	public function attendances()
	{
		return $this->hasMany(Attendance::class);
	}

	public function class_teachers()
	{
		return $this->hasMany(ClassTeacher::class);
	}

	public function fee_payments()
	{
		return $this->hasMany(FeePayment::class);
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

	public function students()
	{
		return $this->hasMany(Student::class, 'current_term_id');
	}

	public function subject_teacher_assignments()
	{
		return $this->hasMany(SubjectTeacherAssignment::class);
	}

	public function term_summaries()
	{
		return $this->hasMany(TermSummary::class);
	}

	public function invoice()
	{
		return $this->belongsTo(Invoice::class);
	}

	public function invoices()
	{
		return $this->hasMany(Invoice::class);
	}

	public function midtermAdditions()
	{
		return $this->hasMany(MidtermStudentAddition::class);
	}

	/**
	 * Check if subscription payment is made (demo schools exempt)
	 */
	public function isPaymentRequired(): bool
	{
		return $this->school->subdomain !== 'demo';
	}

	/**
	 * Check if all fees are paid (original + mid-term)
	 */
	public function allFeesPaid(): bool
	{
		if (!$this->isPaymentRequired()) {
			return true;
		}

		return $this->outstanding_balance <= 0;
	}

	/**
	 * Get outstanding balance
	 */
	public function getOutstandingBalance(): float
	{
		if (!$this->isPaymentRequired()) {
			return 0;
		}

		return max(0, ($this->amount_due + $this->midterm_amount_due) - ($this->amount_paid + $this->midterm_amount_paid));
	}
}
