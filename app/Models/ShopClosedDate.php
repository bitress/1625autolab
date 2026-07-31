<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopClosedDate extends Model
{
    protected $table = 'shop_closed_dates';

    public const UPDATED_AT = null; // Migration only has created_at

    protected $fillable = [
        'closed_date',
        'reason',
    ];

    protected $casts = [
        'closed_date' => 'date',
    ];
}
