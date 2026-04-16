<?php

namespace App\Http\Controllers\Api\V1\Lgpd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lgpd\StoreLgpdSecurityIncidentRequest;
use App\Http\Requests\Lgpd\UpdateLgpdSecurityIncidentRequest;
use App\Http\Resources\LgpdSecurityIncidentResource;
use App\Models\LgpdSecurityIncident;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LgpdSecurityIncidentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $incidents = LgpdSecurityIncident::query()
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('severity'), fn ($q, $v) => $q->where('severity', $v))
            ->orderByDesc('detected_at')
            ->paginate(max(1, min($request->integer('per_page', 15), 100)));

        return ApiResponse::paginated($incidents, resourceClass: LgpdSecurityIncidentResource::class);
    }

    public function store(StoreLgpdSecurityIncidentRequest $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $incident = LgpdSecurityIncident::create([
            ...$request->validated(),
            'tenant_id' => $tenantId,
            'protocol' => LgpdSecurityIncident::generateProtocol($tenantId),
            'status' => LgpdSecurityIncident::STATUS_OPEN,
            'reported_by' => $request->user()->id,
        ]);

        return ApiResponse::data(new LgpdSecurityIncidentResource($incident), 201);
    }

    public function show(int $id): JsonResponse
    {
        $incident = LgpdSecurityIncident::with('reporter')->findOrFail($id);

        return ApiResponse::data(new LgpdSecurityIncidentResource($incident));
    }

    public function update(UpdateLgpdSecurityIncidentRequest $request, int $id): JsonResponse
    {
        $incident = LgpdSecurityIncident::findOrFail($id);

        $validated = $request->validated();

        if (isset($validated['holders_notified']) && $validated['holders_notified'] && ! $incident->holders_notified) {
            $validated['holders_notified_at'] = now();
        }

        $incident->update($validated);

        return ApiResponse::data(new LgpdSecurityIncidentResource($incident));
    }

    public function generateAnpdReport(int $id): JsonResponse
    {
        $incident = LgpdSecurityIncident::findOrFail($id);

        $report = [
            'protocolo' => $incident->protocol,
            'data_deteccao' => $incident->detected_at->format('d/m/Y H:i'),
            'severidade' => $incident->severity,
            'descricao' => $incident->description,
            'dados_afetados' => $incident->affected_data,
            'titulares_afetados' => $incident->affected_holders_count,
            'medidas_adotadas' => $incident->measures_taken,
            'titulares_notificados' => $incident->holders_notified ? 'Sim' : 'Não',
            'data_notificacao_titulares' => $incident->holders_notified_at?->format('d/m/Y H:i'),
            'status' => $incident->status,
            'gerado_em' => now()->format('d/m/Y H:i'),
        ];

        $incident->update(['anpd_reported_at' => now()]);

        return ApiResponse::data(['report' => $report]);
    }
}
