<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubjectAssignment extends Model
{
    protected $table = 'subject_school_class_assignments';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'subject_id',
        'session_id',
        'school_class_id',
        'class_arm_id',
        'class_section_id',
    ];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function session()
    {
        return $this->belongsTo(Session::class);
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
}
