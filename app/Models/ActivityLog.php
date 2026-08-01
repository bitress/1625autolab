<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    public $timestamps = false; // Because created_at is populated via useCurrent() and there is no updated_at

    protected $fillable = [
        'log_name',
        'description',
        'subject_type',
        'subject_id',
        'causer_type',
        'causer_id',
        'properties_json',
        'attribute_changes_json',
        'created_at',
    ];

    protected $casts = [
        'properties_json' => 'array',
        'attribute_changes_json' => 'array',
        'created_at' => 'datetime',
    ];
}
