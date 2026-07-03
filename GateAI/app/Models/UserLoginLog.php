<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLoginLog extends Model
{
    protected $table = 'user_login_logs';
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'login_ip',
        'login_status',
        'fail_reason',
        'access_token',
        'created_at',
    ];

    protected $casts = [
        'id'          => 'integer',
        'user_id'     => 'integer',
        'login_status' => 'integer',
        'created_at'   => 'datetime',
    ];

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
