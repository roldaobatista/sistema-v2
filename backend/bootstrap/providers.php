<?php

use App\Providers\AppServiceProvider;
use App\Providers\EventServiceProvider;
use Laravel\Boost\BoostServiceProvider;
use Laravel\Mcp\Server\McpServiceProvider;
use Laravel\Telescope\TelescopeServiceProvider;

$providers = [
    AppServiceProvider::class,
    EventServiceProvider::class,
];

if (
    class_exists(TelescopeServiceProvider::class)
    && filter_var($_ENV['TELESCOPE_ENABLED'] ?? getenv('TELESCOPE_ENABLED') ?? true, FILTER_VALIDATE_BOOL)
) {
    $providers[] = App\Providers\TelescopeServiceProvider::class;
    $providers[] = TelescopeServiceProvider::class;
}

if (class_exists(BoostServiceProvider::class)) {
    $providers[] = BoostServiceProvider::class;
}

if (class_exists(McpServiceProvider::class)) {
    $providers[] = McpServiceProvider::class;
}

return $providers;
