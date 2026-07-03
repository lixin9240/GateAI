<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmergencyStop extends Model
{
    use HasBeijingTime;

    protected $table = 'emergency_stops';

    public $timestamps = false;

    protected $fillable = [
        'trigger_user_id', // 触发急停操作人
        'decision_id',     // 关联调度决策ID
        'command_id',      // 急停指令ID
        'trigger_time',    // 急停下发时间
        'edge_ack_time',   // 边缘网关确认时间
        'plc_shut_time',   // PLC闸门停止时刻
        'recover_user_id', // 恢复操作人
        'recover_time',    // 恢复操作时间
        'stop_reason',     // 急停原因
    ];

    protected $casts = [
        'trigger_time'  => 'datetime',
        'edge_ack_time' => 'datetime',
        'plc_shut_time' => 'datetime',
        'recover_time'  => 'datetime',
    ];

    public function decision()
    {
        return $this->belongsTo(DispatchDecision::class, 'decision_id');
    }

    public function command()
    {
        return $this->belongsTo(ControlCommand::class, 'command_id');
    }
}
