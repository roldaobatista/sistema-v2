<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fiscal\CalculateRetentionsRequest;
use App\Http\Requests\Fiscal\CheckRegimeRequest;
use App\Http\Requests\Fiscal\CreateWebhookRequest;
use App\Http\Requests\Fiscal\EmitBatchRequest;
use App\Http\Requests\Fiscal\EmitirComplementarRequest;
use App\Http\Requests\Fiscal\EmitirCTeRequest;
use App\Http\Requests\Fiscal\EmitirDevolucaoRequest;
use App\Http\Requests\Fiscal\EmitirRemessaRequest;
use App\Http\Requests\Fiscal\EmitirRetornoRequest;
use App\Http\Requests\Fiscal\ManifestarDestinatarioRequest;
use App\Http\Requests\Fiscal\PaymentConfirmedRequest;
use App\Http\Requests\Fiscal\SaveTemplateFromNoteRequest;
use App\Http\Requests\Fiscal\SaveTemplateRequest;
use App\Http\Requests\Fiscal\ScheduleEmissionRequest;
use App\Http\Requests\Fiscal\SearchByAccessKeyRequest;
use App\Http\Requests\Fiscal\SplitPaymentRequest;
use App\Http\Requests\Fiscal\ValidateDocumentRequest;
use App\Models\FiscalAuditLog;
use App\Models\FiscalNote;
use App\Services\Fiscal\FiscalAdvancedService;
use App\Services\Fiscal\FiscalAutomationService;
use App\Services\Fiscal\FiscalComplianceService;
use App\Services\Fiscal\FiscalFinanceService;
use App\Services\Fiscal\FiscalTemplateService;
use App\Services\Fiscal\FiscalWebhookService;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Expanded fiscal controller for features #6-30.
 */
class FiscalExpandedController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(
        private FiscalAutomationService $automation,
        private FiscalAdvancedService $advanced,
        private FiscalComplianceService $compliance,
        private FiscalFinanceService $finance,
        private FiscalTemplateService $templates,
        private FiscalWebhookService $webhooks,
    ) {}

    // ─── Automation (#6-9) ─────────────────────────

    /**
     * #7 — Batch emit fiscal notes.
     */
    public function emitBatch(EmitBatchRequest $request): JsonResponse
    {
        $request->validated();
        $result = $this->automation->emitBatch(
            $request->source_ids,
            $request->source_type,
            $request->note_type,
            $this->tenantId(),
            $request->user()->id,
        );

        return ApiResponse::data($result);
    }

    /**
     * #8 — Schedule emission for a future date.
     */
    public function scheduleEmission(ScheduleEmissionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $scheduled = $this->automation->scheduleEmission(
            $validated,
            Carbon::parse($validated['scheduled_at']),
            $this->tenantId(),
            $request->user()->id,
        );

        return ApiResponse::data($scheduled, 201);
    }

    /**
     * #9 — Retry email for a fiscal note.
     */
    public function retryEmail(Request $request, int $id): JsonResponse
    {
        $note = FiscalNote::where('tenant_id', $this->tenantId())->findOrFail($id);
        $result = $this->automation->retryEmail($note);

        return ApiResponse::data($result);
    }

    // ─── Webhooks (#10) ────────────────────────────

    public function listWebhooks(Request $request): JsonResponse
    {
        return ApiResponse::data($this->webhooks->listForTenant($this->tenantId()));
    }

    public function createWebhook(CreateWebhookRequest $request): JsonResponse
    {
        $request->validated();
        $webhook = $this->webhooks->createWebhook($this->tenantId(), $request->validated());

        return ApiResponse::data($webhook, 201);
    }

    public function deleteWebhook(Request $request, int $id): JsonResponse
    {
        $deleted = $this->webhooks->deleteWebhook($id, $this->tenantId());

        return $deleted
            ? ApiResponse::message('Webhook removido')
            : ApiResponse::message('Webhook não encontrado', 404);
    }

    // ─── Advanced NF-e (#11-15) ────────────────────

    /**
     * #11 — Issue return (devolução) NF-e.
     */
    public function emitirDevolucao(EmitirDevolucaoRequest $request, int $id): JsonResponse
    {
        $request->validated();
        $original = FiscalNote::where('tenant_id', $this->tenantId())->findOrFail($id);
        $result = $this->advanced->emitirDevolucao($original, $request->items, $request->user()->id);

        FiscalAuditLog::log($original, 'devolucao_emitida', $request->user()->id, ['result' => $result['success']]);

        return ApiResponse::data($result, $result['success'] ? 201 : 422);
    }

    /**
     * #12 — Issue complementary NF-e.
     */
    public function emitirComplementar(EmitirComplementarRequest $request, int $id): JsonResponse
    {
        $request->validated();
        $original = FiscalNote::where('tenant_id', $this->tenantId())->findOrFail($id);
        $result = $this->advanced->emitirComplementar($original, $request->validated(), $request->user()->id);

        FiscalAuditLog::log($original, 'complementar_emitida', $request->user()->id);

        return ApiResponse::data($result, $result['success'] ? 201 : 422);
    }

    /**
     * #13 — Issue remittance NF-e.
     */
    public function emitirRemessa(EmitirRemessaRequest $request): JsonResponse
    {
        $request->validated();
        $result = $this->advanced->emitirRemessa($request->validated(), $request->user()->tenant, $request->user()->id);

        return ApiResponse::data($result, $result['success'] ? 201 : 422);
    }

    /**
     * #13b — Issue return NF-e referencing remittance.
     */
    public function emitirRetorno(EmitirRetornoRequest $request, int $id): JsonResponse
    {
        $request->validated();
        $remessa = FiscalNote::where('tenant_id', $this->tenantId())->findOrFail($id);
        $result = $this->advanced->emitirRetorno($remessa, $request->items, $request->user()->id);

        return ApiResponse::data($result, $result['success'] ? 201 : 422);
    }

    /**
     * #14 — Manifesto do Destinatário.
     */
    public function manifestarDestinatario(ManifestarDestinatarioRequest $request): JsonResponse
    {
        $request->validated();
        $result = $this->advanced->manifestarDestinatario(
            $request->chave_acesso, $request->tipo, $request->user()->tenant,
        );

        return ApiResponse::data($result);
    }

    /**
     * #15 — Issue CT-e.
     */
    public function emitirCTe(EmitirCTeRequest $request): JsonResponse
    {
        $request->validated();
        $result = $this->advanced->emitirCTe($request->validated(), $request->user()->tenant, $request->user()->id);

        return ApiResponse::data($result, $result['success'] ? 201 : 422);
    }

    // ─── Compliance (#16-20) ───────────────────────

    /**
     * #16 — Certificate expiry alert.
     */
    public function certificateAlert(Request $request): JsonResponse
    {
        $result = $this->compliance->checkCertificateExpiry($request->user()->tenant);

        return ApiResponse::data($result);
    }

    /**
     * #17 — Audit log for a note.
     */
    public function auditLog(Request $request, int $id): JsonResponse
    {
        $logs = $this->compliance->getAuditLog($id, $this->tenantId());

        return ApiResponse::data($logs);
    }

    /**
     * #17b — Audit report.
     */
    public function auditReport(Request $request): JsonResponse
    {
        $result = $this->compliance->auditReport(
            $this->tenantId(),
            $request->query('from'),
            $request->query('to'),
        );

        return ApiResponse::data($result);
    }

    /**
     * #18 — Validate CNPJ/CPF.
     */
    public function validateDocument(ValidateDocumentRequest $request): JsonResponse
    {
        $request->validated();
        $result = $this->compliance->validateDocument($request->documento);

        return ApiResponse::data($result);
    }

    /**
     * #20 — Check regime compatibility.
     */
    public function checkRegime(CheckRegimeRequest $request): JsonResponse
    {
        $request->validated();
        $result = $this->compliance->blockIncompatibleEmission($request->user()->tenant, $request->type);

        return ApiResponse::data($result);
    }

    // ─── Finance (#21-25) ──────────────────────────

    /**
     * #21 — Reconcile with receivables.
     */
    public function reconcile(Request $request, int $id): JsonResponse
    {
        $note = FiscalNote::where('tenant_id', $this->tenantId())->findOrFail($id);
        $result = $this->finance->reconcileWithReceivables($note);
        FiscalAuditLog::log($note, 'reconciled', $request->user()->id);

        return ApiResponse::data($result);
    }

    /**
     * #22 — Generate boleto data.
     */
    public function generateBoleto(Request $request, int $id): JsonResponse
    {
        $note = FiscalNote::where('tenant_id', $this->tenantId())->findOrFail($id);
        $result = $this->finance->generateBoletoData($note, $request->query());

        return ApiResponse::data($result);
    }

    /**
     * #23 — Split payment.
     */
    public function splitPayment(SplitPaymentRequest $request, int $id): JsonResponse
    {
        $request->validated();
        $note = FiscalNote::where('tenant_id', $this->tenantId())->findOrFail($id);
        $result = $this->finance->applySplitPayment($note, $request->payments);

        return ApiResponse::data($result);
    }

    /**
     * #24 — Calculate retentions.
     */
    public function calculateRetentions(CalculateRetentionsRequest $request): JsonResponse
    {
        $request->validated();
        $result = $this->finance->calculateRetentions($request->items, $request->user()->tenant);

        return ApiResponse::data($result);
    }

    /**
     * #25 — Payment confirmed webhook.
     */
    public function paymentConfirmed(PaymentConfirmedRequest $request): JsonResponse
    {
        $request->validated();
        $result = $this->finance->onPaymentConfirmed(
            $this->tenantId(),
            $request->customer_id,
            $request->amount,
            $request->transaction_id,
        );

        return ApiResponse::data($result);
    }

    // ─── Templates & UX (#26-28) ───────────────────

    /**
     * #26 — List templates.
     */
    public function listTemplates(Request $request): JsonResponse
    {
        return ApiResponse::data($this->templates->listTemplates($this->tenantId()));
    }

    /**
     * #26 — Save template.
     */
    public function saveTemplate(SaveTemplateRequest $request): JsonResponse
    {
        $template = $this->templates->saveTemplate(
            $request->name, $request->type, $request->template_data,
            $this->tenantId(), $request->user()->id,
        );

        return ApiResponse::data($template, 201);
    }

    /**
     * #26 — Save template from existing note.
     */
    public function saveTemplateFromNote(SaveTemplateFromNoteRequest $request, int $id): JsonResponse
    {
        $request->validated();
        $note = FiscalNote::where('tenant_id', $this->tenantId())->findOrFail($id);
        $template = $this->templates->saveFromNote($note, $request->name);

        return ApiResponse::data($template, 201);
    }

    /**
     * #26 — Apply template.
     */
    public function applyTemplate(Request $request, int $id): JsonResponse
    {
        $data = $this->templates->applyTemplate($id, $this->tenantId());

        return $data
            ? ApiResponse::data($data)
            : ApiResponse::message('Template não encontrado', 404);
    }

    /**
     * #26 — Delete template.
     */
    public function deleteTemplate(Request $request, int $id): JsonResponse
    {
        $deleted = $this->templates->deleteTemplate($id, $this->tenantId());

        return $deleted
            ? ApiResponse::message('Template removido')
            : ApiResponse::message('Template não encontrado', 404);
    }

    /**
     * #27 — Duplicate a fiscal note.
     */
    public function duplicateNote(Request $request, int $id): JsonResponse
    {
        $note = FiscalNote::where('tenant_id', $this->tenantId())->findOrFail($id);
        $data = $this->templates->duplicateNote($note);

        return ApiResponse::data($data);
    }

    /**
     * #28 — Search by access key.
     */
    public function searchByAccessKey(SearchByAccessKeyRequest $request): JsonResponse
    {
        $request->validated();
        $note = $this->templates->searchByAccessKey($request->chave, $this->tenantId());

        return $note
            ? ApiResponse::data($note)
            : ApiResponse::message('Nota não encontrada com esta chave de acesso', 404);
    }
}
