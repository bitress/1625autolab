<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CustomerInquiry extends Model
{
    protected $table = 'customer_inquiries';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'full_name',
        'address',
        'contact_number',
        'plate_number',
        'email_address',
        'facebook_name',
        'make',
        'model',
        'year_model',
        'product_to_purchase',
        'appointment_date',
        'appointment_time',
        'status',
    ];

    protected $casts = [
        'appointment_date' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = str_replace('-', '', (string) Str::uuid());
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
