<?php

namespace App\Services\Gyz;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Models\User;
use App\Models\UserLock;
use App\Models\UserLoginLog;
use App\Support\LogHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserManagementService
{
    /**
     * 用户列表
     */
    public function list(?int $roleId, ?int $isEnabled, ?string $keyword, int $page, int $pageSize): array
    {
        $query = User::query()
            ->select([
                'id', 'account', 'realname', 'role_id', 'phone',
                'is_enabled', 'created_at',
            ])
            ->with(['role:id,name,code']);

        if ($roleId !== null) {
            $query->where('role_id', $roleId);
        }
        if ($isEnabled !== null) {
            $query->where('is_enabled', $isEnabled);
        }
        if ($keyword !== null) {
            $query->where(function ($q) use ($keyword) {
                $q->where('account', 'like', "%{$keyword}%")
                  ->orWhere('realname', 'like', "%{$keyword}%");
            });
        }

        $paginator = $query->orderByDesc('created_at')->paginate($pageSize, ['*'], 'page', $page);

        $list = $paginator->items();
        // 添加 role_name
        $list = array_map(function ($user) {
            $arr = $user->toArray();
            $arr['role_name'] = $user->role->name ?? '';
            $arr['role_code'] = $user->role->code ?? '';
            unset($arr['role']);
            return $arr;
        }, $list);

        return [
            'total' => $paginator->total(),
            'list'  => $list,
        ];
    }

    /**
     * 创建用户
     */
    public function create(array $data): User
    {
        // 检查账号唯一性
        $exists = User::where('account', $data['account'])->exists();
        if ($exists) {
            throw new BusinessException('账号已存在', ResponseCode::DATA_DUPLICATE);
        }

        return DB::transaction(function () use ($data) {
            $user = User::create([
                'account'  => $data['account'],
                'password' => $data['password'],
                'realname' => $data['realname'],
                'role_id'  => $data['role_id'],
                'phone'    => $data['phone'] ?? null,
            ]);

            LogHelper::business('用户已创建', [
                'user_id'      => $user->id,
                'account'      => $user->account,
                'created_by'   => auth('api')->id(),
            ], 'info', 'USER_CREATE');

            return $user;
        });
    }

    /**
     * 更新用户
     */
    public function update(int $id, array $data): User
    {
        $user = User::find($id);

        if (! $user) {
            throw new BusinessException('用户不存在', ResponseCode::DATA_NOT_FOUND);
        }

        DB::transaction(function () use ($user, $data) {
            $user->update($data);

            LogHelper::business('用户信息已更新', [
                'user_id'    => $user->id,
                'changes'    => $data,
                'updated_by' => auth('api')->id(),
            ], 'info', 'USER_UPDATE');
        });

        return $user->fresh();
    }

    /**
     * 重置密码
     */
    public function resetPassword(int $id, ?string $newPassword, bool $forceLogout): string
    {
        $user = User::find($id);

        if (! $user) {
            throw new BusinessException('用户不存在', ResponseCode::DATA_NOT_FOUND);
        }

        $password = $newPassword ?? Str::random(12);

        DB::transaction(function () use ($user, $password, $forceLogout) {
            $user->update([
                'password' => $password,
                'force_change_password' => 1,
            ]);

            if ($forceLogout) {
                $user->update([
                    'login_token'       => null,
                    'token_expire_time' => null,
                ]);
            }

            LogHelper::business('用户密码已重置', [
                'user_id'    => $user->id,
                'account'    => $user->account,
                'reset_by'   => auth('api')->id(),
            ], 'warning', 'PASSWORD_RESET');
        });

        return $password;
    }

    /**
     * 锁定账号
     */
    public function lock(int $id, string $reason, int $duration, bool $forceLogout, int $lockedBy): array
    {
        $user = User::find($id);

        if (! $user) {
            throw new BusinessException('用户不存在', ResponseCode::DATA_NOT_FOUND);
        }

        if ($user->lock_expire_time && $user->lock_expire_time > now()) {
            throw new BusinessException('账号已被锁定', ResponseCode::DATA_LOCKED);
        }

        $lockExpireTime = $duration > 0 ? now()->addMinutes($duration) : null;

        return DB::transaction(function () use ($user, $reason, $duration, $forceLogout, $lockedBy, $lockExpireTime) {
            $user->update([
                'lock_expire_time' => $lockExpireTime,
                'login_token'      => $forceLogout ? null : $user->login_token,
            ]);

            $lock = UserLock::create([
                'user_id'   => $user->id,
                'reason'    => $reason,
                'duration'  => $duration,
                'locked_at' => now(),
                'unlock_at' => $lockExpireTime,
                'unlock_type' => $duration > 0 ? 'auto' : 'manual',
                'locked_by'  => $lockedBy,
            ]);

            LogHelper::business('账号已被锁定', [
                'user_id'   => $user->id,
                'account'   => $user->account,
                'reason'    => $reason,
                'duration'  => $duration,
                'locked_by' => $lockedBy,
            ], 'warning', 'USER_LOCK');

            return [
                'lock_id'    => $lock->id,
                'locked_at'  => $lock->locked_at->toDateTimeString(),
                'unlock_at'  => $lock->unlock_at ? $lock->unlock_at->toDateTimeString() : null,
            ];
        });
    }

    /**
     * 解锁账号
     */
    public function unlock(int $id, ?string $reason, int $unlockedBy): void
    {
        $user = User::find($id);

        if (! $user) {
            throw new BusinessException('用户不存在', ResponseCode::DATA_NOT_FOUND);
        }

        DB::transaction(function () use ($user, $reason, $unlockedBy) {
            $user->update(['lock_expire_time' => null]);

            // 更新最近的锁定记录
            $lock = UserLock::where('user_id', $user->id)
                ->whereNull('unlocked_at')
                ->orderByDesc('created_at')
                ->first();

            if ($lock) {
                $lock->update([
                    'unlocked_by' => $unlockedBy,
                    'unlocked_at' => now(),
                    'unlock_type' => 'manual',
                ]);
            }

            LogHelper::business('账号已解锁', [
                'user_id'     => $user->id,
                'account'     => $user->account,
                'reason'      => $reason,
                'unlocked_by' => $unlockedBy,
            ], 'info', 'USER_UNLOCK');
        });
    }

    /**
     * 删除用户（软删除）
     */
    public function delete(int $id): void
    {
        $user = User::find($id);

        if (! $user) {
            throw new BusinessException('用户不存在', ResponseCode::DATA_NOT_FOUND);
        }

        if ($user->id === auth('api')->id()) {
            throw new BusinessException('不可删除自己', ResponseCode::STATUS_CANNOT_OPERATE);
        }

        DB::transaction(function () use ($user) {
            $user->delete();

            LogHelper::business('用户已删除', [
                'user_id'    => $user->id,
                'account'    => $user->account,
                'deleted_by' => auth('api')->id(),
            ], 'warning', 'USER_DELETE');
        });
    }
}
