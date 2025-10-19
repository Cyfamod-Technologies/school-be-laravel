<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PromotionLog extends Model
{
    protected $table = 'promotion_logs';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'promoted_at' => 'datetime',
        'meta' => 'array',
    ];

    protected $fillable = [
        'id',
        'student_id',
        'from_session_id',
        'to_session_id',
        'from_class_id',
        'to_class_id',
        'from_class_arm_id',
        'to_class_arm_id',
        'from_section_id',
        'to_section_id',
        'performed_by',
        'promoted_at',
        'meta',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $log): void {
            if (empty($log->id)) {
                $log->id = (string) Str::uuid();
            }
            if (empty($log->promoted_at)) {
                $log->promoted_at = Carbon::now();
            }
        });
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function fromSession()
    {
        return $this->belongsTo(Session::class, 'from_session_id');
    }

    public function toSession()
    {
        return $this->belongsTo(Session::class, 'to_session_id');
    }

    public function fromClass()
    {
        return $this->belongsTo(SchoolClass::class, 'from_class_id');
    }

    public function toClass()
    {
        return $this->belongsTo(SchoolClass::class, 'to_class_id');
    }

    public function fromClassArm()
    {
        return $this->belongsTo(ClassArm::class, 'from_class_arm_id');
    }

    public function toClassArm()
    {
        return $this->belongsTo(ClassArm::class, 'to_class_arm_id');
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
