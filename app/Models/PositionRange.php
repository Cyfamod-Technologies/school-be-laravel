<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PositionRange
 *
 * @property string $id
 * @property string $grading_scale_id
 * @property float $min_score
 * @property float $max_score
 * @property int $position
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property GradingScale $grading_scale
 *
 * @package App\Models
 */
class PositionRange extends Model
{
    protected $table = 'position_ranges';
    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = [
        'min_score' => 'float',
        'max_score' => 'float',
        'position' => 'int',
    ];

    protected $fillable = [
        'id',
        'grading_scale_id',
        'min_score',
        'max_score',
        'position',
    ];

    public function grading_scale()
    {
        return $this->belongsTo(GradingScale::class);
    }
}
