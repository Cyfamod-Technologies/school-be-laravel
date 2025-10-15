<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class FeePayment
 *
 * @property string $id
 * @property string $student_id
 * @property string $session_id
 * @property string $term_id
 * @property float $amount_paid
 * @property float $amount_due
 * @property float|null $balance
 * @property string $status
 * @property Carbon $payment_date
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property Session $session
 * @property Student $student
 * @property Term $term
 *
 * @package App\Models
 */
class FeePayment extends Model
{
	protected $table = 'fee_payments';
	public $incrementing = false;

	protected $keyType = 'string';

	protected $casts = [
		'amount_paid' => 'float',
		'amount_due' => 'float',
		'balance' => 'float',
		'payment_date' => 'datetime'
	];

	protected $fillable = [
		'student_id',
		'session_id',
		'term_id',
		'amount_paid',
		'amount_due',
		'balance',
		'status',
		'payment_date'
	];

	public function session()
	{
		return $this->belongsTo(Session::class);
	}

	public function student()
	{
		return $this->belongsTo(Student::class);
	}

	public function term()
	{
		return $this->belongsTo(Term::class);
	}
}
