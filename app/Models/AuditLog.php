<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Class AuditLog
 *
 * @property string $id
 * @property string $user_id
 * @property string $action
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property User $user
 *
 * @package App\Models
 */
class AuditLog extends Model
{
	protected $table = 'audit_logs';
	public $incrementing = false;

	protected $keyType = 'string';

	protected $fillable = [
		'id',
		'user_id',
		'action',
		'description'
	];

	protected static function booted()
	{
		static::creating(function (self $model) {
			if (empty($model->id)) {
				$model->id = (string) Str::uuid();
			}
		});
	}

	public function user()
	{
		return $this->belongsTo(User::class);
	}
}
