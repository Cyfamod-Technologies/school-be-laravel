<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

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

	protected $fillable = [
		'user_id',
		'action',
		'description'
	];

	public function user()
	{
		return $this->belongsTo(User::class);
	}
}
