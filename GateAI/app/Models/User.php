<?php
// 用户模型
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;
    use HasFactory, Notifiable, SoftDeletes;

    protected $table = 'users';

    protected $fillable = [
        'account',
        'password',
        'realname',
        'role_id',
        'phone',
        'force_change_password',
        'login_fail_count',
        'lock_expire_time',
        'login_token',
        'token_expire_time',
        'is_enabled',
    ];

    protected $hidden = [
        'password',
        'login_token',
        'remember_token',
        'login_token',
    ];

    protected $casts = [
        'id'                    => 'integer',
        'role_id'               => 'integer',
        'force_change_password' => 'integer',
        'login_fail_count'      => 'integer',
        'is_enabled'            => 'integer',
        'lock_expire_time'      => 'datetime',
        'token_expire_time'     => 'datetime',
        'email_verified_at'     => 'datetime',
        'password'              => 'hashed',
        'force_change_password' => 'integer',
        'login_fail_count'      => 'integer',
        'lock_expire_time'      => 'datetime',
        'token_expire_time'     => 'datetime',
        'is_enabled'            => 'integer',
    ];

    // Laravel 10.1 不支持 'hashed' cast，用 mutator 替代
    public function setPasswordAttribute($value): void
    {
        $this->attributes['password'] = bcrypt($value);
    }

    // ─── 关联 ──────────────────────────────────────
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function loginLogs()
    {
        return $this->hasMany(UserLoginLog::class, 'user_id');
    }

    public function locks()
    {
        return $this->hasMany(UserLock::class, 'user_id');
    }

    /**
     * 关联角色
     */
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * 关联登录日志
     */
    public function loginLogs()
    {
        return $this->hasMany(UserLoginLog::class, 'user_id');
    }

    /**
     * 账号是否已锁定
     */
    public function isLocked(): bool
    {
        return $this->lock_expire_time !== null && now()->lessThan($this->lock_expire_time);
    }
}
