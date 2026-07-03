<?php
// 用户模型
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
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
    ];

    // Laravel 10.1 不支持 'hashed' cast，用 mutator 替代
    public function setPasswordAttribute($value): void
    {
        $this->attributes['password'] = bcrypt($value);
    }

    // ─── JWT ───────────────────────────────────────
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
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
}
