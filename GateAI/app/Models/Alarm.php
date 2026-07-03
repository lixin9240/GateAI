<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alarm extends Model
{
    protected $table = 'alarms';
    
    protected $fillable = [
        'reservoir_id', 'level', 'status', 'message', 
        'acknowledged_by', 'acknowledged_at', 'disposed_note'
    ];

    protected $casts = [
        'acknowledged_at' => 'datetime',
    ];
}