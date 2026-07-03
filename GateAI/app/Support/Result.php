<?php

namespace App\Support;

use App\Enums\ResponseCode;
use Illuminate\Http\JsonResponse;

class Result
{
    public static function success(string $msg = '成功', mixed $data = null): JsonResponse
    {
        return response()->json([
            'code'     => ResponseCode::SUCCESS->value,
            'msg'      => $msg,
            'data'     => $data,
            'success'  => true,
            'trace_id' => request()->attributes->get('trace_id'),
        ], 200);
    }

    public static function error(ResponseCode $code, ?string $msg = null, mixed $data = null): JsonResponse
    {
        return response()->json([
            'code'     => $code->value,
            'msg'      => $msg ?? $code->msg(),
            'data'     => $data,
            'success'  => false,
            'trace_id' => request()->attributes->get('trace_id'),
        ], self::httpStatus($code->value));
    }

    private static function httpStatus(int $code): int
    {
        return match (true) {
            $code === 0                           => 200,
            $code >= 1    && $code <= 9           => 400,
            $code >= 10001 && $code <= 10008       => 422,
            $code >= 20001 && $code <= 20008       => 401,
            $code >= 30001 && $code <= 30008       => 404,
            $code >= 40001 && $code <= 40010       => 400,
            $code >= 50000 && $code <= 50008       => 502,
            $code >= 60001 && $code <= 60008       => 500,
            $code >= 90001 && $code <= 90008       => 500,
            default                                => 400,
        };
    }
}
