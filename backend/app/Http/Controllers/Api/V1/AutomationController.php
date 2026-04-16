<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Automation\StoreAutomationRuleRequest;
use App\Http\Requests\Automation\StoreAutomationWebhookRequest;
use App\Http\Requests\Automation\StoreScheduledReportRequest;
use App\Http\Requests\Automation\UpdateAutomationRuleRequest;
use App\Http\Requests\Automation\UpdateAutomationWebhookRequest;
use App\Http\Requests\Automation\UpdateScheduledReportRequest;
use App\Models\AutomationRule;
use App\Models\ScheduledReport;
use App\Models\Webhook;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutomationController extends Controller
{
    use ResolvesCurrentTenant;

    // ─── AUTOMATION RULES ────────────────────────────────────────

    public function indexRules(Request $request): JsonResponse
    {
        $query = AutomationRule::where('tenant_id', $this->resolvedTenantId());
        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return ApiResponse::paginated($query->orderBy('name')->paginate(min((int) $request->input('per_page', 20), 100)));
    }

    public function storeRule(StoreAutomationRuleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();
            $validated['tenant_id'] = $this->resolvedTenantId();
            $validated['created_by'] = $request->user()->id;
            $rule = AutomationRule::create($validated);
            DB::commit();

            return ApiResponse::data($rule, 201, ['message' => 'Automação criada']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('AutomationRule create failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar automação.', 500);
        }
    }

    public function updateRule(UpdateAutomationRuleRequest $request, AutomationRule $rule): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();
            $rule->update($validated);
            DB::commit();

            return ApiResponse::data($rule->fresh(), 200, ['message' => 'Automação atualizada']);
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::message('Erro ao atualizar.', 500);
        }
    }

    public function destroyRule(AutomationRule $rule): JsonResponse
    {
        try {
            DB::beginTransaction();
            $rule->delete();
            DB::commit();

            return ApiResponse::message('Automação removida.');
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::message('Erro ao remover.', 500);
        }
    }

    public function toggleRule(AutomationRule $rule): JsonResponse
    {
        try {
            DB::beginTransaction();
            $rule->update(['is_active' => ! $rule->is_active]);
            DB::commit();

            return ApiResponse::data($rule->fresh(), 200, ['message' => 'Status da automação atualizado']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('AutomationRule toggle failed', ['id' => $rule->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar status da automação.', 500);
        }
    }

    public function availableEvents(): JsonResponse
    {
        return ApiResponse::data([
            'work_order.created', 'work_order.completed', 'work_order.cancelled',
            'quote.created', 'quote.approved', 'quote.rejected', 'quote.expiring',
            'payment.received', 'payment.overdue',
            'certificate.generated', 'certificate.expiring',
            'customer.created', 'customer.inactive',
            'expense.created', 'expense.approved',
            'service_call.created', 'service_call.completed',
            'equipment.calibration_due',
        ]);
    }

    public function availableActions(): JsonResponse
    {
        return ApiResponse::data([
            ['type' => 'send_email', 'label' => 'Enviar e-mail', 'params' => ['to', 'subject', 'body']],
            ['type' => 'send_notification', 'label' => 'Enviar notificação push', 'params' => ['user_id', 'message']],
            ['type' => 'create_task', 'label' => 'Criar tarefa na central', 'params' => ['title', 'assigned_to']],
            ['type' => 'change_status', 'label' => 'Alterar status', 'params' => ['new_status']],
            ['type' => 'webhook', 'label' => 'Disparar webhook', 'params' => ['webhook_id']],
            ['type' => 'create_followup', 'label' => 'Agendar follow-up', 'params' => ['days_ahead', 'assigned_to']],
        ]);
    }

    // ─── WEBHOOKS ────────────────────────────────────────────────

    public function indexWebhooks(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            Webhook::where('tenant_id', $this->resolvedTenantId())
                ->withCount('logs')
                ->orderBy('name')
                ->paginate(min((int) $request->input('per_page', 20), 100))
        );
    }

    public function storeWebhook(StoreAutomationWebhookRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();
            $validated['tenant_id'] = $this->resolvedTenantId();
            $webhook = Webhook::create($validated);
            DB::commit();

            return ApiResponse::data($webhook, 201, ['message' => 'Webhook criado']);
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::message('Erro ao criar webhook.', 500);
        }
    }

    public function updateWebhook(UpdateAutomationWebhookRequest $request, Webhook $webhook): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();
            $webhook->update($validated);
            DB::commit();

            return ApiResponse::data($webhook->fresh(), 200, ['message' => 'Webhook atualizado']);
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::message('Erro ao atualizar.', 500);
        }
    }

    public function destroyWebhook(Webhook $webhook): JsonResponse
    {
        try {
            DB::beginTransaction();
            $webhook->delete();
            DB::commit();

            return ApiResponse::message('Webhook removido.');
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::message('Erro ao remover.', 500);
        }
    }

    public function webhookLogs(Request $request, Webhook $webhook): JsonResponse
    {
        return ApiResponse::paginated(
            $webhook->logs()->orderByDesc('created_at')->paginate(min((int) $request->input('per_page', 50), 100))
        );
    }

    // ─── SCHEDULED REPORTS ───────────────────────────────────────

    public function indexScheduledReports(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            ScheduledReport::where('tenant_id', $this->resolvedTenantId())
                ->with('creator:id,name')
                ->orderBy('report_type')
                ->paginate(min((int) $request->input('per_page', 20), 100))
        );
    }

    public function storeScheduledReport(StoreScheduledReportRequest $request): JsonResponse
    {
        $tenantId = $this->resolvedTenantId();
        $validated = $request->validated();

        try {
            DB::beginTransaction();
            $validated['tenant_id'] = $tenantId;
            $validated['created_by'] = $request->user()->id;
            $validated['next_send_at'] = now()->addDay();
            $report = ScheduledReport::create($validated);
            DB::commit();

            return ApiResponse::data($report, 201, ['message' => 'Relatório agendado']);
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::message('Erro ao agendar relatório.', 500);
        }
    }

    public function updateScheduledReport(UpdateScheduledReportRequest $request, ScheduledReport $report): JsonResponse
    {
        if ((int) $report->tenant_id !== $this->resolvedTenantId()) {
            return ApiResponse::message('Registro não encontrado.', 404);
        }

        $validated = $request->validated();

        try {
            DB::beginTransaction();
            $report->update($validated);
            DB::commit();

            return ApiResponse::data($report->fresh(), 200, ['message' => 'Relatório atualizado']);
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::message('Erro ao atualizar.', 500);
        }
    }

    public function destroyScheduledReport(ScheduledReport $report): JsonResponse
    {
        if ((int) $report->tenant_id !== $this->resolvedTenantId()) {
            return ApiResponse::message('Registro não encontrado.', 404);
        }

        try {
            DB::transaction(fn () => $report->delete());

            return ApiResponse::message('Relatorio removido.');
        } catch (\Exception $e) {
            Log::error('ScheduledReport delete failed', ['id' => $report->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao remover.', 500);
        }
    }
}
