<?php
// 故障复盘控制器
namespace App\Http\Controllers\Api\LX;

use App\Http\Controllers\Controller;
use App\Http\Requests\LX\LXIncidentRequest;
use App\Services\LX\IncidentService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;

class IncidentController extends Controller
{
    public function __construct(
        protected IncidentService $service
    ) {}

    public function incidents(LXIncidentRequest $request): JsonResponse
    {
        $data = $this->service->list($request->validated());

        return Result::success('获取故障复盘列表成功', [
            'total' => $data->total(),
            'list'  => $data->items(),
        ]);
    }

    public function importIncident(LXIncidentRequest $request): JsonResponse
    {
        $data = $this->service->import($request->validated());

        return Result::success('故障复盘导入成功', $data);
    }
}
