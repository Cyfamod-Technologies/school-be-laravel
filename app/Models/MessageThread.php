<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class MessageThread
 *
 * @property string $id
 * @property string $sender_id
 * @property string $receiver_id
 * @property string $sender_role
 * @property string $receiver_role
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property User $user
 *
 * @package App\Models
 */
class MessageThread extends Model
{
	protected $table = 'message_threads';
	public $incrementing = false;

	protected $keyType = 'string';

	protected $fillable = [
		'sender_id',
		'receiver_id',
		'sender_role',
		'receiver_role'
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'sender_id');
	}
}
