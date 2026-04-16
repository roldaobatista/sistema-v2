<?php

namespace Tests\Feature;

use Database\Seeders\PermissionsSeeder;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PermissionsSeederCoverageTest extends TestCase
{
    public function test_permissions_seeder_contains_every_permission_used_by_recursive_api_routes(): void
    {
        $this->seed(PermissionsSeeder::class);

        $routePermissions = collect($this->collectRoutePermissions());
        $seededPermissions = Permission::query()->pluck('name')->all();

        $missingPermissions = $routePermissions->diff($seededPermissions)->values()->all();

        $this->assertSame(
            [],
            $missingPermissions,
            'Permissões ausentes no PermissionsSeeder: '.implode(', ', $missingPermissions)
        );
    }

    /**
     * @return array<int, string>
     */
    private function collectRoutePermissions(): array
    {
        $permissions = [];

        foreach ($this->collectApiRouteFiles() as $file) {
            $content = File::get($file);

            if (! preg_match_all('/check\\.permission:([a-zA-Z0-9_.|]+)/', $content, $matches)) {
                continue;
            }

            foreach ($matches[1] as $group) {
                foreach (explode('|', $group) as $permission) {
                    $permissions[trim($permission)] = true;
                }
            }
        }

        $list = array_keys($permissions);
        sort($list);

        return $list;
    }

    /**
     * @return array<int, string>
     */
    private function collectApiRouteFiles(): array
    {
        $seen = [];
        $stack = [base_path('routes/api.php')];
        $files = [];

        while ($stack !== []) {
            $file = array_pop($stack);

            if (isset($seen[$file])) {
                continue;
            }

            $seen[$file] = true;

            if (! File::exists($file)) {
                continue;
            }

            $files[] = $file;
            $content = File::get($file);

            if (preg_match_all("/require\\s+base_path\\('([^']+)'\\)/", $content, $matches)) {
                foreach ($matches[1] as $relativePath) {
                    $stack[] = base_path($relativePath);
                }
            }
        }

        sort($files);

        return $files;
    }
}
