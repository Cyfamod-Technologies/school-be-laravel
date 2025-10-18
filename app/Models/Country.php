<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Country extends Model
{
    use HasFactory;

	protected $table = 'countries';
	public $incrementing = false;
	protected $keyType = 'string';

    protected $fillable = [
        'id',
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

	public function states()
	{
		return $this->hasMany(State::class);
	}
}
