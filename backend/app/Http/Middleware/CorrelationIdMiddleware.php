<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CorrelationIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = (string) ($request->header('X-Correlation-ID')
            ?: $request->header('X-Request-ID')
            ?: Str::uuid()->toString());

        $request->headers->set('X-Correlation-ID', $correlationId);
        $request->headers->set('X-Request-ID', $correlationId);

        app()->instance('correlation_id', $correlationId);
        app()->instance('request_id', $correlationId);

        Log::shareContext([
            'correlation_id' => $correlationId,
            'request_id' => $correlationId,
            'tenant_id' => $request->user()?->current_tenant_id,
            'user_id' => $request->user()?->id,
            'path' => '/'.$request->path(),
            'method' => $request->method(),
        ]);

        $response = $next($request);
        $response->headers->set('X-Correlation-ID', $correlationId);
        $response->headers->set('X-Request-ID', $correlationId);

        return $response;
    }
}
