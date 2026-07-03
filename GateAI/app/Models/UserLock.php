<?php

namespace App\Models;

use App\Models\Concerns\HasBeijingTime;

use App\Models\Concerns\BeijingTime;

use Illuminate\Database\Eloquent\Model;

class UserLock extends Model
{
    protected $table = 'user_locks';
    use HasBeijingTime;

    protected $fillable = [
        'user_id',     // 用户ID
        'reason',      // 锁定原因
        'duration',    // 自动解锁时长（分钟）
        'locked_at',   // 锁定时间
        'unlock_at',   // 自动解锁时间
        'unlock_type', // 解锁方式
        'unlocked_by', // 解锁人
        'unlocked_at', // 解锁时间
        'locked_by',   // 锁定时操作人
    ];

    protected $casts = [
        'duration'  => 'integer',
        'locked_at' => 'datetime',
        'unlock_at' => 'datetime',
        'unlocked_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function locker()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function unlocker()
    {
        return $this->belongsTo(User::class, 'unlocked_by');
    }
}
