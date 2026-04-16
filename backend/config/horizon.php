<?php

use Illuminate\Support\Str;

return [
    'name' => env('HORIZON_NAME', env('APP_NAME')),
    'domain' => env('HORIZON_DOMAIN'),
    'path' => env('HORIZON_PATH', 'horizon'),
    'use' => 'default',
    'prefix' => env('HORIZON_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'),
    'middleware' => ['web'],
    'waits' => [
        'redis:default' => 60,
    ],
    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],
    'silenced' => [],
    'silenced_tags' => [],
    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],
    'fast_termination' => true,
    'memory_limit' => 128,
    'defaults' => [
        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['default', 'alerts', 'crm', 'email-classify', 'email-send', 'email-sync', 'emails', 'fiscal', 'reports', 'quality'],
            'balance' => 'auto',
            'maxProcesses' => 2,
            'memory' => 128,
            'timeout' => 60,
            'tries' => 3,
            'nice' => 0,
        ],
    ],
    'environments' => [
        'production' => [
            'supervisor-default' => [
                'maxProcesses' => 2,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
                'memory' => 128,
                'timeout' => 3600,
                'tries' => 3,
                'nice' => 0,
            ],
            'supervisor-long-running' => [
                'connection' => 'redis',
                'queue' => ['long-running'],
                'balance' => 'simple',
                'maxProcesses' => 1,
                'memory' => 192,
                'timeout' => 7200,
                'tries' => 1,
                'nice' => 10,
            ],
        ],
        'local' => [
            'supervisor-default' => [
                'maxProcesses' => 2,
                'memory' => 128,
                'timeout' => 60,
                'tries' => 3,
            ],
        ],
    ],
    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'public/**/*.php',
        'resources/**/*.php',
        'routes',
        'composer.lock',
        'composer.json',
        '.env',
    ],
];
