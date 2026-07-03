<?php
// 跟踪 ID 中间件
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TraceIdMiddleware
{
    public function handle($request, Closure $next)
    {
        $traceId = (string) Str::uuid();

        $request->attributes->set('trace_id', $traceId);

        Log::withContext([
            'trace_id' => $traceId,
        ]);

        return $next($request);
    }
}
