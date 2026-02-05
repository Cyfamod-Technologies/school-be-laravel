<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CommentRange
 *
 * @property string $id
 * @property string $grading_scale_id
 * @property float $min_score
 * @property float $max_score
 * @property string $teacher_comment
 * @property string $principal_comment
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property GradingScale $grading_scale
 *
 * @package App\Models
 */
class CommentRange extends Model
{
    protected $table = 'comment_ranges';
    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = [
        'min_score' => 'float',
        'max_score' => 'float',
    ];

    protected $fillable = [
        'id',
        'grading_scale_id',
        'min_score',
        'max_score',
        'teacher_comment',
        'principal_comment',
    ];

    public function grading_scale()
    {
        return $this->belongsTo(GradingScale::class);
    }
}
