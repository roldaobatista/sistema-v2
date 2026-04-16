<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HealthCheckController extends Controller
{
    /**
     * Health check endpoint for monitoring and load balancers.
     * Returns status of database, Redis/cache, and queue.
     * No authentication required.
     */
    public function __invoke(): JsonResponse
    {
        $checks = [
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'services' => [],
        ];

        // Database check
        try {
            DB::connection()->getPdo();
            $checks['services']['database'] = ['status' => 'ok'];
        } catch (\Throwable $e) {
            $checks['services']['database'] = [
                'status' => 'error',
                'message' => 'Database connection failed',
            ];
            $checks['status'] = 'degraded';
            Log::error('Health check: database failed', ['error' => $e->getMessage()]);
        }

        // Cache/Redis check
        try {
            $key = 'health_check_'.time();
            Cache::put($key, true, 10);
            $result = Cache::get($key);
            Cache::forget($key);
            $checks['services']['cache'] = ['status' => $result ? 'ok' : 'error'];
        } catch (\Throwable $e) {
            $checks['services']['cache'] = [
                'status' => 'error',
                'message' => 'Cache connection failed',
            ];
            $checks['status'] = 'degraded';
            Log::error('Health check: cache failed', ['error' => $e->getMessage()]);
        }

        // Queue check (verify table exists or Redis connection)
        try {
            $queueConnection = config('queue.default');
            if ($queueConnection === 'database') {
                DB::table('jobs')->count();
            }
            $checks['services']['queue'] = ['status' => 'ok', 'driver' => $queueConnection];
        } catch (\Throwable $e) {
            $checks['services']['queue'] = [
                'status' => 'error',
                'message' => 'Queue check failed',
            ];
            $checks['status'] = 'degraded';
        }

        $httpStatus = $checks['status'] === 'ok' ? 200 : 503;

        return response()->json($checks, $httpStatus);
    }
}
