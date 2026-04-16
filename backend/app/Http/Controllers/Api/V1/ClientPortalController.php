<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\CreateServiceCallFromPortalRequest;
use App\Models\ServiceCall;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use App\Support\FilenameSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ClientPortalController extends Controller
{
    use ResolvesCurrentTenant;

    private function customerId(Request $request): int
    {
        $user = $request->user();

        if (empty($user->customer_id)) {
            abort(403, 'Este endpoint é restrito a usuários vinculados a um cliente.');
        }

        return (int) $user->customer_id;
    }

    public function createServiceCallFromPortal(CreateServiceCallFromPortalRequest $request): JsonResponse
    {
        $data = $request->validated();

        $tenantId = $this->resolvedTenantId();
        $customerId = $this->customerId($request);
        $priority = $data['priority'] ?? 'normal';

        try {
            $serviceCall = ServiceCall::query()->create([
                'tenant_id' => $tenantId,
                'call_number' => ServiceCall::nextNumber($tenantId),
                'customer_id' => $customerId,
                'priority' => $priority,
                'status' => ServiceCall::STATUS_OPEN,
                'observations' => $this->buildPortalObservations($data['subject'], $data['description']),
                'created_by' => $request->user()?->id,
            ]);

            if (! empty($data['equipment_id']) && Schema::hasTable('service_call_equipments')) {
                DB::table('service_call_equipments')->insert([
                    'service_call_id' => $serviceCall->id,
                    'equipment_id' => (int) $data['equipment_id'],
                    'observations' => 'Equipamento informado via portal do cliente.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store("service-calls/{$serviceCall->id}", 'public');

                    DB::table('service_call_attachments')->insert([
                        'service_call_id' => $serviceCall->id,
                        'file_path' => $path,
                        'file_name' => FilenameSanitizer::sanitize($file->getClientOriginalName()),
                        'file_size' => $file->getSize(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            return ApiResponse::data([
                'service_call_id' => $serviceCall->id,
            ], 201, [
                'message' => 'Chamado criado com sucesso.',
            ]);
        } catch (\Throwable $e) {
            Log::error('ClientPortalController::createServiceCallFromPortal failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar chamado.', 500);
        }
    }

    public function trackWorkOrders(Request $request): JsonResponse
    {
        $tenantId = $this->resolvedTenantId();
        $customerId = $this->customerId($request);

        $workOrders = WorkOrder::query()
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->whereNotIn('status', [WorkOrder::STATUS_CANCELLED])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'os_number', 'status', 'priority', 'description', 'created_at', 'sla_due_at', 'assigned_to', 'completed_at']);

        $woIds = $workOrders->pluck('id');
        $timelines = DB::table('work_order_status_history')
            ->whereIn('work_order_id', $woIds)
            ->orderBy('created_at')
            ->get(['work_order_id', 'from_status', 'to_status', 'notes', 'created_at'])
            ->groupBy('work_order_id');

        $techIds = $workOrders->pluck('assigned_to')->filter()->unique();
        $techNames = $techIds->isNotEmpty()
            ? DB::table('users')->whereIn('id', $techIds)->pluck('name', 'id')
            : collect();

        $workOrders->each(function ($workOrder) use ($timelines, $techNames) {
            $workOrder->timeline = $timelines->get($workOrder->id, collect());
            $workOrder->technician_name = $workOrder->assigned_to
                ? $techNames->get($workOrder->assigned_to)
                : null;
        });

        return ApiResponse::data($workOrders);
    }

    public function trackServiceCalls(Request $request): JsonResponse
    {
        $tenantId = $this->resolvedTenantId();
        $customerId = $this->customerId($request);

        $calls = ServiceCall::query()
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function (ServiceCall $call) {
                return [
                    'id' => $call->id,
                    'call_number' => $call->call_number,
                    'subject' => $this->extractPortalSubject($call),
                    'description' => $call->observations,
                    'status' => $call->status?->value ?? $call->status,
                    'priority' => $call->priority,
                    'created_at' => $call->created_at,
                    'updated_at' => $call->updated_at,
                ];
            })
            ->values();

        return ApiResponse::data($calls);
    }

    public function calibrationCertificates(Request $request): JsonResponse
    {
        $tenantId = $this->resolvedTenantId();
        $customerId = $this->customerId($request);

        if (! Schema::hasTable('calibration_certificates')) {
            // Table not yet created — return empty paginated response
            $empty = new LengthAwarePaginator([], 0, 20, 1);

            return ApiResponse::paginated($empty);
        }

        $certificates = DB::table('calibration_certificates')
            ->join('equipments', 'calibration_certificates.equipment_id', '=', 'equipments.id')
            ->where('equipments.tenant_id', $tenantId)
            ->where('equipments.customer_id', $customerId)
            ->select(
                'calibration_certificates.*',
                'equipments.name as equipment_name',
                'equipments.serial_number'
            )
            ->orderByDesc('calibration_certificates.date')
            ->paginate(20);

        return ApiResponse::paginated($certificates);
    }

    public function downloadCertificate(Request $request, int $certificateId): JsonResponse
    {
        $tenantId = $this->resolvedTenantId();
        $customerId = $this->customerId($request);

        if (! Schema::hasTable('calibration_certificates')) {
            return ApiResponse::message('Registro não encontrado.', 404);
        }

        $certificate = DB::table('calibration_certificates')
            ->join('equipments', 'calibration_certificates.equipment_id', '=', 'equipments.id')
            ->where('calibration_certificates.id', $certificateId)
            ->where('calibration_certificates.tenant_id', $tenantId)
            ->where('equipments.customer_id', $customerId)
            ->select('calibration_certificates.*')
            ->first();

        if (! $certificate) {
            return ApiResponse::message('Registro não encontrado.', 404);
        }

        return ApiResponse::data([
            'certificate' => $certificate,
            'download_url' => $certificate->file_path ? asset('storage/'.$certificate->file_path) : null,
            'qr_verification_url' => config('app.url')."/api/v1/verify-certificate/{$certificate->verification_code}",
        ]);
    }

    private function buildPortalObservations(string $subject, string $description): string
    {
        return trim($subject."\n\n".$description);
    }

    private function extractPortalSubject(ServiceCall $call): string
    {
        $observations = trim((string) $call->observations);

        if ($observations === '') {
            return "Chamado {$call->call_number}";
        }

        $firstLine = trim(strtok($observations, "\n"));

        return $firstLine !== '' ? $firstLine : "Chamado {$call->call_number}";
    }
}
