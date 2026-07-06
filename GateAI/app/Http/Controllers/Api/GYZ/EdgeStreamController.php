<?php

namespace App\Http\Controllers\Api\GYZ;

use App\Events\CommandIssued;
use App\Events\EdgeDataUpdated;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EdgeStreamController extends Controller
{
    /**
     * 边缘数据上报 → 推送到前端 WebSocket
     */
    public function publishEdgeData(Request $request): JsonResponse
    {
        $edgeId  = $request->input('edge_id', 'jetson-hydropower-01');
        $type    = $request->input('type', 'water_level');
        $payload = $request->input('payload', []);

        broadcast(new EdgeDataUpdated($edgeId, $type, $payload));

        return response()->json(['sent' => true]);
    }

    /**
     * 人工下发闸门指令 → 推送到边缘端 WebSocket
     */
    public function sendGateCommand(Request $request): JsonResponse
    {
        $edgeId   = $request->input('edge_id', 'jetson-hydropower-01');
        $openings = $request->input('gate_openings', [100, 100, 50]);

        $command = new CommandIssued($edgeId, ['gate_openings' => $openings]);
        broadcast($command);

        return response()->json([
            'success'    => true,
            'command_id' => $command->commandId,
        ]);
    }
}
