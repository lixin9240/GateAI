<?php

namespace App\Services\Fmy;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Models\User;
use App\Models\UserLoginLog;
use App\Support\LogHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    /** 连续登录失败锁定阈值 */
    private const MAX_LOGIN_FAIL_COUNT = 5;

    /** 锁定时长（分钟） */
    private const LOCK_DURATION_MINUTES = 30;

    /** 记住登录态 —— token 有效期（分钟），默认 30 天 */
    private const REMEMBER_TTL = 43200;

    /** 不记住 —— token 有效期（分钟），8 小时 */
    private const SESSION_TTL = 480;

    /**
     * 1.1 用户登录
     */
    public function login(array $data): array
    {
        $user = User::with('role')->where('account', $data['account'])->first();

        // 账号不存在 —— 统一返回"账号或密码错误"防枚举
        if (!$user) {
            LogHelper::business('用户登录失败', [
                'account'     => $data['account'],
                'ip'          => request()->ip(),
                'fail_reason' => '账号不存在',
            ], 'info', 'LOGIN');
            throw new BusinessException('账号或密码错误', ResponseCode::PASSWORD_ERROR);
        }

        // 账号是否启用
        if (!$user->is_enabled) {
            LogHelper::business('用户登录失败-账号已禁用', [
                'user_id' => $user->id,
                'account' => $user->account,
                'ip'      => request()->ip(),
            ], 'warning', 'LOGIN');
            throw new BusinessException('账号已被禁用，请联系管理员', ResponseCode::ACCOUNT_DISABLED);
        }

        // 账号是否锁定
        if ($user->isLocked()) {
            $remainSeconds = max(0, now()->diffInSeconds($user->lock_expire_time));
            LogHelper::business('用户登录失败-账号已锁定', [
                'user_id'          => $user->id,
                'account'          => $user->account,
                'lock_expire_time' => $user->lock_expire_time->toDateTimeString(),
            ], 'warning', 'LOGIN');
            throw new BusinessException('账号已锁定，请稍后再试', ResponseCode::ACCOUNT_FROZEN, [
                'lock_remain_seconds' => $remainSeconds,
                'lock_expire_time'    => $user->lock_expire_time->toDateTimeString(),
            ]);
        }

        // 校验密码
        if (!Hash::check($data['password'], $user->password)) {
            DB::transaction(function () use ($user) {
                $failCount = $user->login_fail_count + 1;
                $updates = ['login_fail_count' => $failCount];

                if ($failCount >= self::MAX_LOGIN_FAIL_COUNT) {
                    $updates['lock_expire_time'] = now()->addMinutes(self::LOCK_DURATION_MINUTES);
                }

                $user->update($updates);
                $this->recordLoginLog($user->id, request()->ip(), 0, '密码错误');
            });

            LogHelper::business('用户登录失败-密码错误', [
                'user_id'        => $user->id,
                'account'        => $user->account,
                'fail_count'     => $user->fresh()->login_fail_count,
                'max_fail_count' => self::MAX_LOGIN_FAIL_COUNT,
            ], 'info', 'LOGIN');

            $freshUser = $user->fresh();
            throw new BusinessException('账号或密码错误', ResponseCode::PASSWORD_ERROR, [
                'fail_count'         => (int) $freshUser->login_fail_count,
                'remaining_attempts' => max(0, self::MAX_LOGIN_FAIL_COUNT - (int) $freshUser->login_fail_count),
            ]);
        }

        // --- 登录成功 —— remember 决定 token 有效期 ---
        $remember = (bool) ($data['remember'] ?? false);
        $ttl = $remember ? self::REMEMBER_TTL : self::SESSION_TTL;
        JWTAuth::factory()->setTTL($ttl);

        $token = JWTAuth::fromUser($user);
        $tokenExpireTime = now()->addMinutes($ttl);

        DB::transaction(function () use ($user, $token, $tokenExpireTime) {
            $user->login_fail_count = 0;
            $user->login_token = 'Bearer ' . $token;
            $user->token_expire_time = $tokenExpireTime;
            $user->save();

            $this->recordLoginLog($user->id, request()->ip(), 1, null, 'Bearer ' . $token);
        });

        LogHelper::business('用户登录成功', [
            'user_id'  => $user->id,
            'account'  => $user->account,
            'ip'       => request()->ip(),
            'role'     => $user->role?->code,
            'remember' => $remember,
        ], 'info', 'LOGIN');

        return [
            'token'              => $token,
            'token_expire_time'  => $tokenExpireTime->toDateTimeString(),
            'remember'           => $remember,
            'user_info'          => [
                'id'        => $user->id,
                'account'   => $user->account,
                'realname'  => $user->realname,
                'role_code' => $user->role?->code,
                'role_name' => $user->role?->name,
            ],
        ];
    }

    /**
     * 1.2 修改密码
     */
    public function changePassword(int $userId, array $data): void
    {
        $user = User::findOrFail($userId);

        if (!Hash::check($data['old_password'], $user->password)) {
            LogHelper::business('修改密码失败-原密码错误', [
                'user_id' => $userId,
                'account' => $user->account,
            ], 'warning', 'CHANGE_PWD');
            throw new BusinessException('原密码错误', ResponseCode::PASSWORD_ERROR);
        }

        $user->password = $data['new_password'];
        $user->force_change_password = 0;
        $user->login_token = null;
        $user->token_expire_time = null;
        $user->save();

        LogHelper::business('修改密码成功', [
            'user_id' => $userId,
            'account' => $user->account,
        ], 'info', 'CHANGE_PWD');
    }

    /**
     * 用户登出 —— 清空 login_token 并将当前 token 加入黑名单
     */
    public function logout(int $userId): void
    {
        $user = User::findOrFail($userId);

        $user->login_token = null;
        $user->token_expire_time = null;
        $user->save();

        // 将当前 token 加入 JWT 黑名单，使其立即失效
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
        } catch (\Exception $e) {
            // token 可能已过期，忽略
        }

        LogHelper::business('用户登出成功', [
            'user_id' => $userId,
            'account' => $user->account,
        ], 'info', 'LOGOUT');
    }

    /**
     * 1.3 登录日志分页查询
     */
    public function getLoginLogs(array $params): array
    {
        $query = UserLoginLog::query()->with('user:id,realname');

        if (!empty($params['start_time'])) {
            $query->where('created_at', '>=', $params['start_time'] . ' 00:00:00');
        }
        if (!empty($params['end_time'])) {
            $query->where('created_at', '<=', $params['end_time'] . ' 23:59:59');
        }

        $pageSize = $params['page_size'] ?? 20;
        $paginator = $query->orderByDesc('created_at')->paginate($pageSize);

        $list = collect($paginator->items())->map(function (UserLoginLog $log) {
            return [
                'id'            => $log->id,
                'user_id'       => $log->user_id,
                'user_realname' => $log->user?->realname,
                'login_ip'      => $log->login_ip,
                'login_status'  => $log->login_status,
                'fail_reason'   => $log->fail_reason,
                'created_at'    => $log->created_at?->toDateTimeString(),
            ];
        });

        return [
            'total' => $paginator->total(),
            'list'  => $list,
        ];
    }

    /**
     * 记录登录日志
     */
    private function recordLoginLog(?int $userId, string $ip, int $status, ?string $failReason = null, ?string $token = null): void
    {
        // 登录成功时记录 token 的 SHA256 哈希，避免日志泄露完整令牌
        $accessToken = null;
        if ($status === 1 && $token) {
            $accessToken = hash('sha256', $token);
        }

        UserLoginLog::create([
            'user_id'      => $userId ?? 0,
            'login_ip'     => $ip,
            'login_status' => $status,
            'fail_reason'  => $failReason,
            'access_token' => $accessToken,
            'created_at'   => now(),
        ]);
    }
}
