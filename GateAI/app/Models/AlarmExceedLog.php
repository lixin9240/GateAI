<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlarmExceedLog extends Model
{
    protected $table = 'alarm_exceed_logs';
    
    protected $fillable = [
        'equipment_id', 'metric_value', 'threshold_value', 
        'duration', 'exceed_start'
    ];
}