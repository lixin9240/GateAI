<?php

namespace App\Models;

use App\Models\Concerns\HasBeijingTime;

use App\Models\Concerns\BeijingTime;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class SettingsWeight extends Model
{
    protected $table = 'settings_weights';
    use HasBeijingTime;

    protected $fillable = [
        'version',       // 配置版本号
        'enabled',       // 是否启用
        'power_weight',  // 发电效益权重
        'safety_weight', // 防洪安全权重
        'ecology_weight',// 生态流量权重
        'preset_name',   // 预设方案名称
        'is_preset',     // 是否系统预设
        'sync_status',   // 同步状态
        'synced_at',     // 最后同步时间
        'synced_nodes',  // 已同步节点列表
        'description',   // 方案描述
        'updated_by',    // 更新人
    ];

    protected $casts = [
        'enabled'        => 'integer',
        'is_preset'      => 'integer',
        'power_weight'   => 'decimal:2',
        'safety_weight'  => 'decimal:2',
        'ecology_weight' => 'decimal:2',
        'synced_nodes'   => 'json',
        'synced_at'      => 'datetime',
    ];

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
