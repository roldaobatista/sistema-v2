<?php

namespace App\Http\Controllers;

use App\Http\Requests\Camera\ReorderCamerasRequest;
use App\Http\Requests\Camera\StoreCameraRequest;
use App\Http\Requests\Camera\TestConnectionCameraRequest;
use App\Http\Requests\Camera\UpdateCameraRequest;
use App\Models\Camera;
use App\Support\ApiResponse;
use App\Support\UrlSecurity;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CameraController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        $cameras = Camera::where('tenant_id', $this->tenantId())
            ->orderBy('position')
            ->paginate(min((int) request()->input('per_page', 25), 100));

        return ApiResponse::paginated($cameras);
    }

    public function store(StoreCameraRequest $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $data = $request->validated();
        $data['tenant_id'] = $tenantId;

        if (! isset($data['position'])) {
            $data['position'] = Camera::where('tenant_id', $data['tenant_id'])->max('position') + 1;
        }

        try {
            $camera = DB::transaction(fn () => Camera::create($data));

            return ApiResponse::data($camera, 201);
        } catch (\Throwable $e) {
            Log::error('Camera store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar câmera', 500);
        }
    }

    public function update(UpdateCameraRequest $request, Camera $camera): JsonResponse
    {
        $tenantId = $this->tenantId();
        if ($camera->tenant_id !== $tenantId) {
            abort(403);
        }

        $data = $request->validated();

        try {
            $camera->update($data);

            return ApiResponse::data($camera->fresh());
        } catch (\Throwable $e) {
            Log::error('Camera update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar câmera', 500);
        }
    }

    public function destroy(Camera $camera): JsonResponse
    {
        if ($camera->tenant_id !== $this->tenantId()) {
            abort(403);
        }

        try {
            $camera->delete();

            return ApiResponse::message('Câmera removida');
        } catch (\Throwable $e) {
            Log::error('Camera destroy failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao remover câmera', 500);
        }
    }

    public function reorder(ReorderCamerasRequest $request): JsonResponse
    {
        $data = $request->validated();
        $tenantId = $this->tenantId();

        try {
            DB::transaction(function () use ($data, $tenantId) {
                foreach ($data['order'] as $position => $cameraId) {
                    Camera::where('id', $cameraId)
                        ->where('tenant_id', $tenantId)
                        ->update(['position' => $position]);
                }
            });

            return ApiResponse::message('Ordem atualizada');
        } catch (\Throwable $e) {
            Log::error('Camera reorder failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao reordenar câmeras', 500);
        }
    }

    public function health(): JsonResponse
    {
        $cameras = Camera::where('tenant_id', $this->tenantId())
            ->where('is_active', true)
            ->orderBy('position')
            ->get();

        $results = $cameras->map(function (Camera $camera) {
            $status = 'offline';
            $latencyMs = null;

            $url = $camera->stream_url;
            $start = microtime(true);

            if (! UrlSecurity::isSafeUrl($url)) {
                return [
                    'id' => $camera->id,
                    'name' => $camera->name,
                    'status' => 'offline',
                    'latency_ms' => null,
                ];
            }

            try {
                if (str_starts_with($url, 'rtsp://')) {
                    $parsed = parse_url($url);
                    $host = $parsed['host'] ?? '';
                    $port = $parsed['port'] ?? 554;

                    if ($host) {
                        $connection = @fsockopen($host, $port, $errno, $errstr, 2);
                        if ($connection) {
                            fclose($connection);
                            $status = 'online';
                            $latencyMs = round((microtime(true) - $start) * 1000);
                        }
                    }
                } elseif (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                    $ch = curl_init($url);
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 3,
                        CURLOPT_CONNECTTIMEOUT => 2,
                        CURLOPT_NOBODY => true,
                    ]);
                    curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($httpCode >= 200 && $httpCode < 400) {
                        $status = 'online';
                        $latencyMs = round((microtime(true) - $start) * 1000);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Camera health check failed', [
                    'camera_id' => $camera->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return [
                'id' => $camera->id,
                'name' => $camera->name,
                'status' => $status,
                'latency_ms' => $latencyMs,
            ];
        });

        return ApiResponse::data($results);
    }

    public function testConnection(TestConnectionCameraRequest $request): JsonResponse
    {
        $data = $request->validated();
        $url = $data['stream_url'];
        $reachable = false;

        if (! UrlSecurity::isSafeUrl($url)) {
            return ApiResponse::data([
                'reachable' => false,
                'url' => $url,
            ]);
        }

        if (str_starts_with($url, 'rtsp://')) {
            $parsed = parse_url($url);
            $host = $parsed['host'] ?? '';
            $port = $parsed['port'] ?? 554;

            if ($host) {
                $connection = @fsockopen($host, $port, $errno, $errstr, 3);
                if ($connection) {
                    fclose($connection);
                    $reachable = true;
                }
            }
        } elseif (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_NOBODY => true,
            ]);
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $reachable = $httpCode >= 200 && $httpCode < 400;
        }

        return ApiResponse::data([
            'reachable' => $reachable,
            'url' => $url,
        ]);
    }
}
