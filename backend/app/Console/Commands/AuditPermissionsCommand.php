<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * Audita permissões: extrai as usadas nas rotas (check.permission) e compara
 * com o PermissionsSeeder. Reporta faltantes no seeder e opcionalmente órfãs (no seeder sem rota).
 */
class AuditPermissionsCommand extends Command
{
    protected $signature = 'camada1:audit-permissions {--orphans : Listar permissões no seeder que não aparecem em nenhuma rota}';

    protected $description = 'Audita se todas as permissões usadas nas rotas existem no PermissionsSeeder';

    public function handle(): int
    {
        $seederPath = base_path('database/seeders/PermissionsSeeder.php');

        if (! File::exists($seederPath)) {
            $this->error('PermissionsSeeder.php não encontrado.');

            return self::FAILURE;
        }

        $routePerms = collect();
        foreach ($this->collectRouteFiles() as $fullPath) {
            $routePerms = $routePerms->merge($this->extractPermissionsFromRoutes(File::get($fullPath)));
        }
        $routePerms = $routePerms->unique()->values();

        $seederPerms = $this->extractPermissionsFromSeeder(File::get($seederPath));

        $missing = $routePerms->diff($seederPerms)->sort()->values();
        if ($missing->isNotEmpty()) {
            $this->error('Permissões usadas nas rotas mas AUSENTES no PermissionsSeeder:');
            foreach ($missing as $p) {
                $this->line('  - '.$p);
            }
            $this->newLine();
        } else {
            $this->info('Todas as permissões das rotas existem no PermissionsSeeder.');
        }

        if ($this->option('orphans')) {
            $orphans = $seederPerms->diff($routePerms)->sort()->values();
            if ($orphans->isNotEmpty()) {
                $this->warn('Permissões no seeder que não aparecem em nenhuma rota (podem ser usadas em Policies/frontend):');
                foreach ($orphans->take(50) as $p) {
                    $this->line('  - '.$p);
                }
                if ($orphans->count() > 50) {
                    $this->line('  ... e mais '.($orphans->count() - 50).'.');
                }
            }
        }

        return $missing->isEmpty() ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return Collection<int, string>
     */
    private function collectRouteFiles(): Collection
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

        return collect($files);
    }

    private function extractPermissionsFromRoutes(string $content): Collection
    {
        $found = [];
        if (preg_match_all("/check\.permission:([a-zA-Z0-9_.|]+)/", $content, $matches)) {
            foreach ($matches[1] as $group) {
                foreach (explode('|', $group) as $perm) {
                    $found[trim($perm)] = true;
                }
            }
        }

        return collect(array_keys($found));
    }

    private function extractPermissionsFromSeeder(string $content): Collection
    {
        $found = [];
        if (preg_match_all("/^\s+'([a-zA-Z0-9_.]+)'\s*,?\s*(\/\/|$)/m", $content, $matches)) {
            foreach ($matches[1] as $perm) {
                if (str_contains($perm, '.')) {
                    $found[$perm] = true;
                }
            }
        }

        return collect(array_keys($found));
    }
}
