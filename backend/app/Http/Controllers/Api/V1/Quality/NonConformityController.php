<?php

namespace App\Http\Controllers\Api\V1\Quality;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\StoreNonConformityRequest;
use App\Http\Requests\Quality\UpdateNonConformityRequest;
use App\Models\NonConformity;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NonConformityController extends Controller
{
    private function tenantId(): int
    {
        $user = auth()->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    public function index(Request $request): JsonResponse
    {
        $query = NonConformity::with(['reporter:id,name', 'assignee:id,name'])
            ->where('tenant_id', $this->tenantId());

        if ($search = $request->get('search')) {
            $safe = SearchSanitizer::contains($search);
            $query->where(function ($q) use ($safe) {
                $q->where('title', 'like', $safe)
                    ->orWhere('nc_number', 'like', $safe)
                    ->orWhere('description', 'like', $safe);
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($source = $request->get('source')) {
            $query->where('source', $source);
        }

        if ($severity = $request->get('severity')) {
            $query->where('severity', $severity);
        }

        return ApiResponse::paginated(
            $query->orderByDesc('created_at')->paginate(min((int) $request->get('per_page', 20), 100))
        );
    }

    public function store(StoreNonConformityRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $nc = DB::transaction(function () use ($validated) {
                $ncNumber = 'NC-'.str_pad(
                    (string) ((int) NonConformity::where('tenant_id', $this->tenantId())->withTrashed()->lockForUpdate()->max('id') + 1),
                    5, '0', STR_PAD_LEFT
                );

                return NonConformity::create([
                    'tenant_id' => $this->tenantId(),
                    'nc_number' => $ncNumber,
                    'title' => $validated['title'],
                    'description' => $validated['description'],
                    'source' => $validated['source'],
                    'severity' => $validated['severity'],
                    'status' => 'open',
                    'reported_by' => auth()->id(),
                    'assigned_to' => $validated['assigned_to'] ?? null,
                    'due_date' => $validated['due_date'] ?? null,
                    'quality_audit_id' => $validated['quality_audit_id'] ?? null,
                ]);
            });

            return ApiResponse::data($nc->load(['reporter:id,name', 'assignee:id,name']), 201);
        } catch (\Throwable $e) {
            Log::error('NonConformity store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar não conformidade.', 500);
        }
    }

    public function show(NonConformity $nonConformity): JsonResponse
    {
        if ((int) $nonConformity->tenant_id !== $this->tenantId()) {
            abort(403);
        }

        return ApiResponse::data(
            $nonConformity->load(['reporter:id,name', 'assignee:id,name', 'capaRecord', 'qualityAudit:id,title,audit_number'])
        );
    }

    public function update(UpdateNonConformityRequest $request, NonConformity $nonConformity): JsonResponse
    {
        if ((int) $nonConformity->tenant_id !== $this->tenantId()) {
            abort(403);
        }

        $validated = $request->validated();

        try {
            // Auto-set closed_at when status transitions to closed
            if (($validated['status'] ?? null) === 'closed' && $nonConformity->status !== 'closed') {
                $validated['closed_at'] = now();
            }

            DB::transaction(fn () => $nonConformity->update($validated));

            return ApiResponse::data(
                $nonConformity->fresh()->load(['reporter:id,name', 'assignee:id,name'])
            );
        } catch (\Throwable $e) {
            Log::error('NonConformity update failed', ['id' => $nonConformity->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar não conformidade.', 500);
        }
    }

    public function destroy(NonConformity $nonConformity): JsonResponse
    {
        if ((int) $nonConformity->tenant_id !== $this->tenantId()) {
            abort(403);
        }

        try {
            DB::transaction(fn () => $nonConformity->delete());

            return ApiResponse::noContent();
        } catch (\Throwable $e) {
            Log::error('NonConformity destroy failed', ['id' => $nonConformity->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir não conformidade.', 500);
        }
    }
}
