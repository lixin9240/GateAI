<?php

namespace App\Http\Middleware;

use App\Enums\ResponseCode;
use App\Support\Result;
use Closure;
use Illuminate\Http\Request;

/**
 * 角色权限中间件
 * 用法: Route::middleware('role:admin,algorithm') -> ...
 */
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();

        if (! $user || ! $user->role) {
            return Result::error(ResponseCode::FORBIDDEN, '无权限访问');
        }

        if (! in_array($user->role->code, $roles)) {
            return Result::error(ResponseCode::FORBIDDEN, '当前角色无此操作权限');
        }

        return $next($request);
    }
}
