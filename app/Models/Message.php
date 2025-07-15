<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Message
 *
 * @property string $id
 * @property string $thread_id
 * @property string $sender_id
 * @property string $receiver_id
 * @property string $sender_role
 * @property string $receiver_role
 * @property string $message_body
 * @property bool $is_read
 * @property Carbon $sent_at
 * @property Carbon $updated_at
 *
 * @property User $user
 *
 * @package App\Models
 */
class Message extends Model
{
	protected $table = 'messages';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'is_read' => 'bool',
		'sent_at' => 'datetime'
	];

	protected $fillable = [
		'thread_id',
		'sender_id',
		'receiver_id',
		'sender_role',
		'receiver_role',
		'message_body',
		'is_read',
		'sent_at'
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'sender_id');
	}
}
