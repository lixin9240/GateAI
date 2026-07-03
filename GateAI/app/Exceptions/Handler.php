<?php
// 异常处理程序
namespace App\Exceptions;

use App\Enums\ResponseCode;
use App\Support\LogHelper;
use App\Support\Result;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

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
        if ($e instanceof ValidationException) {
            return Result::error(
                ResponseCode::PARAM_ERROR,
                collect($e->errors())->flatten()->first()
            );
        }

        if ($e instanceof AuthenticationException) {
            return Result::error(ResponseCode::UNAUTHORIZED);
        }

        if ($e instanceof ModelNotFoundException) {
            return Result::error(ResponseCode::DATA_NOT_FOUND);
        }

        if ($e instanceof NotFoundHttpException) {
            return Result::error(ResponseCode::DATA_NOT_FOUND, '接口不存在');
        }

        if ($e instanceof BusinessException) {
            return Result::error($e->codeEnum, $e->getMessage());
        }

        if ($e instanceof QueryException) {
            LogHelper::exception($e, [
                'sql'      => $e->getSql(),
                'bindings' => $e->getBindings(),
            ], '数据库异常');

            return Result::error(ResponseCode::DATABASE_ERROR);
        }

        LogHelper::exception($e);

        return Result::error(ResponseCode::SYSTEM_ERROR);
    }
}
