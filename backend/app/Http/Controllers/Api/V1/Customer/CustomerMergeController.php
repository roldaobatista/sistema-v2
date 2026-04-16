<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\MergeCustomersRequest;
use App\Http\Requests\Customer\SearchDuplicateCustomersRequest;
use App\Models\ClientPortalUser;
use App\Models\CrmActivity;
use App\Models\Customer;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerMergeController extends Controller
{
    use ResolvesCurrentTenant;

    public function merge(MergeCustomersRequest $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $primaryId = (int) $request->validated('primary_id');
        $duplicateIds = collect($request->validated('duplicate_ids'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        try {
            $primary = Customer::query()
                ->where('tenant_id', $tenantId)
                ->findOrFail($primaryId);
            $this->authorize('update', $primary);

            $duplicates = Customer::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $duplicateIds)
                ->get();

            if ($duplicates->count() !== $duplicateIds->count()) {
                return ApiResponse::message('Um ou mais clientes duplicados não foram encontrados para o tenant atual.', 422);
            }

            $result = DB::transaction(function () use ($request, $tenantId, $primary, $primaryId, $duplicates) {
                $importedNotes = [];
                $mergedCustomers = [];

                foreach ($duplicates as $duplicate) {
                    $this->authorize('update', $duplicate);

                    $duplicate->contacts()->update(['customer_id' => $primaryId]);
                    $duplicate->deals()->update(['customer_id' => $primaryId]);
                    $duplicate->activities()->update(['customer_id' => $primaryId]);
                    $duplicate->equipments()->update(['customer_id' => $primaryId]);
                    $duplicate->workOrders()->update(['customer_id' => $primaryId]);
                    $duplicate->quotes()->update(['customer_id' => $primaryId]);
                    $duplicate->serviceCalls()->update(['customer_id' => $primaryId]);
                    $duplicate->accountsReceivable()->update(['customer_id' => $primaryId]);
                    $duplicate->documents()->update(['customer_id' => $primaryId]);
                    $duplicate->complaints()->update(['customer_id' => $primaryId]);
                    $duplicate->rfmScores()->update(['customer_id' => $primaryId]);
                    $duplicate->locations()->update(['customer_id' => $primaryId]);
                    $duplicate->recurringContracts()->update(['customer_id' => $primaryId]);
                    $duplicate->emails()->update(['customer_id' => $primaryId]);

                    try {
                        ClientPortalUser::where('customer_id', $duplicate->id)
                            ->update(['customer_id' => $primaryId]);
                    } catch (\Throwable $e) {
                        Log::warning('Falha ao remapear portal do cliente durante fusao', [
                            'tenant_id' => $tenantId,
                            'primary_customer_id' => $primaryId,
                            'duplicate_customer_id' => $duplicate->id,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    $mergedCustomers[] = [
                        'id' => $duplicate->id,
                        'name' => $duplicate->name,
                    ];

                    if (filled($duplicate->notes)) {
                        $importedNotes[] = sprintf(
                            '[Fusao em %s] Notas importadas de #%d (%s):%s%s',
                            now()->format('d/m/Y'),
                            $duplicate->id,
                            $duplicate->name,
                            PHP_EOL,
                            trim((string) $duplicate->notes)
                        );
                    }

                    $duplicate->notes = trim(implode(PHP_EOL.PHP_EOL, array_filter([
                        filled($duplicate->notes) ? trim((string) $duplicate->notes) : null,
                        "[Fusao] Mesclado com cliente #{$primaryId} em ".now()->format('d/m/Y H:i'),
                    ])));
                    $duplicate->save();
                    $duplicate->delete();
                }

                if ($importedNotes !== []) {
                    $primary->update([
                        'notes' => trim(implode(PHP_EOL.PHP_EOL, array_filter([
                            filled($primary->notes) ? trim((string) $primary->notes) : null,
                            implode(PHP_EOL.PHP_EOL, $importedNotes),
                        ]))),
                    ]);
                }

                CrmActivity::logSystemEvent(
                    $tenantId,
                    $primaryId,
                    'Fusao de clientes concluida',
                    null,
                    $request->user()->id,
                    [
                        'primary_customer_id' => $primaryId,
                        'merged_customer_ids' => collect($mergedCustomers)->pluck('id')->values()->all(),
                        'merged_customers' => $mergedCustomers,
                    ]
                );

                $primary->recalculateHealthScore();

                return [
                    'count' => $duplicates->count(),
                    'primary' => $primary->fresh(),
                ];
            });

            return ApiResponse::data(
                ['primary' => $result['primary']],
                200,
                ['message' => $result['count'].' cliente(s) mesclado(s) com sucesso no cliente #'.$primaryId.'.']
            );
        } catch (\Throwable $e) {
            Log::error('Falha ao mesclar clientes', [
                'tenant_id' => $tenantId,
                'primary_id' => $request->input('primary_id'),
                'duplicate_ids' => $request->input('duplicate_ids', []),
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao mesclar clientes. Verifique os dados e tente novamente.', 500);
        }
    }

    public function searchDuplicates(SearchDuplicateCustomersRequest $request): JsonResponse
    {
        $type = $request->validated('type') ?? 'name';

        $driver = DB::connection()->getDriverName();
        $concatExpr = $driver === 'pgsql'
            ? "STRING_AGG(CAST(id AS VARCHAR), ',')"
            : 'GROUP_CONCAT(id)';

        $duplicates = [];

        if ($type === 'document') {
            $normalizedDoc = "REPLACE(REPLACE(REPLACE(REPLACE(document, '.', ''), '/', ''), '-', ''), ' ', '')";
            $duplicates = Customer::select(
                DB::raw("{$normalizedDoc} as document_normalized"),
                DB::raw('count(*) as count'),
                DB::raw("{$concatExpr} as ids")
            )
                ->where('tenant_id', $this->tenantId())
                ->whereNotNull('document')
                ->where('document', '!=', '')
                ->groupBy(DB::raw($normalizedDoc))
                ->having('count', '>', 1)
                ->limit(20)
                ->get();
        } elseif ($type === 'email') {
            $duplicates = Customer::select(
                DB::raw('LOWER(TRIM(email)) as email_normalized'),
                DB::raw('count(*) as count'),
                DB::raw("{$concatExpr} as ids")
            )
                ->where('tenant_id', $this->tenantId())
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->groupBy(DB::raw('LOWER(TRIM(email))'))
                ->having('count', '>', 1)
                ->limit(20)
                ->get();
        } else {
            $duplicates = Customer::select(
                DB::raw('LOWER(TRIM(name)) as name_normalized'),
                DB::raw('count(*) as count'),
                DB::raw("{$concatExpr} as ids")
            )
                ->where('tenant_id', $this->tenantId())
                ->groupBy(DB::raw('LOWER(TRIM(name))'))
                ->having('count', '>', 1)
                ->limit(20)
                ->get();
        }

        $results = [];
        foreach ($duplicates as $dup) {
            $ids = explode(',', $dup->ids);
            $customers = Customer::query()
                ->where('tenant_id', $this->tenantId())
                ->whereIn('id', $ids)
                ->get(['id', 'name', 'document', 'email', 'created_at']);

            $key = match ($type) {
                'document' => $dup->document_normalized,
                'email' => $dup->email_normalized,
                default => $dup->name_normalized,
            };

            $results[] = [
                'key' => $key ?: $customers->first()?->name,
                'count' => $dup->count,
                'customers' => $customers,
            ];
        }

        return ApiResponse::data($results);
    }
}
