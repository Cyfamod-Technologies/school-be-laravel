<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class State extends Model
{
	use HasFactory;

	protected $table = 'states';
	public $incrementing = false;
	protected $keyType = 'string';

	protected $fillable = [
		'id',
		'country_id',
		'name',
		'code',
	];

	protected static function booted()
	{
		static::creating(function (self $model) {
			if (empty($model->id)) {
				$model->id = (string) Str::uuid();
			}
		});
	}

	public function country()
	{
		return $this->belongsTo(Country::class);
	}

	public function local_government_areas()
	{
		return $this->hasMany(LocalGovernmentArea::class);
	}
}

