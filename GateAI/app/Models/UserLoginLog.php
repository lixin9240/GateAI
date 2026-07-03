<?php

namespace App\Models;

use App\Models\Concerns\HasBeijingTime;

use App\Models\Concerns\BeijingTime;

use Illuminate\Database\Eloquent\Model;

class UserLoginLog extends Model
{
    protected $table = 'user_login_logs';
    use HasBeijingTime;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',      // 用户ID
        'login_ip',     // 登录IP
        'login_status', // 1=成功 0=失败
        'fail_reason',  // 失败原因
        'access_token', // 访问令牌
        'created_at',   // 登录时间
    ];

    protected $casts = [
        'created_at'   => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
