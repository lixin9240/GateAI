<?php
// 响应结果封装
namespace App\Support;

use App\Enums\ResponseCode;
use Illuminate\Http\JsonResponse;

class Result
{
    public static function success(string $msg = '成功', mixed $data = null): JsonResponse
    {
        return response()->json([
            'code'    => ResponseCode::SUCCESS->value,
            'msg'     => $msg,
            'data'    => $data,
            'success' => true,
            'trace_id' => request()->attributes->get('trace_id'),
        ]);
    }

    public static function error(ResponseCode $code, ?string $msg = null, mixed $data = null): JsonResponse
    {
        return response()->json([
            'code'    => $code->value,
            'msg'     => $msg ?? $code->msg(),
            'data'    => $data,
            'success' => false,
            'trace_id' => request()->attributes->get('trace_id'),
        ]);
    }
}
