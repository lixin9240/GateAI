<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LstmPrediction extends Model
{
    protected $table = 'lstm_predictions';

    public $timestamps = false;

    protected $fillable = [
        'equipment_id',     // 监测站点设备
        'predict_term',     // 预测时长 1=1h 2=3h 3=6h
        'base_time',        // 预测基准时间
        'water_seq_json',   // 时序水位预测数组
        'flow_seq_json',    // 时序流量预测数组
        'predict_accuracy', // 预测准确率 0~100
    ];

    protected $casts = [
        'water_seq_json' => 'json',
        'flow_seq_json'  => 'json',
        'base_time'      => 'datetime',
    ];
}
