<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RealtimeEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_type',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
