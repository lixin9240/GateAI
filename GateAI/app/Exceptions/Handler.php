<?php

namespace App\Exceptions;

use Throwable;
use App\Support\Result;
use App\Enums\ResponseCode;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e)
    {
        /**
         * 参数验证异常
         */
        if ($e instanceof ValidationException) {
            return Result::error(
                ResponseCode::PARAM_ERROR,
                collect($e->errors())
                    ->flatten()
                    ->first()
            )->setStatusCode(400);
        }

        /**
         * 未登录
         */
        if ($e instanceof AuthenticationException) {
            return Result::error(
                ResponseCode::UNAUTHORIZED
            )->setStatusCode(401);
        }

        /**
         * 模型不存在
         */
        if ($e instanceof ModelNotFoundException) {
            return Result::error(
                ResponseCode::DATA_NOT_FOUND
            )->setStatusCode(404);
        }

        /**
         * 路由不存在
         */
        if ($e instanceof NotFoundHttpException) {
            return Result::error(
                ResponseCode::DATA_NOT_FOUND,
                '接口不存在'
            )->setStatusCode(404);
        }

        /**
         * 业务异常
         */
        if ($e instanceof BusinessException) {
            return Result::error(
                $e->codeEnum,
                $e->getMessage(),
                $e->errorData
            )->setStatusCode($this->getBusinessExceptionStatusCode($e));
        }

        /**
         * 数据库异常
         */
        if ($e instanceof QueryException) {
            Log::channel('exception')->error(
                '数据库异常',
                [
                    'trace_id' => $request->attributes->get('trace_id'),
                    'sql'      => $e->getSql(),
                    'bindings' => $e->getBindings(),
                    'message'  => $e->getMessage(),
                ]
            );

            return Result::error(
                ResponseCode::DATABASE_ERROR
            )->setStatusCode(500);
        }

        /**
         * 系统异常日志
         */
        Log::channel('exception')->error(
            $e->getMessage(),
            [
                'trace_id' => $request->attributes->get('trace_id'),
                'file'     => $e->getFile(),
                'line'     => $e->getLine(),
                'trace'    => $e->getTraceAsString(),
            ]
        );

        /**
         * 未知异常
         */
        return Result::error(
            ResponseCode::SYSTEM_ERROR
        )->setStatusCode(500);
    }

    /**
     * 根据业务异常码确定 HTTP 状态码
     */
    private function getBusinessExceptionStatusCode(BusinessException $e): int
    {
        $code = $e->codeEnum->value;

        return match (true) {
            $code >= 20001 && $code < 30000 => 401,
            $code >= 30001 && $code < 40000 => 400,
            $code >= 40001 && $code < 50000 => 400,
            $code >= 50001 && $code < 60000 => 502,
            $code >= 60001 && $code < 70000 => 500,
            $code >= 90001 && $code < 100000 => 500,
            default => 400,
        };
    }
}
