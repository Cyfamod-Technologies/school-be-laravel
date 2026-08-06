<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Cache
 *
 * @property string $key
 * @property string $value
 * @property int $expiration
 */
class Cache extends Model
{
    protected $table = 'cache';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $casts = [
        'expiration' => 'int',
    ];

    protected $fillable = [
        'value',
        'expiration',
    ];
}
