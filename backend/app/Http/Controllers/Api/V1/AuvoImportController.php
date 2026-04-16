<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auvo\AuvoConfigRequest;
use App\Models\AuvoIdMapping;
use App\Models\AuvoImport;
use App\Models\TenantSetting;
use App\Services\Auvo\AuvoApiClient;
use App\Services\Auvo\AuvoImportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuvoImportController extends Controller
{
    private function client(Request $request): AuvoApiClient
    {
        return AuvoApiClient::forTenant($request->user()->current_tenant_id);
    }

    /**
     * Test connection to Auvo API and return entity counts.
     */
    public function testConnection(Request $request): JsonResponse
    {
        $client = $this->client($request);

        $connectionResult = $client->testConnection();

        if ($connectionResult['connected']) {
            try {
                $counts = $client->getEntityCounts();
                $connectionResult['available_entities'] = $counts;
            } catch (\Exception $e) {
                $connectionResult['available_entities'] = [];
                $connectionResult['counts_error'] = $e->getMessage();
            }
        }

        return ApiResponse::data($connectionResult);
    }

    /**
     * Preview data from Auvo before importing.
     */
    public function preview(Request $request, string $entity): JsonResponse
    {
        if (! isset(AuvoImport::ENTITY_TYPES[$entity])) {
            return ApiResponse::message("Tipo de entidade inválido: {$entity}.", 422);
        }

        try {
            $client = $this->client($request);

            if (! $client->hasCredentials()) {
                return ApiResponse::message('Credenciais Auvo não configuradas. Configure em Credenciais.', 422);
            }

            $service = new AuvoImportService($client);
            $limit = min((int) $request->get('limit', 10), 50);
            $result = $service->preview($entity, $limit);

            return ApiResponse::data([
                'entity' => $entity,
                'entity_label' => AuvoImport::ENTITY_TYPES[$entity],
                'total' => $result['total'],
                'sample' => $result['sample'],
                'mapped_fields' => $result['mapped_fields'],
            ]);
        } catch (\Exception $e) {
            Log::error('Auvo: preview failed', ['entity' => $entity, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao buscar dados.', 500);
        }
    }

    /**
     * Import a specific entity type.
     */
    public function import(Request $request, string $entity): JsonResponse
    {
        if (! isset(AuvoImport::ENTITY_TYPES[$entity])) {
            return ApiResponse::message("Tipo de entidade inválido: {$entity}.", 422);
        }

        $strategy = $request->get('strategy', 'skip');
        if (! in_array($strategy, ['skip', 'update'])) {
            return ApiResponse::message('Estratégia inválida. Use "skip" ou "update".', 422);
        }

        try {
            $client = $this->client($request);

            if (! $client->hasCredentials()) {
                return ApiResponse::message('Credenciais Auvo não configuradas. Configure em Credenciais.', 422);
            }

            $tenantId = $request->user()->current_tenant_id;
            $userId = $request->user()->id;

            // Block quotation import when no customer mappings exist
            if ($entity === 'quotations') {
                $customerMappings = AuvoIdMapping::where('tenant_id', $tenantId)
                    ->where('entity_type', 'customers')
                    ->count();

                if ($customerMappings === 0) {
                    return ApiResponse::message('Importe clientes antes de importar orçamentos. Orçamentos dependem de clientes já mapeados (Auvo → Kalibrium) para vincular o campo cliente.', 422, [
                        'entity_label' => AuvoImport::ENTITY_TYPES[$entity],
                        'requires_customers' => true,
                    ]);
                }
            }

            $service = new AuvoImportService($client);
            $result = $service->importEntity($entity, $tenantId, $userId, $strategy);

            $totalFetched = $result['total_fetched'] ?? 0;
            $totalImported = $result['total_imported'] ?? 0;
            $totalUpdated = $result['total_updated'] ?? 0;
            $totalSkipped = $result['total_skipped'] ?? 0;
            $totalErrors = $result['total_errors'] ?? 0;
            $skippedNoCustomer = $result['skipped_no_customer'] ?? 0;

            if ($totalFetched === 0) {
                $message = 'Nenhum registro encontrado no Auvo para esta entidade. Verifique se há dados no painel Auvo ou se a API retornou outro formato.';
            } elseif ($entity === 'quotations' && $totalImported === 0 && $totalUpdated === 0) {
                $message = 'Nenhum orçamento importado.';
                if ($skippedNoCustomer > 0) {
                    $message .= sprintf(' %d orçamento(s) ignorado(s) porque o cliente não está mapeado no Kalibrium. Importe clientes antes.', $skippedNoCustomer);
                } else {
                    $message .= ' Importe clientes antes para que o cliente do orçamento seja encontrado no Kalibrium.';
                }
            } else {
                $message = 'Importação concluída';
                if ($skippedNoCustomer > 0) {
                    $message .= sprintf('. %d orçamento(s) ignorado(s) por cliente não mapeado', $skippedNoCustomer);
                }
                if ($totalErrors > 0) {
                    $firstError = $result['first_error'] ?? null;
                    $message .= sprintf('. %d erro(s).', $totalErrors);
                    if ($firstError) {
                        $message .= ' Ex.: '.(is_string($firstError) ? $firstError : (is_array($firstError) ? ($firstError['message'] ?? json_encode($firstError)) : ''));
                    }
                }
            }

            return ApiResponse::data(array_merge([
                'entity_label' => AuvoImport::ENTITY_TYPES[$entity],
            ], $result), 200, ['message' => $message]);
        } catch (\Exception $e) {
            Log::error('Auvo: import failed', ['entity' => $entity, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro na importação.', 500);
        }
    }

    /**
     * Import all entities in dependency order.
     */
    public function importAll(Request $request): JsonResponse
    {
        $strategy = $request->get('strategy', 'skip');

        try {
            $client = $this->client($request);

            if (! $client->hasCredentials()) {
                return ApiResponse::message('Credenciais Auvo não configuradas. Configure em Credenciais.', 422);
            }

            $service = new AuvoImportService($client);
            $tenantId = $request->user()->current_tenant_id;
            $userId = $request->user()->id;

            $results = $service->importAll($tenantId, $userId, $strategy);

            $totalInserted = 0;
            $totalErrors = 0;
            foreach ($results as $r) {
                $totalInserted += $r['total_imported'] ?? 0;
                $totalErrors += $r['total_errors'] ?? 0;
            }

            return ApiResponse::data([
                'summary' => [
                    'total_entities' => count($results),
                    'total_inserted' => $totalInserted,
                    'total_errors' => $totalErrors,
                ],
                'details' => $results,
            ], 200, ['message' => 'Importação completa finalizada']);
        } catch (\Exception $e) {
            Log::error('Auvo: full import failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro na importação.', 500);
        }
    }

    /**
     * List import history.
     */
    public function history(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $query = AuvoImport::where('tenant_id', $tenantId)
            ->with('user:id,name')
            ->orderByDesc('created_at');

        if ($request->has('entity')) {
            $query->byEntity($request->get('entity'));
        }

        if ($request->has('status')) {
            $query->byStatus($request->get('status'));
        }

        $imports = $query->paginate(min((int) $request->get('per_page', 20), 100));

        return ApiResponse::paginated($imports);
    }

    /**
     * Rollback a specific import.
     */
    public function rollback(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $import = AuvoImport::where('tenant_id', $tenantId)
            ->where('id', $id)
            ->firstOrFail();

        if ($import->status === AuvoImport::STATUS_ROLLED_BACK) {
            return ApiResponse::message('Esta importação já foi desfeita.', 422);
        }

        if ($import->status !== AuvoImport::STATUS_DONE) {
            return ApiResponse::message('Só é possível desfazer importações concluídas.', 422);
        }

        try {
            $client = $this->client($request);
            $service = new AuvoImportService($client);
            $result = $service->rollback($import);

            return ApiResponse::data(['result' => $result], 200, ['message' => 'Importação desfeita com sucesso']);
        } catch (\Exception $e) {
            Log::error('Auvo: rollback failed', ['import_id' => $id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao desfazer.', 500);
        }
    }

    /**
     * List ID mappings (Auvo ↔ Kalibrium).
     */
    public function mappings(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $query = AuvoIdMapping::where('tenant_id', $tenantId);

        if ($request->has('entity')) {
            $query->where('entity_type', $request->get('entity'));
        }

        $mappings = $query->orderByDesc('created_at')
            ->paginate(min((int) $request->get('per_page', 50), 100));

        return ApiResponse::paginated($mappings);
    }

    /**
     * Save Auvo API credentials (persisted in tenant_settings DB table).
     */
    public function config(AuvoConfigRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $tenantId = $request->user()->current_tenant_id;

        // Save to database (persistent, per-tenant)
        TenantSetting::setValue($tenantId, 'auvo_credentials', [
            'api_key' => $validated['api_key'],
            'api_token' => $validated['api_token'],
        ]);

        // Test connection with the provided credentials
        $client = new AuvoApiClient($validated['api_key'], $validated['api_token'], $tenantId);
        $result = $client->testConnection();

        return ApiResponse::data([
            'saved' => true,
            'connected' => $result['connected'],
        ], 200, [
            'message' => $result['connected']
                ? 'Credenciais salvas e conexão verificada com sucesso!'
                : 'Credenciais salvas, mas a conexão falhou: '.$result['message'],
        ]);
    }

    /**
     * Get current Auvo API credentials (masked for security).
     */
    public function getConfig(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $credentials = TenantSetting::getValue($tenantId, 'auvo_credentials');

        $apiKey = $credentials['api_key'] ?? config('services.auvo.api_key', '');
        $apiToken = $credentials['api_token'] ?? config('services.auvo.api_token', '');

        $mask = fn (string $v): string => strlen($v) <= 6
            ? str_repeat('*', strlen($v))
            : substr($v, 0, 3).str_repeat('*', strlen($v) - 6).substr($v, -3);

        return ApiResponse::data([
            'has_credentials' => ! empty($apiKey) && ! empty($apiToken),
            'api_key_masked' => $apiKey ? $mask($apiKey) : '',
            'api_token_masked' => $apiToken ? $mask($apiToken) : '',
        ]);
    }

    /**
     * Get last sync status per entity.
     */
    public function syncStatus(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $entityTypes = array_keys(AuvoImport::ENTITY_TYPES);

        // Batch: last successful import per entity (1 query)
        $lastImports = AuvoImport::where('tenant_id', $tenantId)
            ->where('status', AuvoImport::STATUS_DONE)
            ->whereIn('entity_type', $entityTypes)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('entity_type')
            ->map(fn ($group) => $group->first());

        // Batch: mapping counts per entity (1 query)
        $mappingCounts = AuvoIdMapping::where('tenant_id', $tenantId)
            ->selectRaw('entity_type, COUNT(*) as cnt')
            ->groupBy('entity_type')
            ->pluck('cnt', 'entity_type');

        $statuses = [];
        foreach (AuvoImport::ENTITY_TYPES as $entity => $label) {
            $lastImport = $lastImports->get($entity);
            $statuses[$entity] = [
                'label' => $label,
                'last_import_at' => $lastImport?->completed_at ?? $lastImport?->last_synced_at,
                'total_imported' => $lastImport?->total_imported ?? 0,
                'total_updated' => $lastImport?->total_updated ?? 0,
                'total_errors' => $lastImport?->total_errors ?? 0,
                'total_mapped' => $mappingCounts->get($entity, 0),
                'status' => $lastImport?->status ?? 'never',
            ];
        }

        return ApiResponse::data([
            'entities' => $statuses,
            'total_mappings' => $mappingCounts->sum(),
        ]);
    }

    /**
     * Delete a specific import history record.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $import = AuvoImport::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $import) {
            return ApiResponse::message('Registro de importação não encontrado.', 404);
        }

        try {
            DB::beginTransaction();
            AuvoIdMapping::where('import_id', $import->id)->delete();
            $import->delete();
            DB::commit();

            return ApiResponse::message('Registro de importação removido com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Auvo: delete import failed', ['id' => $id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao remover registro.', 500);
        }
    }
}
