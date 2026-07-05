<?php

namespace App\Http\Controllers\Api\LX;

use App\Http\Controllers\Controller;
use App\Http\Requests\LX\LXScenarioRequest;
use App\Services\LX\ScenarioService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;

class ScenarioController extends Controller
{
    public function __construct(
        protected ScenarioService $service
    ) {}

    public function scenarios(LXScenarioRequest $request): JsonResponse
    {
        $data = $this->service->list($request->validated());

        return Result::success('获取仿真场景列表成功', [
            'total' => $data->total(),
            'list'  => $data->items(),
        ]);
    }

    public function store(LXScenarioRequest $request): JsonResponse
    {
        $scenario = $this->service->create($request->validated());

        return Result::success('创建仿真场景成功', $scenario);
    }

    public function update(int $id, LXScenarioRequest $request): JsonResponse
    {
        $scenario = $this->service->update($id, $request->validated());

        return Result::success('更新仿真场景成功', $scenario);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return Result::success('删除仿真场景成功');
    }
}
