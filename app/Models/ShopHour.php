<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopHour extends Model
{
    protected $table = 'shop_hours';

    public $timestamps = false; // The migration only added updated_at, but we can just disable auto-timestamps to avoid created_at errors unless needed. Wait, it has updated_at, let's keep it. Actually, Laravel expects both created_at and updated_at.

    public const CREATED_AT = null; // Tell Laravel there is no created_at column

    protected $fillable = [
        'day_of_week',
        'is_open',
        'open_time',
        'close_time',
        'slot_interval_h',
    ];

    protected $casts = [
        'is_open' => 'boolean',
    ];
}
