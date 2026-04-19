<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regressão para sec-10 (re-auditoria Camada 1 2026-04-19).
 *
 * CLAUDE.md Lei 4: "Tenant ID sempre `$request->user()->current_tenant_id`.
 * Jamais do body." O middleware EnsureTenantScope anteriormente injetava
 * `tenant_id` no body via `$request->merge()`, criando caminho alternativo
 * proibido. Este teste garante que o merge não acontece.
 */
class TenantScopeNoBodyMergeTest extends TestCase
{
    public function test_middleware_does_not_merge_tenant_id_into_request_body(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->tenants()->attach($tenant->id, ['is_default' => true]);
        $user->forceFill(['current_tenant_id' => $tenant->id])->save();

        Sanctum::actingAs($user);

        $request = Request::create('/api/v1/customers', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new EnsureTenantScope;
        $middleware->handle($request, function (Request $passed) {
            $this->assertNull(
                $passed->input('tenant_id'),
                'Middleware não deve injetar tenant_id no body (sec-10 / Lei 4)'
            );
            $this->assertArrayNotHasKey(
                'tenant_id',
                $passed->all(),
                'Nenhum caminho do request deve expor tenant_id (sec-10)'
            );

            return response('ok');
        });

        $this->assertSame(
            $tenant->id,
            app('current_tenant_id'),
            'Binding current_tenant_id deve estar setado (canal legítimo)'
        );
    }

    public function test_middleware_preserves_body_when_user_posts_without_tenant_id(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->tenants()->attach($tenant->id, ['is_default' => true]);
        $user->forceFill(['current_tenant_id' => $tenant->id])->save();

        $request = Request::create('/api/v1/customers', 'POST', [
            'name' => 'Acme',
            'document' => '12345678900',
        ]);
        $request->setUserResolver(fn () => $user);

        $middleware = new EnsureTenantScope;
        $middleware->handle($request, function (Request $passed) {
            $this->assertSame('Acme', $passed->input('name'));
            $this->assertSame('12345678900', $passed->input('document'));
            $this->assertNull($passed->input('tenant_id'));

            return response('ok');
        });
    }

    public function test_switch_tenant_endpoint_receives_tenant_id_only_from_explicit_body(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->tenants()->attach([$tenant->id, $tenant2->id]);
        $user->forceFill(['current_tenant_id' => $tenant->id])->save();

        $request = Request::create('/api/v1/auth/switch-tenant', 'POST', [
            'tenant_id' => $tenant2->id,
        ]);
        $request->setUserResolver(fn () => $user);

        $middleware = new EnsureTenantScope;
        $middleware->handle($request, function (Request $passed) use ($tenant2) {
            $this->assertSame(
                $tenant2->id,
                (int) $passed->input('tenant_id'),
                'switch-tenant lê tenant_id do body — valor deve ser o enviado pelo usuário, não re-injetado pelo middleware'
            );

            return response('ok');
        });
    }
}
