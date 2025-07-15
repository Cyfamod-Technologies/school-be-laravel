<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ApiKey
 *
 * @property string $id
 * @property string $name
 * @property string $key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class ApiKey extends Model
{
	protected $table = 'api_keys';
	public $incrementing = false;

	protected $fillable = [
		'name',
		'key'
	];
}
