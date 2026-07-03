<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Class Invoice
 *
 * @property string $id
 * @property string $school_id
 * @property string|null $term_id
 * @property string $invoice_type
 * @property int $student_count
 * @property float $price_per_student
 * @property float $total_amount
 * @property string $status
 * @property Carbon $due_date
 * @property string|null $reference_invoice_id
 * @property Carbon|null $paid_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @package App\Models
 */
class Invoice extends Model
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

    protected $table = 'invoices';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'student_count' => 'integer',
        'price_per_student' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'timestamp',
    ];

    protected $fillable = [
        'id',
        'school_id',
        'term_id',
        'invoice_type',
        'student_count',
        'price_per_student',
        'total_amount',
        'status',
        'due_date',
        'reference_invoice_id',
        'paid_at',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    public function referenceInvoice()
    {
        return $this->belongsTo(Invoice::class, 'reference_invoice_id');
    }

    public function midtermAdditions()
    {
        return $this->hasMany(MidtermStudentAddition::class);
    }

    public function commissions()
    {
        return $this->hasMany(AgentCommission::class);
    }

    public function isPaid()
    {
        return $this->status === 'paid';
    }

    public function isPartiallyPaid()
    {
        return $this->status === 'partial';
    }

    public function markAsPaid()
    {
        $this->status = 'paid';
        $this->paid_at = now();
        return $this->save();
    }
}
