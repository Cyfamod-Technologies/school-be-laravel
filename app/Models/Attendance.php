<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Class Attendance
 *
 * @property string $id
 * @property string $student_id
 * @property string $session_id
 * @property string $term_id
 * @property Carbon $date
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property Session $session
 * @property Student $student
 * @property Term $term
 *
 * @package App\Models
 */
class Attendance extends Model
{
	protected $table = 'attendances';
    use HasUuids;
    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = [
        'date' => 'date',
        'metadata' => 'array',
    ];

    protected $fillable = [
        'student_id',
        'session_id',
        'term_id',
        'school_class_id',
        'class_arm_id',
        'class_section_id',
        'date',
        'status',
        'recorded_by',
        'metadata',
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

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function classArm()
    {
        return $this->belongsTo(ClassArm::class);
    }

    public function classSection()
    {
        return $this->belongsTo(ClassSection::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
