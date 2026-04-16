<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Iam\ExportAuditLogRequest;
use App\Http\Requests\Iam\ListAuditLogRequest;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    private function baseQuery()
    {
        $tenantId = app()->bound('current_tenant_id') ? (int) app('current_tenant_id') : null;

        return AuditLog::with('user:id,name')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where(function ($query) {
                $query->whereNull('auditable_type')
                    ->orWhereNotIn('auditable_type', [Tenant::class, User::class]);
            })
            ->orderByDesc('created_at');
    }

    public function index(ListAuditLogRequest $request): JsonResponse
    {
        try {
            $query = $this->baseQuery();

            if ($request->filled('action')) {
                $query->where('action', $request->input('action'));
            }
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }
            if ($request->filled('auditable_type')) {
                $safeType = SearchSanitizer::contains($request->input('auditable_type'));
                $query->where('auditable_type', 'like', $safeType);
            }
            if ($request->filled('from')) {
                $query->where('created_at', '>=', $request->input('from'));
            }
            if ($request->filled('to')) {
                $query->where('created_at', '<=', $request->input('to').' 23:59:59');
            }
            if ($request->filled('search')) {
                $safe = SearchSanitizer::contains($request->input('search'));
                $query->where('description', 'like', $safe);
            }

            $perPage = min((int) $request->input('per_page', 30), 100);

            return ApiResponse::paginated($query->paginate($perPage));
        } catch (\Exception $e) {
            Log::error('AuditLog index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar logs de auditoria', 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $tenantId = app()->bound('current_tenant_id') ? (int) app('current_tenant_id') : null;

            $query = AuditLog::with('user:id,name');
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }

            $log = $query->findOrFail($id);

            $diff = [];
            $old = $log->old_values ?? [];
            $new = $log->new_values ?? [];
            $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));

            foreach ($allKeys as $key) {
                $oldVal = $old[$key] ?? null;
                $newVal = $new[$key] ?? null;
                if ($oldVal !== $newVal) {
                    $diff[] = [
                        'field' => $key,
                        'old' => $oldVal,
                        'new' => $newVal,
                    ];
                }
            }

            $logData = $log->toArray();
            $logData['diff'] = $diff;

            return ApiResponse::data($logData);
        } catch (ModelNotFoundException $e) {
            return ApiResponse::message('Log não encontrado', 404);
        } catch (\Exception $e) {
            Log::error('AuditLog show failed', ['error' => $e->getMessage(), 'id' => $id]);

            return ApiResponse::message('Erro ao buscar log', 500);
        }
    }

    public function actions(): JsonResponse
    {
        try {
            $tenantId = app()->bound('current_tenant_id') ? (int) app('current_tenant_id') : null;

            $query = AuditLog::query()
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->where(function ($query) {
                    $query->whereNull('auditable_type')
                        ->orWhereNotIn('auditable_type', [Tenant::class, User::class]);
                });

            $actions = $query->select('action')->distinct()->pluck('action');

            return ApiResponse::data($actions);
        } catch (\Throwable $e) {
            Log::error('AuditLog actions failed', ['error' => $e->getMessage()]);

            return ApiResponse::data([]);
        }
    }

    public function entityTypes(): JsonResponse
    {
        try {
            $tenantId = app()->bound('current_tenant_id') ? (int) app('current_tenant_id') : null;

            $query = AuditLog::query()
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->where(function ($query) {
                    $query->whereNull('auditable_type')
                        ->orWhereNotIn('auditable_type', [Tenant::class, User::class]);
                });

            $types = $query->select('auditable_type')->distinct()
                ->pluck('auditable_type')
                ->filter()
                ->map(fn ($t) => [
                    'value' => $t,
                    'label' => class_basename($t),
                ])
                ->values();

            return ApiResponse::data($types);
        } catch (\Throwable $e) {
            Log::error('AuditLog entityTypes failed', ['error' => $e->getMessage()]);

            return ApiResponse::data([]);
        }
    }

    public function export(ExportAuditLogRequest $request): StreamedResponse
    {
        try {
            $query = $this->baseQuery();

            if ($request->filled('action')) {
                $query->where('action', $request->input('action'));
            }
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }
            if ($request->filled('auditable_type')) {
                $safeType = SearchSanitizer::contains($request->input('auditable_type'));
                $query->where('auditable_type', 'like', $safeType);
            }
            if ($request->filled('from')) {
                $query->where('created_at', '>=', $request->input('from'));
            }
            if ($request->filled('to')) {
                $query->where('created_at', '<=', $request->input('to').' 23:59:59');
            }

            $filename = 'audit_log_'.now()->format('Y-m-d_His').'.csv';

            return response()->streamDownload(function () use ($query) {
                $handle = fopen('php://output', 'w');
                fwrite($handle, "\xEF\xBB\xBF");
                fputcsv($handle, ['Data', 'Usuário', 'Ação', 'Entidade', 'ID', 'Descrição', 'IP'], ';');

                $query->chunk(500, function ($logs) use ($handle) {
                    foreach ($logs as $log) {
                        fputcsv($handle, [
                            $log->created_at?->format('d/m/Y H:i:s'),
                            $log->user?->name ?? 'Sistema',
                            $log->action instanceof AuditAction ? $log->action->label() : $log->action,
                            $log->auditable_type ? class_basename($log->auditable_type) : '-',
                            $log->auditable_id ?? '-',
                            $log->description,
                            $log->ip_address,
                        ], ';');
                    }
                });

                fclose($handle);
            }, $filename, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        } catch (\Exception $e) {
            Log::error('AuditLog export failed', ['error' => $e->getMessage()]);

            return response()->streamDownload(function () {
                echo 'Erro na exportação. Tente novamente ou contate o suporte.';
            }, 'error.txt', ['Content-Type' => 'text/plain']);
        }
    }
}
