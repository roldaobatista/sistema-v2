<?php

namespace Tests\Feature\Console;

use Database\Seeders\PermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AuditPermissionsCommandTest extends TestCase
{
    public function test_audit_permissions_command_reports_missing_permissions_from_recursive_api_routes(): void
    {
        $this->seed(PermissionsSeeder::class);

        $missingPermissions = $this->missingPermissionsFromApiRoutes();

        $exitCode = Artisan::call('camada1:audit-permissions');
        $output = Artisan::output();

        if ($missingPermissions === []) {
            $this->assertSame(0, $exitCode);
            $this->assertStringContainsString(
                'Todas as permissões das rotas existem no PermissionsSeeder.',
                $output
            );

            return;
        }

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString(
            'Permissões usadas nas rotas mas AUSENTES no PermissionsSeeder:',
            $output
        );

        foreach ($missingPermissions as $permission) {
            $this->assertStringContainsString($permission, $output);
        }
    }

    /**
     * @return array<int, string>
     */
    private function missingPermissionsFromApiRoutes(): array
    {
        $routePermissions = collect($this->collectRoutePermissions());
        $seededPermissions = Permission::query()->pluck('name')->all();

        return $routePermissions->diff($seededPermissions)->values()->all();
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
