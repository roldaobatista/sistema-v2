<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * Valida que todas as rotas da API apontam para controller e método existentes.
 */
class ValidateRouteControllersCommand extends Command
{
    protected $signature = 'camada2:validate-routes {--list : Listar cada rota verificada}';

    protected $description = 'Valida que cada rota API aponta para controller/método existente';

    public function handle(): int
    {
        $this->info('Validando rotas da API...');
        $invalid = [];
        $checked = 0;

        foreach (RouteFacade::getRoutes() as $route) {
            if (! $route->isFallback && $this->isApiRoute($route)) {
                $action = $route->getActionName();
                if ($action === 'Closure') {
                    continue;
                }
                $checked++;
                [$class, $method] = $this->parseAction($action);
                if (! $class) {
                    $invalid[] = [$route->uri(), $action, 'Não foi possível extrair a classe'];
                    continue;
                }
                if (! $method) {
                    $method = '__invoke';
                }
                if (! class_exists($class)) {
                    $invalid[] = [$route->uri(), $action, "Classe não existe: {$class}"];
                    continue;
                }
                if (! method_exists($class, $method)) {
                    $invalid[] = [$route->uri(), $action, "Método não existe: {$class}::{$method}"];
                    continue;
                }
                if ($this->option('list')) {
                    $this->line("  [OK] {$route->uri()} → {$class}::{$method}");
                }
            }
        }

        if (! empty($invalid)) {
            $this->error('Rotas com controller/método inexistente:');
            foreach ($invalid as [$uri, $action, $reason]) {
                $this->line("  URI: {$uri}");
                $this->line("  Action: {$action}");
                $this->line("  Motivo: {$reason}");
                $this->newLine();
            }
            $this->error(count($invalid).' rota(s) inválida(s).');

            return self::FAILURE;
        }

        $this->info("Todas as rotas verificadas ({$checked} rotas) apontam para controller/método existente.");

        return self::SUCCESS;
    }

    private function isApiRoute(Route $route): bool
    {
        return str_starts_with($route->uri(), 'api/');
    }

    private function parseAction(string $action): array
    {
        $class = '';
        $method = '';
        if (str_contains($action, '::')) {
            $parts = explode('::', $action);
            $class = trim($parts[0]);
            $method = trim($parts[1] ?? '');
        } elseif (str_contains($action, '@')) {
            $parts = explode('@', $action);
            $class = trim($parts[0]);
            $method = trim($parts[1] ?? '');
        } else {
            $class = trim($action);
        }
        $class = ltrim($class, '\\');
        if ($class && ! str_starts_with($class, 'App\\')) {
            $class = 'App\\Http\\Controllers\\'.$class;
        }
        if (! $method && $class) {
            $method = '__invoke';
        }

        return [$class, $method];
    }
}
