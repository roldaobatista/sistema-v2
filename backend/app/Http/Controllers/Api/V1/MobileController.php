<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RemainingModules\AddToSyncQueueRequest;
use App\Http\Requests\RemainingModules\CreatePrintJobRequest;
use App\Http\Requests\RemainingModules\RespondToNotificationRequest;
use App\Http\Requests\RemainingModules\StorePhotoAnnotationRequest;
use App\Http\Requests\RemainingModules\StoreSignatureRequest;
use App\Http\Requests\RemainingModules\StoreThermalReadingRequest;
use App\Http\Requests\RemainingModules\StoreVoiceReportRequest;
use App\Http\Requests\RemainingModules\UpdateBiometricConfigRequest;
use App\Http\Requests\RemainingModules\UpdateKioskConfigRequest;
use App\Http\Requests\RemainingModules\UpdatePreferencesRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MobileController extends Controller
{
    private function tenantId(): int
    {
        $user = auth()->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    public function userPreferences(): JsonResponse
    {
        $prefs = DB::table('user_preferences')
            ->where('user_id', auth()->id())
            ->first();

        return ApiResponse::data($prefs ?? ['dark_mode' => false, 'language' => 'pt_BR', 'notifications' => true]);
    }

    public function updatePreferences(UpdatePreferencesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::table('user_preferences')->updateOrInsert(
                ['user_id' => auth()->id()],
                array_merge($validated, ['updated_at' => now()])
            );

            return ApiResponse::message('Preferências atualizadas');
        } catch (\Exception $e) {
            Log::error('Preferences update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar preferências', 500);
        }
    }

    public function syncQueue(): JsonResponse
    {
        $queue = DB::table('sync_queue')
            ->where('user_id', auth()->id())
            ->where('status', '!=', 'completed')
            ->orderBy('created_at')
            ->get();

        return ApiResponse::data($queue);
    }

    public function addToSyncQueue(AddToSyncQueueRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $id = DB::table('sync_queue')->insertGetId([
                'tenant_id' => $this->tenantId(),
                'user_id' => auth()->id(),
                'entity_type' => $validated['entity_type'],
                'entity_id' => $validated['entity_id'] ?? null,
                'action' => $validated['action'],
                'payload' => json_encode($validated['payload']),
                'status' => 'pending',
                'created_at' => now(),
            ]);

            return ApiResponse::data(['id' => $id], 201, ['message' => 'Adicionado à fila de sincronização']);
        } catch (\Exception $e) {
            Log::error('Sync queue add failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao adicionar à fila', 500);
        }
    }

    public function interactiveNotifications(): JsonResponse
    {
        $notifications = DB::table('mobile_notifications')
            ->where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return ApiResponse::data($notifications);
    }

    public function respondToNotification(RespondToNotificationRequest $request, int $notificationId): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::table('mobile_notifications')
                ->where('id', $notificationId)
                ->where('user_id', auth()->id())
                ->update([
                    'response_action' => $validated['action'],
                    'responded_at' => now(),
                ]);

            return ApiResponse::message('Ação registrada');
        } catch (\Exception $e) {
            Log::error('Notification response failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar ação', 500);
        }
    }

    public function barcodeLookup(Request $request): JsonResponse
    {
        $code = $request->input('code');

        $product = DB::table('products')
            ->where('tenant_id', $this->tenantId())
            ->where(function ($q) use ($code) {
                $q->where('barcode', $code)->orWhere('sku', $code)->orWhere('serial_number', $code);
            })
            ->first();

        if (! $product) {
            return ApiResponse::message('Produto não encontrado', 404, ['code' => $code]);
        }

        return ApiResponse::data($product);
    }

    public function storeSignature(StoreSignatureRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $id = DB::table('digital_signatures')->insertGetId([
                'tenant_id' => $this->tenantId(),
                'work_order_id' => $validated['work_order_id'],
                'signature_data' => $validated['signature_data'],
                'signer_name' => $validated['signer_name'],
                'signer_role' => $validated['signer_role'] ?? 'customer',
                'signed_at' => now(),
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            return ApiResponse::data(['id' => $id], 201, ['message' => 'Assinatura registrada']);
        } catch (\Exception $e) {
            Log::error('Signature storage failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar assinatura', 500);
        }
    }

    public function printJobs(): JsonResponse
    {
        $jobs = DB::table('print_jobs')
            ->where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return ApiResponse::data($jobs);
    }

    public function createPrintJob(CreatePrintJobRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $id = DB::table('print_jobs')->insertGetId([
                'tenant_id' => $this->tenantId(),
                'user_id' => auth()->id(),
                'document_type' => $validated['document_type'],
                'document_id' => $validated['document_id'],
                'printer_type' => $validated['printer_type'],
                'copies' => $validated['copies'] ?? 1,
                'status' => 'queued',
                'created_at' => now(),
            ]);

            return ApiResponse::data(['id' => $id], 201, ['message' => 'Job de impressão criado']);
        } catch (\Exception $e) {
            Log::error('Print job creation failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar job de impressão', 500);
        }
    }

    public function storeVoiceReport(StoreVoiceReportRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $id = DB::table('voice_reports')->insertGetId([
                'tenant_id' => $this->tenantId(),
                'user_id' => auth()->id(),
                'work_order_id' => $validated['work_order_id'],
                'transcription' => $validated['transcription'],
                'duration_seconds' => $validated['duration_seconds'] ?? null,
                'language' => $validated['language'] ?? 'pt_BR',
                'created_at' => now(),
            ]);

            return ApiResponse::data(['id' => $id], 201, ['message' => 'Relatório por voz registrado']);
        } catch (\Exception $e) {
            Log::error('Voice report storage failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar relatório', 500);
        }
    }

    public function biometricConfig(): JsonResponse
    {
        $config = DB::table('biometric_configs')
            ->where('user_id', auth()->id())
            ->first();

        return ApiResponse::data($config ?? ['enabled' => false, 'type' => null]);
    }

    public function updateBiometricConfig(UpdateBiometricConfigRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::table('biometric_configs')->updateOrInsert(
                ['user_id' => auth()->id()],
                array_merge($validated, ['updated_at' => now()])
            );

            return ApiResponse::message('Configuração biométrica atualizada');
        } catch (\Exception $e) {
            Log::error('Biometric config update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar configuração biométrica', 500);
        }
    }

    public function storePhotoAnnotation(StorePhotoAnnotationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $id = DB::table('photo_annotations')->insertGetId([
                'tenant_id' => $this->tenantId(),
                'work_order_id' => $validated['work_order_id'],
                'user_id' => auth()->id(),
                'image_path' => $validated['image_path'],
                'annotations' => json_encode($validated['annotations']),
                'created_at' => now(),
            ]);

            return ApiResponse::data(['id' => $id], 201, ['message' => 'Anotação salva']);
        } catch (\Exception $e) {
            Log::error('Photo annotation storage failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao salvar anotação', 500);
        }
    }

    public function storeThermalReading(StoreThermalReadingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $id = DB::table('thermal_readings')->insertGetId([
                'tenant_id' => $this->tenantId(),
                'work_order_id' => $validated['work_order_id'],
                'equipment_id' => $validated['equipment_id'] ?? null,
                'temperature' => $validated['temperature'],
                'unit' => $validated['unit'],
                'image_path' => $validated['image_path'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'measured_by' => auth()->id(),
                'measured_at' => now(),
                'created_at' => now(),
            ]);

            DB::commit();

            return ApiResponse::data(['id' => $id], 201, ['message' => 'Leitura térmica registrada']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Thermal reading failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar leitura térmica', 500);
        }
    }

    public function kioskConfig(): JsonResponse
    {
        try {
            $config = DB::table('kiosk_configs')
                ->where('tenant_id', $this->tenantId())
                ->first();
        } catch (\Throwable) {
            $config = null;
        }

        return ApiResponse::data($config ?? [
            'enabled' => false,
            'allowed_pages' => ['dashboard', 'work-orders'],
            'idle_timeout_seconds' => 300,
            'auto_logout' => true,
            'show_header' => false,
        ]);
    }

    public function updateKioskConfig(UpdateKioskConfigRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::table('kiosk_configs')->updateOrInsert(
                ['tenant_id' => $this->tenantId()],
                [
                    'enabled' => $validated['enabled'],
                    'allowed_pages' => json_encode($validated['allowed_pages'] ?? ['dashboard']),
                    'idle_timeout_seconds' => $validated['idle_timeout_seconds'] ?? 300,
                    'auto_logout' => $validated['auto_logout'] ?? true,
                    'show_header' => $validated['show_header'] ?? false,
                    'pin_code' => isset($validated['pin_code']) ? bcrypt($validated['pin_code']) : null,
                    'updated_at' => now(),
                ]
            );

            return ApiResponse::message('Configuração de quiosque atualizada');
        } catch (\Exception $e) {
            Log::error('Kiosk config update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar configuração de quiosque', 500);
        }
    }

    public function offlineMapRegions(): JsonResponse
    {
        $regions = DB::table('offline_map_regions')
            ->where('tenant_id', $this->tenantId())
            ->where('is_active', true)
            ->select('id', 'name', 'bounds', 'zoom_min', 'zoom_max', 'estimated_size_mb', 'updated_at')
            ->orderBy('name')
            ->get()
            ->map(function ($region) {
                $region->bounds = json_decode($region->bounds);

                return $region;
            });

        if ($regions->isEmpty()) {
            $regions = collect([
                [
                    'id' => 'default',
                    'name' => 'Região Metropolitana',
                    'bounds' => ['north' => -15.5, 'south' => -16.0, 'east' => -55.8, 'west' => -56.3],
                    'zoom_min' => 10,
                    'zoom_max' => 16,
                    'estimated_size_mb' => 45,
                ],
            ]);
        }

        return ApiResponse::data($regions);
    }
}
