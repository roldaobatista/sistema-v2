<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tests\TestCase;

class RouteAliasPermissionAlignmentTest extends TestCase
{
    public function test_crm_customer_360_legacy_route_uses_customer_parameter_for_binding(): void
    {
        $route = $this->matchRoute('GET', '/api/v1/crm/customer-360/123');

        $this->assertSame('api/v1/crm/customer-360/{customer}', $route->uri());
        $this->assertSame('customer', $route->parameterNames()[0]);
    }

    public function test_inventory_routes_use_inventory_permissions_instead_of_movement_aliases(): void
    {
        $this->assertRouteHasMiddleware('GET', '/api/v1/inventories', 'check.permission:estoque.inventory.view');
        $this->assertRouteHasMiddleware('POST', '/api/v1/inventories', 'check.permission:estoque.inventory.create');
        $this->assertRouteHasMiddleware('GET', '/api/v1/inventory/inventories', 'check.permission:estoque.inventory.view');
        $this->assertRouteHasMiddleware('POST', '/api/v1/inventory/inventories', 'check.permission:estoque.inventory.create');
        $this->assertRouteHasMiddleware('POST', '/api/v1/stock/inventories', 'check.permission:estoque.inventory.create');
    }

    public function test_used_stock_alias_routes_use_granular_permissions(): void
    {
        $this->assertRouteHasMiddleware('GET', '/api/v1/stock/used-items', 'check.permission:estoque.used_stock.view');
        $this->assertRouteHasMiddleware('POST', '/api/v1/stock/used-items/1/report', 'check.permission:estoque.used_stock.report');
        $this->assertRouteHasMiddleware('POST', '/api/v1/stock/used-items/1/confirm-return', 'check.permission:estoque.used_stock.confirm');
    }

    public function test_commission_aliases_require_event_view_permission(): void
    {
        $this->assertRouteHasMiddleware('GET', '/api/v1/commissions', 'check.permission:commissions.event.view');
        $this->assertRouteHasMiddleware('GET', '/api/v1/commissions/events', 'check.permission:commissions.event.view');
    }

    public function test_commission_simulate_alias_uses_canonical_controller_and_create_permission(): void
    {
        $route = $this->matchRoute('POST', '/api/v1/commissions/simulate');

        $this->assertSame('api/v1/commissions/simulate', $route->uri());
        $this->assertStringEndsWith(
            'App\Http\Controllers\Api\V1\Financial\CommissionController@simulate',
            $route->getActionName()
        );
        $this->assertContains('check.permission:commissions.rule.create', $route->gatherMiddleware());
    }

    public function test_agenda_aliases_use_canonical_controller_and_binding_names(): void
    {
        $itemsRoute = $this->matchRoute('GET', '/api/v1/agenda-items/123');
        $completeRoute = $this->matchRoute('PUT', '/api/v1/agenda/123/complete');

        $this->assertSame('api/v1/agenda-items/{agendaItem}', $itemsRoute->uri());
        $this->assertSame('agendaItem', $itemsRoute->parameterNames()[0]);
        $this->assertStringEndsWith('App\Http\Controllers\Api\V1\AgendaController@show', $itemsRoute->getActionName());

        $this->assertSame('api/v1/agenda/{agendaItem}/complete', $completeRoute->uri());
        $this->assertSame('agendaItem', $completeRoute->parameterNames()[0]);
        $this->assertStringEndsWith('App\Http\Controllers\Api\V1\AgendaController@complete', $completeRoute->getActionName());
        $this->assertContains('check.permission:agenda.close.self|agenda.close.any', $completeRoute->gatherMiddleware());
    }

    private function assertRouteHasMiddleware(string $method, string $uri, string $middleware): void
    {
        $route = $this->matchRoute($method, $uri);

        $this->assertContains($middleware, $route->gatherMiddleware(), sprintf(
            'A rota [%s %s] não contém o middleware esperado [%s].',
            $method,
            $uri,
            $middleware
        ));
    }

    private function matchRoute(string $method, string $uri): Route
    {
        /** @var Route $route */
        $route = app('router')->getRoutes()->match(Request::create($uri, $method));

        return $route;
    }
}
