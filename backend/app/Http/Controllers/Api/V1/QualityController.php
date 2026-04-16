<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\StoreCorrectiveActionRequest;
use App\Http\Requests\Quality\StoreCustomerComplaintRequest;
use App\Http\Requests\Quality\StoreQualityProcedureRequest;
use App\Http\Requests\Quality\StoreSatisfactionSurveyRequest;
use App\Http\Requests\Quality\UpdateCorrectiveActionRequest;
use App\Http\Requests\Quality\UpdateCustomerComplaintRequest;
use App\Http\Requests\Quality\UpdateQualityProcedureRequest;
use App\Models\CorrectiveAction;
use App\Models\CustomerComplaint;
use App\Models\QualityProcedure;
use App\Models\SatisfactionSurvey;
use App\Support\ApiResponse;
use App\Support\QualityActionMetrics;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QualityController extends Controller
{
    use ResolvesCurrentTenant;
    // ─── PROCEDURES ──────────────────────────────────────────────

    public function indexProcedures(Request $request): JsonResponse
    {
        $query = QualityProcedure::where('tenant_id', $this->resolvedTenantId());

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('search')) {
            $search = SearchSanitizer::contains($request->search);
            $query->where(fn ($q) => $q->where('title', 'like', $search)
                ->orWhere('code', 'like', $search));
        }

        return ApiResponse::paginated($query->orderBy('code')->paginate(min((int) $request->input('per_page', 20), 100)));
    }

    public function storeProcedure(StoreQualityProcedureRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();
            $validated['tenant_id'] = $this->resolvedTenantId();
            $procedure = QualityProcedure::create($validated);
            DB::commit();

            return ApiResponse::data($procedure, 201, ['message' => 'Procedimento criado']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('QualityProcedure create failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar procedimento.', 500);
        }
    }

    public function showProcedure(QualityProcedure $procedure): JsonResponse
    {
        $procedure->load('approver:id,name');

        return ApiResponse::data($procedure);
    }

    public function updateProcedure(UpdateQualityProcedureRequest $request, QualityProcedure $procedure): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();
            if (isset($validated['content']) && $validated['content'] !== $procedure->content) {
                $validated['revision'] = $procedure->revision + 1;
            }
            $procedure->update($validated);
            DB::commit();

            return ApiResponse::data($procedure->fresh(), 200, ['message' => 'Procedimento atualizado']);
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::message('Erro ao atualizar procedimento.', 500);
        }
    }

    public function approveProcedure(Request $request, QualityProcedure $procedure): JsonResponse
    {
        try {
            DB::beginTransaction();
            $procedure->update([
                'status' => 'active',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);
            DB::commit();

            return ApiResponse::data($procedure->fresh(), 200, ['message' => 'Procedimento aprovado']);
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::message('Erro ao aprovar.', 500);
        }
    }

    public function destroyProcedure(QualityProcedure $procedure): JsonResponse
    {
        if ($procedure->status === 'active') {
            return ApiResponse::message('Procedimentos ativos não podem ser excluídos. Mude para obsoleto primeiro.', 422);
        }

        try {
            $procedure->delete();

            return ApiResponse::message('Procedimento excluído com sucesso.');
        } catch (\Exception $e) {
            Log::error('QualityProcedure delete failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir procedimento.', 500);
        }
    }

    // ─── CORRECTIVE ACTIONS ──────────────────────────────────────

    public function indexCorrectiveActions(Request $request): JsonResponse
    {
        $query = CorrectiveAction::where('tenant_id', $this->resolvedTenantId())
            ->with('responsible:id,name');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        return ApiResponse::paginated($query->orderByDesc('created_at')->paginate(min((int) $request->input('per_page', 20), 100)));
    }

    public function storeCorrectiveAction(StoreCorrectiveActionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();
            $validated['tenant_id'] = $this->resolvedTenantId();
            $action = CorrectiveAction::create($validated);
            DB::commit();

            return ApiResponse::data($action, 201, ['message' => 'Ação registrada']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CorrectiveAction create failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar ação.', 500);
        }
    }

    public function updateCorrectiveAction(UpdateCorrectiveActionRequest $request, CorrectiveAction $action): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();
            if (($validated['status'] ?? null) === 'completed') {
                $validated['completed_at'] = now();
            }
            $action->update($validated);
            DB::commit();

            return ApiResponse::data($action->fresh(), 200, ['message' => 'Ação atualizada']);
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::message('Erro ao atualizar ação.', 500);
        }
    }

    public function destroyCorrectiveAction(CorrectiveAction $action): JsonResponse
    {
        if (in_array($action->status, ['completed', 'verified'])) {
            return ApiResponse::message('Ações concluídas ou verificadas não podem ser excluídas.', 422);
        }

        try {
            $action->delete();

            return ApiResponse::message('Ação corretiva excluída com sucesso.');
        } catch (\Exception $e) {
            Log::error('CorrectiveAction delete failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir ação corretiva.', 500);
        }
    }

    // ─── CUSTOMER COMPLAINTS ─────────────────────────────────────

    public function indexComplaints(Request $request): JsonResponse
    {
        $query = CustomerComplaint::where('tenant_id', $this->resolvedTenantId())
            ->with(['customer:id,name', 'assignedTo:id,name']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        return ApiResponse::paginated($query->orderByDesc('created_at')->paginate(min((int) $request->input('per_page', 20), 100)));
    }

    public function storeComplaint(StoreCustomerComplaintRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();
            $validated['tenant_id'] = $this->resolvedTenantId();
            $complaint = CustomerComplaint::create($validated);
            DB::commit();

            return ApiResponse::data($complaint, 201, ['message' => 'Reclamação registrada']);
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::message('Erro ao registrar reclamação.', 500);
        }
    }

    public function updateComplaint(UpdateCustomerComplaintRequest $request, CustomerComplaint $complaint): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();
            if (($validated['status'] ?? null) === 'resolved') {
                $validated['resolved_at'] = now();
                if (empty($complaint->responded_at)) {
                    $validated['responded_at'] = now();
                }
            }
            $complaint->update($validated);
            DB::commit();

            return ApiResponse::data($complaint->fresh(), 200, ['message' => 'Reclamação atualizada']);
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::message('Erro ao atualizar reclamação.', 500);
        }
    }

    public function destroyComplaint(CustomerComplaint $complaint): JsonResponse
    {
        if (in_array($complaint->status, ['resolved', 'closed'])) {
            return ApiResponse::message('Reclamações resolvidas ou fechadas não podem ser excluídas.', 422);
        }

        try {
            $complaint->delete();

            return ApiResponse::message('Reclamação excluída com sucesso.');
        } catch (\Exception $e) {
            Log::error('CustomerComplaint delete failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir reclamação.', 500);
        }
    }

    // ─── SATISFACTION SURVEYS / NPS ──────────────────────────────

    public function indexSurveys(Request $request): JsonResponse
    {
        $query = SatisfactionSurvey::where('tenant_id', $this->resolvedTenantId())
            ->with(['customer:id,name', 'workOrder:id,number']);

        return ApiResponse::paginated($query->orderByDesc('created_at')->paginate(min((int) $request->input('per_page', 20), 100)));
    }

    public function storeSurvey(StoreSatisfactionSurveyRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();
            $validated['tenant_id'] = $this->resolvedTenantId();
            $survey = SatisfactionSurvey::create($validated);
            DB::commit();

            return ApiResponse::data($survey, 201, ['message' => 'Pesquisa registrada']);
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::message('Erro ao registrar pesquisa.', 500);
        }
    }

    public function npsDashboard(Request $request): JsonResponse
    {
        $tenantId = $this->resolvedTenantId();
        $surveys = SatisfactionSurvey::where('tenant_id', $tenantId)
            ->whereNotNull('nps_score');

        $total = $surveys->count();
        if ($total === 0) {
            return ApiResponse::data(['nps' => null, 'total' => 0]);
        }

        $promoters = (clone $surveys)->where('nps_score', '>=', 9)->count();
        $detractors = (clone $surveys)->where('nps_score', '<=', 6)->count();
        $nps = round((($promoters - $detractors) / $total) * 100, 1);

        $avgRatings = SatisfactionSurvey::where('tenant_id', $tenantId)
            ->selectRaw('AVG(service_rating) as avg_service, AVG(technician_rating) as avg_technician, AVG(timeliness_rating) as avg_timeliness')
            ->first();

        return ApiResponse::data([
            'nps' => $nps,
            'total' => $total,
            'promoters' => $promoters,
            'passives' => $total - $promoters - $detractors,
            'detractors' => $detractors,
            'avg_service' => round($avgRatings->avg_service ?? 0, 1),
            'avg_technician' => round($avgRatings->avg_technician ?? 0, 1),
            'avg_timeliness' => round($avgRatings->avg_timeliness ?? 0, 1),
        ]);
    }

    // ─── QUALITY DASHBOARD ───────────────────────────────────────

    public function dashboard(Request $request): JsonResponse
    {
        $tenantId = $this->resolvedTenantId();
        $actionCounts = QualityActionMetrics::dashboardCounts($tenantId);

        return ApiResponse::data([
            'active_procedures' => QualityProcedure::where('tenant_id', $tenantId)->where('status', 'active')->count(),
            'review_due' => QualityProcedure::where('tenant_id', $tenantId)->where('next_review_date', '<=', now()->addMonth())->count(),
            'open_actions' => $actionCounts['open_actions'],
            'overdue_actions' => $actionCounts['overdue_actions'],
            'open_complaints' => CustomerComplaint::where('tenant_id', $tenantId)->whereIn('status', ['open', 'investigating'])->count(),
            'total_surveys' => SatisfactionSurvey::where('tenant_id', $tenantId)->count(),
        ]);
    }
}
