<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LstmPrediction extends Model
{
    protected $table = 'lstm_predictions';

    protected $fillable = [
        'equipment_id',
        'predict_term',
        'base_time',
        'water_seq_json',
        'flow_seq_json',
        'predict_accuracy',
    ];

    protected $casts = [
        'water_seq_json' => 'json',
        'flow_seq_json'  => 'json',
        'base_time'      => 'datetime',
    ];
}
