<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLoginLog extends Model
{
    protected $table = 'user_login_logs';

    protected $fillable = [
        'user_id',
        'login_ip',
        'login_status',
        'fail_reason',
        'access_token',
    ];

    protected $casts = [
        'id'          => 'integer',
        'user_id'     => 'integer',
        'login_status' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
