<?php

namespace App\Http\Controllers\Api\GYZ;

use App\Http\Controllers\Controller;
use App\Services\GYZ\RolePermissionService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    public function __construct(
        protected RolePermissionService $service
    ) {}

    /**
     * 获取权限配置
     * GET /api/v1/settings/role-permissions
     */
    public function index(): JsonResponse
    {
        return Result::success('获取成功', $this->service->getConfig());
    }

    /**
     * 保存权限配置（全量覆盖）
     * POST /api/v1/settings/role-permissions/save
     */
    public function save(Request $request): JsonResponse
    {
        $request->validate([
            'permissions'             => 'required|array|min:1',
            'permissions.*.pageId'    => 'required|string',
            'permissions.*.roleNames' => 'present|array',
        ]);

        $this->service->saveConfig(
            $request->input('permissions'),
            (int) auth('api')->id()
        );

        return Result::success('权限配置保存成功');
    }

    /**
     * 重置权限配置
     * POST /api/v1/settings/role-permissions/reset
     */
    public function reset(): JsonResponse
    {
        $data = $this->service->resetConfig((int) auth('api')->id());

        return Result::success('已重置为默认配置', $data);
    }
}
