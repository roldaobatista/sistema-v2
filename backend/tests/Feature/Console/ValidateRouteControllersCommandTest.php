<?php

namespace Tests\Feature\Console;

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ValidateRouteControllersCommandTest extends TestCase
{
    public function test_validate_route_controllers_command_checks_all_api_routes_including_routes_outside_api_namespace(): void
    {
        $apiRoutes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (LaravelRoute $route): bool => $this->isApiRoute($route))
            ->values();

        $outsideApiNamespaceRoute = $apiRoutes->first(
            fn (LaravelRoute $route): bool => ! str_contains($route->getActionName(), 'App\\Http\\Controllers\\Api\\')
        );

        $this->assertNotNull($outsideApiNamespaceRoute);

        $expectedCount = $apiRoutes->count();

        Artisan::call('camada2:validate-routes', ['--list' => true]);
        $output = Artisan::output();

        $this->assertStringContainsString(
            "Todas as rotas verificadas ({$expectedCount} rotas) apontam para controller/método existente.",
            $output
        );
        $this->assertStringContainsString($outsideApiNamespaceRoute->uri(), $output);
        $this->assertStringContainsString(
            str_replace('@', '::', $outsideApiNamespaceRoute->getActionName()),
            $output
        );
    }

    private function isApiRoute(LaravelRoute $route): bool
    {
        return ! $route->isFallback && $route->getActionName() !== 'Closure' && str_starts_with($route->uri(), 'api/');
    }
}
