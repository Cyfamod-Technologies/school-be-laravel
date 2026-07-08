<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class StudentEnrollment
 *
 * @property string $id
 * @property string $student_id
 * @property string $class_section_id
 * @property string $session_id
 * @property string $term_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property ClassSection $class_section
 * @property Session $session
 * @property Student $student
 * @property Term $term
 */
class StudentEnrollment extends Model
{
    protected $table = 'student_enrollments';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'student_id',
        'class_section_id',
        'session_id',
        'term_id',
    ];

    protected static function booted()
    {
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function class_section()
    {
        return $this->belongsTo(ClassSection::class);
    }

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
