<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class StaffAttendance extends Model
{
    use HasUuids;

    protected $table = 'staff_attendances';

    protected $fillable = [
        'staff_id',
        'school_id',
        'date',
        'status',
        'branch_name',
        'recorded_by',
        'metadata',
    ];

    protected $casts = [
        'date' => 'date',
        'metadata' => 'array',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
