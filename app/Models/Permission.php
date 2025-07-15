<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Permission
 *
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property Collection|Role[] $roles
 *
 * @package App\Models
 */
class Permission extends Model
{
	protected $table = 'permissions';
	public $incrementing = false;

	protected $fillable = [
		'name',
		'description'
	];

	public function roles()
	{
		return $this->belongsToMany(Role::class, 'role_has_permissions');
	}
}
