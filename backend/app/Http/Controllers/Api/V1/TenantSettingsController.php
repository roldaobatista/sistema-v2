<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpsertTenantSettingsRequest;
use App\Models\AuditLog;
use App\Models\TenantSetting;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;

class TenantSettingsController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(): JsonResponse
    {
        if (! app()->bound('current_tenant_id')) {
            return ApiResponse::message('Nenhuma empresa selecionada.', 403);
        }

        // BelongsToTenant scope filtra automaticamente pelo tenant atual
        $settings = TenantSetting::where('tenant_id', $this->tenantId())->orderBy('key')->paginate(min((int) request()->input('per_page', 25), 100));
        $mapped = $settings->mapWithKeys(fn ($s) => [$s->key => $s->value_json]);

        // Return flat key-value pairs at root level for direct access
        return response()->json(array_merge(
            ['data' => $mapped],
            $mapped->toArray()
        ));
    }

    public function show(string $key): JsonResponse
    {
        if (! app()->bound('current_tenant_id')) {
            return ApiResponse::message('Nenhuma empresa selecionada.', 403);
        }

        $tenantId = $this->tenantId();
        $value = TenantSetting::getValue($tenantId, $key);

        // Return key and value at root level
        return response()->json([
            'data' => compact('key', 'value'),
            'key' => $key,
            'value' => $value,
        ]);
    }

    public function upsert(UpsertTenantSettingsRequest $request): JsonResponse
    {
        if (! app()->bound('current_tenant_id')) {
            return ApiResponse::message('Nenhuma empresa selecionada.', 403);
        }

        $validated = $request->validated();
        $tenantId = $this->tenantId();

        foreach ($validated['settings'] as $item) {
            TenantSetting::setValue($tenantId, $item['key'], $item['value']);
        }

        AuditLog::log('updated', 'Configurações da empresa atualizadas');

        $all = TenantSetting::where('tenant_id', $tenantId)->orderBy('key')->get()
            ->mapWithKeys(fn ($s) => [$s->key => $s->value_json]);

        // Return flat key-value pairs at root level for direct access
        return response()->json(array_merge(
            ['data' => $all],
            $all->toArray()
        ));
    }

    public function destroy(string $key): JsonResponse
    {
        if (! app()->bound('current_tenant_id')) {
            return ApiResponse::message('Nenhuma empresa selecionada.', 403);
        }

        $tenantId = $this->tenantId();

        $deleted = TenantSetting::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('key', $key)
            ->delete();

        if (! $deleted) {
            return ApiResponse::message('Configuração não encontrada.', 404);
        }

        AuditLog::log('deleted', "Configuração '{$key}' removida");

        return ApiResponse::noContent();
    }
}
