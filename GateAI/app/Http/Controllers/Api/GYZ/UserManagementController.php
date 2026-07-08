<?php

namespace App\Http\Controllers\Api\Gyz;

use App\Http\Controllers\Controller;
use App\Http\Requests\GYZ\UserCreateRequest;
use App\Http\Requests\GYZ\UserListRequest;
use App\Http\Requests\GYZ\UserLockRequest;
use App\Http\Requests\GYZ\UserResetPasswordRequest;
use App\Http\Requests\GYZ\UserUnlockRequest;
use App\Http\Requests\GYZ\UserUpdateRequest;
use App\Services\GYZ\UserManagementService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class UserManagementController extends Controller
{
    public function __construct(
        protected UserManagementService $service
    ) {}

    /**
     * 8.4.1 用户列表
     */
    public function index(UserListRequest $request): JsonResponse
    {
        $result = $this->service->list(
            $request->validated('role_id'),
            $request->validated('is_enabled'),
            $request->validated('keyword'),
            (int) ($request->validated('page') ?? 1),
            (int) ($request->validated('page_size') ?? 20)
        );

        return Result::success('获取成功', $result);
    }

    /**
     * 8.4.2 创建用户
     */
    public function store(UserCreateRequest $request): JsonResponse
    {
        $user = $this->service->create($request->validated());

        return Result::success('用户创建成功', [
            'id'      => $user->id,
            'account' => $user->account,
        ]);
    }

    /**
     * 8.4.3 更新用户
     */
    public function update(int $id, UserUpdateRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $filename = 'avatar_' . $id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $ossPath = 'avatars/' . date('Ym') . '/' . $filename;

            Storage::disk('oss')->put($ossPath, file_get_contents($file->getRealPath()));
            $data['avatar'] = config('filesystems.disks.oss.endpoint') . '/' . $ossPath;
        }

        $user = $this->service->update($id, $data);

        return Result::success('用户更新成功', $user);
    }

    /**
     * 8.4.4 重置密码
     */
    public function resetPassword(int $id, UserResetPasswordRequest $request): JsonResponse
    {
        $newPassword = $this->service->resetPassword(
            $id,
            $request->validated('new_password'),
            $request->boolean('force_logout', true)
        );

        // 按接口文档规定：密码不在API响应中返回
        return Result::success('密码重置成功');
    }

    /**
     * 8.4.5 锁定账号
     */
    public function lock(int $id, UserLockRequest $request): JsonResponse
    {
        $result = $this->service->lock(
            $id,
            $request->validated('reason'),
            (int) ($request->validated('duration') ?? 0),
            $request->boolean('force_logout', true),
            (int) auth('api')->id()
        );

        return Result::success('账号已锁定', $result);
    }

    /**
     * 8.4.6 解锁账号
     */
    public function unlock(int $id, UserUnlockRequest $request): JsonResponse
    {
        $this->service->unlock(
            $id,
            $request->validated('reason'),
            (int) auth('api')->id()
        );

        return Result::success('账号已解锁');
    }

    /**
     * 8.4.7 删除用户
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return Result::success('用户已删除');
    }

    /**
     * 导出用户列表 CSV
     */
    public function export(): \Illuminate\Http\Response
    {
        $result = $this->service->list(null, null, null, 1, 1000);
        $csv = "ID,账号,姓名,角色,手机,启用,锁定\n";
        foreach ($result['list'] as $u) {
            $locked = ($u['is_locked'] ?? false) ? '是' : '否';
            $enabled = ($u['is_enabled'] ?? 0) ? '是' : '否';
            $csv .= "{$u['id']},{$u['account']},{$u['realname']},{$u['role_name']},{$u['phone']},{$enabled},{$locked}\n";
        }
        return response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="users_export.csv"']);
    }
}
