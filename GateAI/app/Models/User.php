<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
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

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'login_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'password'              => 'hashed',
        'force_change_password' => 'integer',
        'login_fail_count'      => 'integer',
        'lock_expire_time'      => 'datetime',
        'token_expire_time'     => 'datetime',
        'is_enabled'            => 'integer',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
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
