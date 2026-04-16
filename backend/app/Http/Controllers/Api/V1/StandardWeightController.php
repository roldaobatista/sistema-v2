<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Equipment\StoreStandardWeightRequest;
use App\Http\Requests\Equipment\UpdateStandardWeightRequest;
use App\Http\Resources\StandardWeightResource;
use App\Models\StandardWeight;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StandardWeightController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StandardWeight::class);
        $query = StandardWeight::where('tenant_id', $this->tenantId());

        if ($search = $request->input('search')) {
            $safe = SearchSanitizer::contains($search);
            $query->where(function ($q) use ($safe) {
                $q->where('code', 'like', $safe)
                    ->orWhere('serial_number', 'like', $safe)
                    ->orWhere('certificate_number', 'like', $safe)
                    ->orWhere('manufacturer', 'like', $safe);
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($request->input('expiring')) {
            $query->expiring((int) $request->input('expiring_days', 30));
        }

        if ($request->boolean('expired')) {
            $query->expired();
        }

        if ($precisionClass = $request->input('precision_class')) {
            $query->where('precision_class', $precisionClass);
        }

        $sortBy = $request->input('sort_by', 'code');
        $sortDir = $request->input('sort_dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['code', 'nominal_value', 'precision_class', 'serial_number', 'calibration_due_at', 'created_at'];
        $query->orderBy(in_array($sortBy, $allowedSorts, true) ? $sortBy : 'code', $sortDir);

        $perPage = min((int) $request->input('per_page', 25), 100);
        $weights = $query->paginate($perPage);

        return ApiResponse::paginated($weights, resourceClass: StandardWeightResource::class);
    }

    public function show(Request $request, StandardWeight $standardWeight): JsonResponse
    {
        $this->authorize('view', $standardWeight);
        if ((int) $standardWeight->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Nao autorizado', 403);
        }

        $standardWeight->load('calibrations.equipment');

        return ApiResponse::data(new StandardWeightResource($standardWeight));
    }

    public function store(StoreStandardWeightRequest $request): JsonResponse
    {
        $this->authorize('create', StandardWeight::class);
        $data = $request->validated();

        try {
            DB::beginTransaction();

            $tenantId = $this->tenantId();
            $data['tenant_id'] = $tenantId;
            $data['code'] = StandardWeight::generateCode($tenantId);

            $weight = StandardWeight::create($data);

            DB::commit();

            return ApiResponse::data(new StandardWeightResource($weight), 201, ['message' => 'Peso padrao cadastrado com sucesso']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao criar peso padrão', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro interno ao criar peso padrão', 500);
        }
    }

    public function update(UpdateStandardWeightRequest $request, StandardWeight $standardWeight): JsonResponse
    {
        $this->authorize('update', $standardWeight);
        if ((int) $standardWeight->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Não autorizado.', 403);
        }

        $data = $request->validated();

        try {
            DB::beginTransaction();
            $standardWeight->update($data);
            DB::commit();

            return ApiResponse::data(new StandardWeightResource($standardWeight->fresh()), 200, ['message' => 'Peso padrao atualizado com sucesso']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao atualizar peso padrão', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro interno ao atualizar peso padrão', 500);
        }
    }

    public function destroy(Request $request, StandardWeight $standardWeight): JsonResponse
    {
        $this->authorize('delete', $standardWeight);
        if ((int) $standardWeight->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Não autorizado', 403);
        }

        $calibrationCount = $standardWeight->calibrations()->count();
        if ($calibrationCount > 0) {
            return ApiResponse::message(
                "Não é possível excluir. Este peso padrão está vinculado a {$calibrationCount} calibração(ões).",
                409
            );
        }

        try {
            DB::transaction(fn () => $standardWeight->delete());

            return ApiResponse::message('Peso padrao excluido com sucesso');
        } catch (\Exception $e) {
            Log::error('Erro ao excluir peso padrao', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir peso padrão', 500);
        }
    }

    public function expiring(Request $request): JsonResponse
    {
        $days = (int) $request->input('days', 30);

        $expiring = StandardWeight::where('tenant_id', $this->tenantId())
            ->expiring($days)
            ->orderBy('certificate_expiry')
            ->get();

        $expired = StandardWeight::where('tenant_id', $this->tenantId())
            ->expired()
            ->orderBy('certificate_expiry')
            ->get();

        return ApiResponse::data([
            'expiring' => $expiring,
            'expired' => $expired,
            'expiring_count' => $expiring->count(),
            'expired_count' => $expired->count(),
        ]);
    }

    public function constants(): JsonResponse
    {
        return ApiResponse::data([
            'statuses' => StandardWeight::STATUSES,
            'precision_classes' => StandardWeight::PRECISION_CLASSES,
            'units' => StandardWeight::UNITS,
            'shapes' => StandardWeight::SHAPES,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $weights = StandardWeight::where('tenant_id', $this->tenantId())
            ->orderBy('code')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="pesos_padrao.csv"',
        ];

        return response()->stream(function () use ($weights) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            fputcsv($handle, [
                'Código', 'Valor Nominal', 'Unidade', 'Nº Série', 'Fabricante',
                'Classe', 'Material', 'Formato', 'Nº Certificado',
                'Data Certificado', 'Validade', 'Laboratório', 'Status',
            ], ';');

            foreach ($weights as $w) {
                fputcsv($handle, [
                    $w->code,
                    number_format((float) $w->nominal_value, 4, ',', '.'),
                    $w->unit,
                    $w->serial_number ?? '',
                    $w->manufacturer ?? '',
                    $w->precision_class ?? '',
                    $w->material ?? '',
                    $w->shape ? (StandardWeight::SHAPES[$w->shape] ?? $w->shape) : '',
                    $w->certificate_number ?? '',
                    $w->certificate_date?->format('d/m/Y') ?? '',
                    $w->certificate_expiry?->format('d/m/Y') ?? '',
                    $w->laboratory ?? '',
                    StandardWeight::STATUSES[$w->status]['label'] ?? $w->status,
                ], ';');
            }

            fclose($handle);
        }, 200, $headers);
    }
}
