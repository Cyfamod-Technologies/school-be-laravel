<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LocalGovernmentArea extends Model
{
	use HasFactory;

	protected $table = 'local_government_areas';
	public $incrementing = false;
	protected $keyType = 'string';

	protected $fillable = [
		'id',
		'state_id',
		'name',
	];

	protected static function booted()
	{
		static::creating(function (self $model) {
			if (empty($model->id)) {
				$model->id = (string) Str::uuid();
			}
		});
	}

	public function state()
	{
		return $this->belongsTo(State::class);
	}
}

