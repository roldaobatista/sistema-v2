<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Observability\ObservabilityMetricsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiRequestMetricsMiddleware
{
    public function __construct(
        private readonly ObservabilityMetricsService $metricsService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        if ($request->is('api/*') && ! $request->is('api/health')) {
            $this->metricsService->record(
                $request->path(),
                $request->method(),
                $response,
                (microtime(true) - $start) * 1000
            );
        }

        return $response;
    }
}
