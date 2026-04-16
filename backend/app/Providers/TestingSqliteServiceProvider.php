<?php

namespace App\Providers;

use App\Database\TestingSQLiteConnection;
use Illuminate\Support\ServiceProvider;

/**
 * Registra a conexão SQLite customizada usada nos testes.
 *
 * - compileDropForeign → no-op (evita RuntimeException)
 * - compileIndex → IF NOT EXISTS (evita conflitos em rebuild)
 * - Pragmas de performance para SQLite in-memory
 *
 * Somente carregado quando DB_CONNECTION=sqlite (phpunit.xml).
 */
class TestingSqliteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (config('database.default') !== 'sqlite') {
            return;
        }

        // Registrar driver resolver customizado para sqlite
        $this->app['db']->extend('sqlite', function ($config, $name) {
            $config['name'] = $name;

            $pdo = new \PDO(
                'sqlite:'.($config['database'] === ':memory:' ? ':memory:' : $config['database']),
            );
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $connection = new TestingSQLiteConnection(
                $pdo,
                $config['database'] ?? ':memory:',
                $config['prefix'] ?? '',
                $config,
            );

            // Otimizações de performance para SQLite in-memory
            $connection->getPdo()->exec('PRAGMA synchronous = OFF');
            $connection->getPdo()->exec('PRAGMA cache_size = -20000');
            $connection->getPdo()->exec('PRAGMA temp_store = MEMORY');

            return $connection;
        });
    }

    public function boot(): void
    {
        // Nada a fazer no boot — tudo é configurado no register via driver resolver.
    }
}
