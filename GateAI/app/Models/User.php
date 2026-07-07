<?php
// 用户模型
namespace App\Models;

use App\Models\Concerns\BeijingTime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use BeijingTime, HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $table = 'users';

    protected $fillable = [
        'account',               // 登录账号
        'password',              // 密码
        'realname',              // 真实姓名
        'role_id',               // 角色ID
        'phone',                 // 手机号
        'email',                 // 邮箱
        'avatar',                // 头像路径
        'force_change_password', // 是否强制修改密码
        'login_fail_count',      // 登录失败次数
        'lock_expire_time',      // 锁定到期时间
        'login_token',           // 登录令牌
        'token_expire_time',     // 令牌过期时间
        'is_enabled',            // 启用状态
    ];

    protected $hidden = [
        'password',
        'login_token',
        'remember_token',
    ];

    protected $casts = [
        'role_id'               => 'integer',
        'email_verified_at'     => 'datetime',
        // 'hashed' cast Laravel 10.1 不支持，用 setPasswordAttribute() mutator 替代
        'force_change_password' => 'integer',
        'login_fail_count'      => 'integer',
        'lock_expire_time'      => 'datetime',
        'token_expire_time'     => 'datetime',
        'is_enabled'            => 'integer',
    ];

    public function setPasswordAttribute($value): void
    {
        $this->attributes['password'] = bcrypt($value);
    }

    // ─── JWT ──────────────────────────────────────
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }



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
     * 账号是否已锁定
     */
    public function isLocked(): bool
    {
        return $this->lock_expire_time !== null && now()->lessThan($this->lock_expire_time);
    }
}
