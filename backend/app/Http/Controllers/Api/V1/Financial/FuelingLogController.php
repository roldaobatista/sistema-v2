<?php

namespace App\Http\Controllers\Api\V1\Financial;

use App\Enums\FuelingLogStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\ApproveFuelingLogRequest;
use App\Http\Requests\Financial\StoreFuelingLogRequest;
use App\Http\Requests\Financial\UpdateFuelingLogRequest;
use App\Http\Resources\FuelingLogResource;
use App\Models\FuelingLog;
use App\Models\TechnicianCashFund;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FuelingLogController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FuelingLog::class);

        try {
            $tenantId = $this->tenantId();

            $query = FuelingLog::where('tenant_id', $tenantId)
                ->with(['user:id,name', 'workOrder:id,os_number', 'approver:id,name']);

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            if ($request->filled('date_from')) {
                $query->whereDate('fueling_date', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('fueling_date', '<=', $request->date_to);
            }
            if ($request->filled('search')) {
                $safe = SearchSanitizer::contains($request->search);
                $query->where(function ($q) use ($safe) {
                    $q->where('vehicle_plate', 'like', $safe)
                        ->orWhere('gas_station_name', 'like', $safe);
                });
            }

            $logs = $query->orderByDesc('fueling_date')->paginate(min((int) $request->input('per_page', 25), 100));

            return ApiResponse::paginated($logs, resourceClass: FuelingLogResource::class);
        } catch (\Exception $e) {
            Log::error('FuelingLog index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar abastecimentos', 500);
        }
    }

    public function show(Request $request, FuelingLog $fuelingLog): JsonResponse
    {
        $this->authorize('view', $fuelingLog);

        try {
            if ($fuelingLog->tenant_id !== $this->tenantId()) {
                return ApiResponse::message('Acesso negado', 403);
            }
            $fuelingLog->load(['user:id,name', 'workOrder:id,os_number', 'approver:id,name']);

            return ApiResponse::data(new FuelingLogResource($fuelingLog));
        } catch (\Exception $e) {
            Log::error('FuelingLog show failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao carregar abastecimento', 500);
        }
    }

    public function store(StoreFuelingLogRequest $request): JsonResponse
    {
        $this->authorize('create', FuelingLog::class);

        try {
            $tenantId = $this->tenantId();
            $validated = $request->validated();

            $receiptPath = null;
            if ($request->hasFile('receipt')) {
                $path = $request->file('receipt')->store("tenants/{$tenantId}/fueling-receipts", 'public');
                $receiptPath = "/storage/{$path}";
            }

            // Server-side recalculation: total_amount = liters * price_per_liter
            $calculatedTotal = bcmul((string) $validated['liters'], (string) $validated['price_per_liter'], 2);
            $totalAmount = $calculatedTotal;

            $log = DB::transaction(function () use ($validated, $tenantId, $request, $receiptPath, $totalAmount) {
                return FuelingLog::create([
                    'tenant_id' => $tenantId,
                    'user_id' => $request->user()->id,
                    'work_order_id' => $validated['work_order_id'] ?? null,
                    'vehicle_plate' => $validated['vehicle_plate'],
                    'odometer_km' => $validated['odometer_km'],
                    'gas_station_name' => $validated['gas_station'] ?? null,
                    'fuel_type' => $validated['fuel_type'],
                    'liters' => $validated['liters'],
                    'price_per_liter' => $validated['price_per_liter'],
                    'total_amount' => $totalAmount,
                    'fueling_date' => $validated['date'],
                    'notes' => $validated['notes'] ?? null,
                    'receipt_path' => $receiptPath,
                    'affects_technician_cash' => $validated['affects_technician_cash'] ?? false,
                    'status' => FuelingLogStatus::PENDING,
                ]);
            });

            return ApiResponse::data(new FuelingLogResource($log->load(['user:id,name', 'workOrder:id,os_number'])), 201);
        } catch (ValidationException $e) {
            return ApiResponse::message('Erro de validação', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            Log::error('FuelingLog store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar abastecimento', 500);
        }
    }

    public function update(UpdateFuelingLogRequest $request, FuelingLog $fuelingLog): JsonResponse
    {
        $this->authorize('update', $fuelingLog);

        try {
            $tenantId = $this->tenantId();

            if ($fuelingLog->tenant_id !== $tenantId) {
                return ApiResponse::message('Acesso negado', 403);
            }

            $validated = $request->validated();

            $data = [];
            if (isset($validated['vehicle_plate'])) {
                $data['vehicle_plate'] = $validated['vehicle_plate'];
            }
            if (isset($validated['odometer_km'])) {
                $data['odometer_km'] = $validated['odometer_km'];
            }
            if (array_key_exists('gas_station', $validated)) {
                $data['gas_station_name'] = $validated['gas_station'];
            }
            if (isset($validated['fuel_type'])) {
                $data['fuel_type'] = $validated['fuel_type'];
            }
            if (isset($validated['liters'])) {
                $data['liters'] = $validated['liters'];
            }
            if (isset($validated['price_per_liter'])) {
                $data['price_per_liter'] = $validated['price_per_liter'];
            }
            if (isset($validated['total_amount'])) {
                $data['total_amount'] = $validated['total_amount'];
            }
            if (isset($validated['date'])) {
                $data['fueling_date'] = $validated['date'];
            }
            if (array_key_exists('notes', $validated)) {
                $data['notes'] = $validated['notes'];
            }
            if (array_key_exists('work_order_id', $validated)) {
                $data['work_order_id'] = $validated['work_order_id'];
            }
            if (isset($validated['affects_technician_cash'])) {
                $data['affects_technician_cash'] = $validated['affects_technician_cash'];
            }

            if ($request->hasFile('receipt')) {
                if ($fuelingLog->receipt_path) {
                    $oldPath = str_replace('/storage/', '', $fuelingLog->receipt_path);
                    Storage::disk('public')->delete($oldPath);
                }
                $path = $request->file('receipt')->store("tenants/{$tenantId}/fueling-receipts", 'public');
                $data['receipt_path'] = "/storage/{$path}";
            }

            DB::transaction(function () use ($fuelingLog, $data) {
                $locked = FuelingLog::lockForUpdate()->findOrFail($fuelingLog->id);

                if ($locked->status !== FuelingLogStatus::PENDING) {
                    abort(422, 'Apenas registros pendentes podem ser editados');
                }

                $locked->update($data);
            });

            return ApiResponse::data(new FuelingLogResource($fuelingLog->fresh()->load(['user:id,name', 'workOrder:id,os_number'])));
        } catch (HttpException $e) {
            return ApiResponse::message($e->getMessage(), $e->getStatusCode());
        } catch (ValidationException $e) {
            return ApiResponse::message('Erro de validação', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            Log::error('FuelingLog update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar abastecimento', 500);
        }
    }

    public function approve(ApproveFuelingLogRequest $request, FuelingLog $fuelingLog): JsonResponse
    {
        $this->authorize('update', $fuelingLog);

        try {
            $tenantId = $this->tenantId();

            if ($fuelingLog->tenant_id !== $tenantId) {
                return ApiResponse::message('Acesso negado', 403);
            }

            $validated = $request->validated();
            $isApprove = $validated['action'] === 'approve';
            $rejectionReason = trim((string) ($validated['rejection_reason'] ?? ''));

            if (! $isApprove && $rejectionReason === '') {
                return ApiResponse::message('Informe o motivo da rejeição', 422, [
                    'errors' => ['rejection_reason' => ['O motivo da rejeição é obrigatório.']],
                ]);
            }

            DB::transaction(function () use ($fuelingLog, $isApprove, $rejectionReason, $request, $tenantId) {
                $locked = FuelingLog::lockForUpdate()->findOrFail($fuelingLog->id);

                if ($locked->status !== FuelingLogStatus::PENDING && $locked->status !== FuelingLogStatus::REJECTED) {
                    abort(422, 'Apenas registros pendentes podem ser aprovados/rejeitados');
                }

                $locked->update([
                    'status' => $isApprove ? FuelingLogStatus::APPROVED : FuelingLogStatus::REJECTED,
                    'approved_by' => $request->user()->id,
                    'approved_at' => now(),
                    'rejection_reason' => $isApprove ? null : $rejectionReason,
                ]);

                if ($isApprove && $locked->affects_technician_cash && $locked->user_id) {
                    $fund = TechnicianCashFund::getOrCreate($locked->user_id, $tenantId);
                    $fund->addDebit(
                        (string) $locked->total_amount,
                        "Abastecimento #{$locked->id}: {$locked->vehicle_plate} - {$locked->liters}L",
                        null,
                        $request->user()->id,
                        $locked->work_order_id,
                        allowNegative: true,
                    );
                }
            });

            return ApiResponse::data(new FuelingLogResource($fuelingLog->fresh()->load(['user:id,name', 'approver:id,name'])));
        } catch (HttpException $e) {
            return ApiResponse::message($e->getMessage(), $e->getStatusCode());
        } catch (ValidationException $e) {
            return ApiResponse::message('Erro de validação', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            Log::error('FuelingLog approve failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao aprovar/rejeitar abastecimento', 500);
        }
    }

    public function resubmit(Request $request, FuelingLog $fuelingLog): JsonResponse
    {
        $this->authorize('update', $fuelingLog);

        try {
            $tenantId = $this->tenantId();

            if ($fuelingLog->tenant_id !== $tenantId) {
                return ApiResponse::message('Acesso negado', 403);
            }

            DB::transaction(function () use ($fuelingLog) {
                $locked = FuelingLog::lockForUpdate()->findOrFail($fuelingLog->id);

                if ($locked->status !== FuelingLogStatus::REJECTED) {
                    abort(422, 'Apenas registros rejeitados podem ser resubmetidos');
                }

                $locked->update([
                    'status' => FuelingLogStatus::PENDING,
                    'approved_by' => null,
                    'approved_at' => null,
                    'rejection_reason' => null,
                ]);
            });

            return ApiResponse::data(new FuelingLogResource($fuelingLog->fresh()->load(['user:id,name', 'workOrder:id,os_number'])));
        } catch (HttpException $e) {
            return ApiResponse::message($e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            Log::error('FuelingLog resubmit failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao resubmeter abastecimento', 500);
        }
    }

    public function destroy(Request $request, FuelingLog $fuelingLog): JsonResponse
    {
        $this->authorize('delete', $fuelingLog);

        try {
            $tenantId = $this->tenantId();

            if ((int) $fuelingLog->tenant_id !== $tenantId) {
                return ApiResponse::message('Acesso negado', 403);
            }

            $receiptPath = $fuelingLog->receipt_path;

            DB::transaction(function () use ($fuelingLog) {
                $locked = FuelingLog::lockForUpdate()->findOrFail($fuelingLog->id);

                if ($locked->status !== FuelingLogStatus::PENDING) {
                    abort(422, 'Apenas registros pendentes podem ser excluídos');
                }

                $locked->delete();
            });

            if ($receiptPath) {
                $oldPath = str_replace('/storage/', '', $receiptPath);
                Storage::disk('public')->delete($oldPath);
            }

            return ApiResponse::message('Registro excluído');
        } catch (HttpException $e) {
            return ApiResponse::message($e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            Log::error('FuelingLog destroy failed', ['id' => $fuelingLog->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir registro', 500);
        }
    }
}
