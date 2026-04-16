<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\PublicWorkOrderTrackingController;
use App\Models\Tenant;
use App\Models\WorkOrder;
use Closure;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionRouteSecurityTest extends TestCase
{
    public function test_all_v1_routes_except_logins_require_sanctum_authentication(): void
    {
        $publicUris = [
            'api/v1/login',
            'api/v1/auth/login',
            'api/v1/portal/login',
            'api/v1/forgot-password',
            'api/v1/reset-password',
            // Public aliases intentionally versioned under /api/v1: token, webhook,
            // proposal approval, QR tracking, and email pixel endpoints cannot require
            // Sanctum because they are consumed by external users or providers.
            'api/v1/rate/{token}',
            'api/v1/webhooks/whatsapp',
            'api/v1/webhooks/email',
            'api/v1/webhooks/whatsapp/status',
            'api/v1/webhooks/whatsapp/messages',
            'api/v1/webhooks/payment',
            'api/v1/quotes/{quote}/public-view',
            'api/v1/quotes/{quote}/public-pdf',
            'api/v1/quotes/{quote}/public-approve',
            'api/v1/quotes/proposal/{magicToken}',
            'api/v1/quotes/proposal/{magicToken}/approve',
            'api/v1/quotes/proposal/{magicToken}/reject',
            'api/v1/crm/quotes/sign/{token}',
            'api/v1/verify-certificate/{code}',
            'api/v1/equipment-qr/{token}',
            'api/v1/catalog/{slug}',
            'api/v1/track/os/{workOrder}',
            'api/v1/pixel/{trackingId}',
            'api/v1/fiscal/consulta-publica',
            'api/v1/fiscal/webhook',
            'api/v1/portal/guest/{token}',
            'api/v1/portal/guest/{token}/consume',
            'api/v1/supplier-portal/quotations/{token}',
            'api/v1/supplier-portal/quotations/{token}/answer',
            'api/v1/portal/supplier/quotations/{token}',
            'api/v1/portal/supplier/quotations/{token}/answer',
        ];

        $violations = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (LaravelRoute $route) => str_starts_with($route->uri(), 'api/v1/'))
            ->reject(fn (LaravelRoute $route) => in_array($route->uri(), $publicUris, true))
            ->filter(function (LaravelRoute $route): bool {
                $middleware = $route->gatherMiddleware();

                return ! collect($middleware)->contains(
                    fn (string $item) => str_contains($item, 'auth:sanctum') || str_contains($item, 'Authenticate:sanctum')
                );
            })
            ->map(fn (LaravelRoute $route) => $route->methods()[0].' '.$route->uri())
            ->values();

        $this->assertCount(
            0,
            $violations,
            'Rotas api/v1 sem auth:sanctum: '.$violations->implode(', ')
        );
    }

    public function test_critical_advanced_groups_have_permission_middleware(): void
    {
        $criticalPrefixes = [
            'api/v1/security/',
            'api/v1/integrations/',
            'api/v1/mobile/',
            'api/v1/fleet-advanced/',
            'api/v1/hr-advanced/',
            'api/v1/innovation/',
        ];

        $violations = collect(app('router')->getRoutes()->getRoutes())
            ->filter(function (LaravelRoute $route) use ($criticalPrefixes): bool {
                foreach ($criticalPrefixes as $prefix) {
                    if (str_starts_with($route->uri(), $prefix)) {
                        return true;
                    }
                }

                return false;
            })
            ->filter(function (LaravelRoute $route): bool {
                $middleware = $route->gatherMiddleware();

                return ! collect($middleware)->contains(
                    fn (string $item) => str_contains($item, 'check.permission:') || str_contains($item, 'CheckPermission')
                );
            })
            ->map(fn (LaravelRoute $route) => $route->methods()[0].' '.$route->uri())
            ->values();

        $this->assertCount(
            0,
            $violations,
            'Rotas críticas sem check.permission: '.$violations->implode(', ')
        );
    }

    public function test_public_endpoints_have_throttle_rate_limit(): void
    {
        $expectedThrottled = [
            ['method' => 'POST', 'uri' => 'api/v1/login'],
            ['method' => 'POST', 'uri' => 'api/v1/portal/login'],
            ['method' => 'POST', 'uri' => 'api/v1/rate/{token}'],
            ['method' => 'GET', 'uri' => 'api/v1/quotes/{quote}/public-view'],
            ['method' => 'POST', 'uri' => 'api/v1/quotes/{quote}/public-approve'],
            ['method' => 'GET', 'uri' => 'api/v1/track/os/{workOrder}'],
            ['method' => 'GET', 'uri' => 'api/v1/pixel/{trackingId}'],
            ['method' => 'POST', 'uri' => 'api/v1/webhooks/whatsapp'],
            ['method' => 'POST', 'uri' => 'api/v1/webhooks/email'],
        ];

        foreach ($expectedThrottled as $routeCheck) {
            $route = collect(app('router')->getRoutes()->getRoutes())->first(function (LaravelRoute $route) use ($routeCheck): bool {
                return $route->uri() === $routeCheck['uri']
                    && in_array($routeCheck['method'], $route->methods(), true);
            });

            $this->assertNotNull($route, "Rota não encontrada: {$routeCheck['method']} {$routeCheck['uri']}");

            $middleware = $route->gatherMiddleware();
            $hasThrottle = collect($middleware)->contains(
                fn (string $item) => str_contains($item, 'throttle:') || str_contains($item, 'ThrottleRequests')
            );

            $this->assertTrue(
                $hasThrottle,
                "Rota pública sem throttle: {$routeCheck['method']} {$routeCheck['uri']}"
            );
        }
    }

    public function test_public_work_order_tracking_route_uses_versioned_controller_not_closure(): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())->first(function (LaravelRoute $route): bool {
            return $route->uri() === 'api/v1/track/os/{workOrder}'
                && in_array('GET', $route->methods(), true);
        });

        $this->assertNotNull($route, 'Rota GET api/v1/track/os/{workOrder} não encontrada.');
        $this->assertFalse(
            $route->getAction('uses') instanceof Closure,
            'Rota pública de tracking de OS não pode ser implementada como Closure.'
        );
        $this->assertSame(
            'App\Http\Controllers\Api\V1\PublicWorkOrderTrackingController',
            ltrim((string) $route->getActionName(), '\\')
        );
    }

    public function test_public_work_order_tracking_rejects_sequential_id_without_signed_token(): void
    {
        $tenant = Tenant::factory()->create();
        app()->instance('current_tenant_id', $tenant->id);

        $workOrder = WorkOrder::factory()->create(['tenant_id' => $tenant->id]);

        app()->forgetInstance('current_tenant_id');

        $this->getJson("/api/v1/track/os/{$workOrder->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Token inválido');

        $this->assertSame(0, DB::table('qr_scans')->where('work_order_id', $workOrder->id)->count());
    }

    public function test_public_work_order_tracking_accepts_valid_signed_token_and_records_scan(): void
    {
        config()->set('app.frontend_url', 'https://portal.example.com');

        $tenant = Tenant::factory()->create();
        app()->instance('current_tenant_id', $tenant->id);

        $workOrder = WorkOrder::factory()->create(['tenant_id' => $tenant->id]);
        $token = PublicWorkOrderTrackingController::tokenFor($workOrder);

        app()->forgetInstance('current_tenant_id');

        $this->get("/api/v1/track/os/{$workOrder->id}?token={$token}")
            ->assertRedirect("https://portal.example.com/portal/os/{$workOrder->id}");

        $this->assertSame(1, DB::table('qr_scans')->where('work_order_id', $workOrder->id)->count());
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'action' => 'public_viewed',
            'auditable_type' => WorkOrder::class,
            'auditable_id' => $workOrder->id,
        ]);
    }

    public function test_legacy_public_aliases_outside_v1_are_not_registered(): void
    {
        $legacyAliases = [
            ['method' => 'POST', 'uri' => 'api/rate/{token}'],
            ['method' => 'GET', 'uri' => 'api/quotes/{quote}/public-view'],
            ['method' => 'GET', 'uri' => 'api/quotes/{quote}/public-pdf'],
            ['method' => 'POST', 'uri' => 'api/quotes/{quote}/public-approve'],
            ['method' => 'GET', 'uri' => 'api/quotes/proposal/{magicToken}'],
            ['method' => 'POST', 'uri' => 'api/quotes/proposal/{magicToken}/approve'],
            ['method' => 'POST', 'uri' => 'api/quotes/proposal/{magicToken}/reject'],
            ['method' => 'GET', 'uri' => 'api/track/os/{workOrder}'],
            ['method' => 'GET', 'uri' => 'api/pixel/{trackingId}'],
        ];

        foreach ($legacyAliases as $routeCheck) {
            $route = collect(app('router')->getRoutes()->getRoutes())->first(function (LaravelRoute $route) use ($routeCheck): bool {
                return $route->uri() === $routeCheck['uri']
                    && in_array($routeCheck['method'], $route->methods(), true);
            });

            $this->assertNull(
                $route,
                "Alias publico legado ainda registrado fora de /api/v1: {$routeCheck['method']} {$routeCheck['uri']}"
            );
        }
    }
}
