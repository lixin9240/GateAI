<?php

namespace App\Http\Controllers\Api\Gyz;

use App\Http\Controllers\Controller;
use App\Http\Requests\GYZ\ModelActivateRequest;
use App\Http\Requests\GYZ\ModelDeployRequest;
use App\Http\Requests\GYZ\ModelListRequest;
use App\Http\Requests\GYZ\ModelRollbackRequest;
use App\Http\Requests\GYZ\ModelUploadRequest;
use App\Services\GYZ\SettingsModelService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;

class SettingsModelController extends Controller
{
    public function __construct(
        protected SettingsModelService $service
    ) {}

    /**
     * 8.3.1 获取模型列表
     */
    public function index(ModelListRequest $request): JsonResponse
    {
        $result = $this->service->list(
            $request->validated('type'),
            $request->validated('status'),
            $request->validated('keyword'),
            (int) ($request->validated('page') ?? 1),
            (int) ($request->validated('page_size') ?? 20)
        );

        return Result::success('获取成功', $result);
    }

    /**
     * 8.3.2 上传模型
     */
    public function upload(ModelUploadRequest $request): JsonResponse
    {
        $model = $this->service->upload(
            $request->validated(),
            (int) auth('api')->id()
        );

        return Result::success('模型上传成功', $model);
    }

    /**
     * 8.3.3 激活模型
     */
    public function activate(int $id, ModelActivateRequest $request): JsonResponse
    {
        $model = $this->service->activate(
            $id,
            $request->boolean('force', false),
            $request->boolean('rollback_on_failure', true),
            (int) auth('api')->id()
        );

        return Result::success('模型已激活', $model);
    }

    /**
     * 8.3.4 回滚模型
     */
    public function rollback(int $id, ModelRollbackRequest $request): JsonResponse
    {
        $model = $this->service->rollback(
            $id,
            $request->validated('reason'),
            (int) auth('api')->id()
        );

        return Result::success('模型已回滚', $model);
    }

    /**
     * 8.3.5 删除模型
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return Result::success('模型已删除');
    }

    /**
     * 8.3.6 下发模型至边缘端
     */
    public function deploy(int $id, ModelDeployRequest $request): JsonResponse
    {
        $results = $this->service->deploy(
            $id,
            $request->validated('edge_node_ids'),
            $request->validated('strategy'),
            (int) auth('api')->id()
        );

        return Result::success('模型下发任务已创建', $results);
    }
}
