<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Class MidtermStudentAddition
 *
 * @property string $id
 * @property string $term_id
 * @property string $school_id
 * @property string $student_id
 * @property string|null $invoice_id
 * @property string $status
 * @property float $price_per_student
 * @property Carbon $admission_date
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @package App\Models
 */
class MidtermStudentAddition extends Model
{
    use HasFactory;

    protected static function booted()
    {
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    protected $table = 'midterm_student_additions';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'price_per_student' => 'decimal:2',
        'admission_date' => 'date',
    ];

    protected $fillable = [
        'id',
        'term_id',
        'school_id',
        'student_id',
        'invoice_id',
        'status',
        'price_per_student',
        'admission_date',
    ];

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function isPaid()
    {
        return $this->status === 'paid';
    }

    public function markAsPaid()
    {
        $this->status = 'paid';
        return $this->save();
    }
}
