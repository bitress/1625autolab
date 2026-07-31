<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingWaitlist extends Model
{
    protected $table = 'booking_waitlist';

    protected $fillable = [
        'slot_date',
        'slot_time',
        'user_id',
        'name',
        'email',
        'phone',
        'service_ids',
        'notes',
        'status',
        'notified_at',
    ];

    protected $casts = [
        'slot_date' => 'date',
        'notified_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
