<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicWorkOrderTrackingController extends Controller
{
    public function __invoke(string $workOrder, Request $request): JsonResponse|RedirectResponse
    {
        $workOrderId = filter_var($workOrder, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if (! is_int($workOrderId)) {
            return ApiResponse::message('OS não encontrada', 404);
        }

        $token = trim((string) $request->query('token', ''));
        $tenantId = $this->tenantIdFromToken($workOrderId, $token);

        if ($tenantId === null) {
            return ApiResponse::message('Token inválido', 403);
        }

        // Public HMAC links do not have an authenticated tenant context; the token
        // carries tenant_id and the query below is constrained to that signed tenant.
        $workOrderModel = WorkOrder::withoutGlobalScope('tenant')
            ->whereKey($workOrderId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $workOrderModel) {
            return ApiResponse::message('OS não encontrada', 404);
        }

        DB::table('qr_scans')->insert([
            'work_order_id' => $workOrderModel->id,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'scanned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AuditLog::log('public_viewed', "OS {$workOrderModel->business_number} acessada via link publico de tracking", $workOrderModel, [], [
            'access_type' => 'public_work_order_tracking',
            'token_tenant_id' => $tenantId,
        ]);

        return redirect(config('app.frontend_url', config('app.url'))."/portal/os/{$workOrderModel->id}");
    }

    public static function tokenFor(WorkOrder $workOrder): string
    {
        $tenantId = (int) $workOrder->tenant_id;
        $workOrderId = (int) $workOrder->id;

        return $tenantId.'.'.self::signatureFor($tenantId, $workOrderId);
    }

    private function tenantIdFromToken(int $workOrderId, string $token): ?int
    {
        if ($token === '' || ! str_contains($token, '.')) {
            return null;
        }

        [$tenantId, $signature] = explode('.', $token, 2);
        $tenantId = filter_var($tenantId, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if (! is_int($tenantId) || $signature === '') {
            return null;
        }

        $expected = self::signatureFor($tenantId, $workOrderId);

        return hash_equals($expected, $signature) ? $tenantId : null;
    }

    private static function signatureFor(int $tenantId, int $workOrderId): string
    {
        return hash_hmac('sha256', "work-order-track:{$tenantId}:{$workOrderId}", config('app.key'));
    }
}
