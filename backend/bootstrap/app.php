<?php

use App\Http\Middleware\ApiRequestMetricsMiddleware;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckReportExportPermission;
use App\Http\Middleware\CorrelationIdMiddleware;
use App\Http\Middleware\EnsurePortalAccess;
use App\Http\Middleware\EnsureTenantScope;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyFiscalWebhookSecret;
use App\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        channels: __DIR__.'/../routes/channels.php',
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            $apiFiles = glob(base_path('routes/api/*.php'));
            if (is_array($apiFiles)) {
                foreach ($apiFiles as $file) {
                    $middleware = basename($file) === 'supplier_portal.php'
                        ? ['api']
                        : ['api', 'auth:sanctum', 'check.tenant'];

                    Route::middleware($middleware)
                        ->prefix('api/v1')
                        ->group($file);
                }
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(SecurityHeaders::class);
        $middleware->prepend(CorrelationIdMiddleware::class);
        $middleware->append(ApiRequestMetricsMiddleware::class);

        // Confiar em proxies da rede Docker interna (Nginx container)
        $middleware->trustProxies(at: ['172.16.0.0/12', '10.0.0.0/8', '192.168.0.0/16']);

        // Fallback global: rotas sem throttle explícito recebem o limiter 'api' (120/min tenant-aware).
        // Rotas com throttle próprio (tenant-reads, tenant-mutations, etc.) usam o mais restritivo.
        $middleware->throttleApi('api');

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            return '/login';
        });

        $middleware->alias([
            'tenant.scope' => EnsureTenantScope::class,
            'check.tenant' => EnsureTenantScope::class,
            'check.permission' => CheckPermission::class,
            'check.report.export' => CheckReportExportPermission::class,
            'portal.access' => EnsurePortalAccess::class,
            'verify.webhook' => VerifyWebhookSignature::class,
            'verify.fiscal_webhook' => VerifyFiscalWebhookSecret::class,
        ]);

        // Token-based auth only (Bearer) — statefulApi() removed to avoid CSRF 419 on API routes
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Não autenticado.'], 401);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Acesso negado.'], 403);
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $model = class_basename($e->getModel());

                return response()->json([
                    'message' => "Recurso {$model} não encontrado.",
                ], 404);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Rota não encontrada.'], 404);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Dados inválidos.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Muitas requisições. Tente novamente em instantes.',
                ], 429, $e->getHeaders());
            }
        });

        $exceptions->render(function (RouteNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Não autenticado.'], 401);
            }
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => $e->getMessage() ?: 'Erro na requisição.'], $e->getStatusCode());
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                Log::error('API 500', [
                    'message' => $e->getMessage(),
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'path' => $request->path(),
                ]);
                $shouldExposeException = (bool) config('app.debug');

                return response()->json([
                    'message' => $shouldExposeException ? $e->getMessage() : 'Erro interno do servidor.',
                    ...($shouldExposeException ? [
                        'exception' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ] : []),
                ], 500);
            }
        });
    })->create();
