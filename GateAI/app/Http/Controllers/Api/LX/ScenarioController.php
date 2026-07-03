<?php
// 仿真场景控制器
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
}
