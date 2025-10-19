<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class BulkUploadBatch extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'bulk_upload_batches';

    protected $fillable = [
        'school_id',
        'user_id',
        'type',
        'status',
        'total_rows',
        'payload',
        'meta',
        'expires_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'meta' => 'array',
        'expires_at' => 'datetime',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
