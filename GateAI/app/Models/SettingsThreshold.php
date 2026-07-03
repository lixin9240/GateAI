<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettingsThreshold extends Model
{
    protected $table = 'settings_thresholds';

    protected $fillable = [
        'reservoir_id',     // 所属水库
        'metric',           // 监控指标
        'equipment_type',   // 适用设备类型
        'warning_upper',    // 预警上限
        'warning_lower',    // 预警下限
        'critical_upper',   // 紧急上限
        'critical_lower',   // 紧急下限
        'debounce_seconds', // 防抖时间（秒）
        'enabled',          // 是否启用
        'description',      // 描述
        'updated_by',       // 更新人
    ];

    protected $casts = [
        'warning_upper'    => 'decimal:4',
        'warning_lower'    => 'decimal:4',
        'critical_upper'   => 'decimal:4',
        'critical_lower'   => 'decimal:4',
        'debounce_seconds' => 'integer',
        'enabled'          => 'integer',
    ];

    public function reservoir()
    {
        return $this->belongsTo(Reservoir::class, 'reservoir_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
