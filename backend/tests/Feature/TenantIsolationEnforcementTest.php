<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * META TEST: Tenant Isolation Enforcement
 * Automatically validates that tenant-aware models with API show routes
 * correctly isolate data from other tenants (returning 404).
 *
 * Uses registered route scanning instead of name guessing, since this
 * project registers routes manually rather than via apiResource().
 */
class TenantIsolationEnforcementTest extends TestCase
{
    public function test_all_tenant_aware_models_enforce_isolation_on_api()
    {
        $tenantA = Tenant::factory()->create(['name' => 'Tenant A Test']);
        $tenantB = Tenant::factory()->create(['name' => 'Tenant B Test']);

        $userA = User::factory()->create([
            'current_tenant_id' => $tenantA->id,
        ]);
        $userA->tenant_id = $tenantA->id;
        $userA->save();
        $userA->tenants()->attach($tenantA->id, ['is_default' => true]);
        $userA->assignRole('admin');

        Sanctum::actingAs($userA);
        app()->instance('current_tenant_id', $tenantA->id);

        // Build a map: model slug => show URI pattern from registered routes
        $showRoutes = $this->discoverShowRoutes();

        $testedRoutes = 0;
        $missingFactories = [];
        $skippedModels = [];

        $modelsPath = app_path('Models');
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($modelsPath));

        foreach ($iterator as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if (! preg_match('/class\s+([a-zA-Z0-9_]+)\s+extends\s+(Model|(?:[\w\\\]+)?Model)/i', $content, $m)) {
                continue;
            }

            $className = '\\App\\Models\\'.$m[1];
            if (! class_exists($className)) {
                continue;
            }

            try {
                $reflection = new \ReflectionClass($className);
                if ($reflection->isAbstract()) {
                    continue;
                }

                $traits = class_uses_recursive($className);
                $hasTenantTrait = in_array('App\Models\Concerns\BelongsToTenant', $traits);
                if (! $hasTenantTrait) {
                    continue;
                }

                // Try multiple slug patterns to match routes
                $slugs = $this->modelToSlugs($m[1]);
                $matchedUri = null;
                foreach ($slugs as $slug) {
                    if (isset($showRoutes[$slug])) {
                        $matchedUri = $showRoutes[$slug];
                        break;
                    }
                }

                if (! $matchedUri) {
                    $skippedModels[] = $m[1];
                    continue;
                }

                if (! method_exists($className, 'factory')) {
                    $missingFactories[] = $m[1];
                    continue;
                }

                try {
                    // Create record in Tenant B
                    setPermissionsTeamId($tenantB->id);
                    app()->instance('current_tenant_id', $tenantB->id);
                    $recordB = $className::factory()->create(['tenant_id' => $tenantB->id]);

                    // Act as Tenant A and request Tenant B's record
                    setPermissionsTeamId($tenantA->id);
                    app()->instance('current_tenant_id', $tenantA->id);
                    Sanctum::actingAs($userA);

                    $id = $recordB->id ?: $recordB->uuid;
                    $uri = str_replace('{id}', $id, $matchedUri);
                    $response = $this->getJson($uri);

                    $this->assertTrue(
                        in_array($response->getStatusCode(), [404, 403]),
                        "Model {$m[1]} at {$uri} returned {$response->getStatusCode()} instead of 404/403 for cross-tenant access"
                    );
                    $testedRoutes++;
                } catch (QueryException $e) {
                    // Skip if factory cannot create record (missing FK, etc.)
                } catch (\InvalidArgumentException $e) {
                    // Skip if route cannot be resolved
                } catch (\Error $e) {
                    // Skip if factory class doesn't exist or can't be resolved
                    $missingFactories[] = $m[1].' (factory error)';
                }
            } catch (\ReflectionException $e) {
            }
        }

        $this->assertGreaterThan(0, $testedRoutes,
            'Expected at least 1 tenant-aware route to be tested, but 0 were tested. '.
            'Missing factories: '.implode(', ', $missingFactories).'. '.
            'Skipped (no route): '.implode(', ', array_slice($skippedModels, 0, 20))
        );
    }

    /**
     * Scan all registered GET routes for show-like patterns (URI with single parameter).
     * Returns ['slug' => '/api/v1/slug/{id}']
     */
    private function discoverShowRoutes(): array
    {
        $showRoutes = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods())) {
                continue;
            }

            $uri = $route->uri();
            // Match patterns like api/v1/something/{param} (show routes)
            if (! preg_match('#^api/v1/(?:.*?/)?([a-z][-a-z0-9]*)/\{[^}]+\}$#', $uri, $m)) {
                continue;
            }

            $slug = $m[1];
            // Store with placeholder for the ID
            $showRoutes[$slug] = '/'.preg_replace('#\{[^}]+\}$#', '{id}', $uri);
        }

        return $showRoutes;
    }

    /**
     * Generate multiple possible route slugs for a model name.
     * E.g., WorkOrder => ['work-orders', 'work_orders']
     *        FleetVehicle => ['fleet-vehicles', 'vehicles']
     *        AccountReceivable => ['accounts-receivable', 'account-receivables']
     */
    private function modelToSlugs(string $modelName): array
    {
        $kebab = Str::kebab($modelName);
        $slugs = [];

        // Standard plural: work-order -> work-orders
        $slugs[] = Str::plural($kebab);

        // Plural only last word: work-order -> work-orders (same), account-receivable -> accounts-receivable
        $parts = explode('-', $kebab);
        if (count($parts) > 1) {
            $lastPart = array_pop($parts);
            $slugs[] = implode('-', $parts).'-'.Str::plural($lastPart);
            // Also try just the last word pluralized
            $slugs[] = Str::plural($lastPart);
        }

        return array_unique($slugs);
    }
}
