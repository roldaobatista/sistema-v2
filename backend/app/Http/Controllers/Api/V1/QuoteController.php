<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\QuoteAlreadyConvertedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quote\AddQuoteEquipmentRequest;
use App\Http\Requests\Quote\AddQuoteItemRequest;
use App\Http\Requests\Quote\ApproveQuoteRequest;
use App\Http\Requests\Quote\BulkQuoteActionRequest;
use App\Http\Requests\Quote\CompareQuotesRequest;
use App\Http\Requests\Quote\ConvertQuoteRequest;
use App\Http\Requests\Quote\CreateFromTemplateRequest;
use App\Http\Requests\Quote\ListQuotesRequest;
use App\Http\Requests\Quote\RejectQuoteRequest;
use App\Http\Requests\Quote\RevertFromRenegotiationRequest;
use App\Http\Requests\Quote\SendQuoteEmailRequest;
use App\Http\Requests\Quote\StoreQuoteNestedItemRequest;
use App\Http\Requests\Quote\StoreQuoteRequest;
use App\Http\Requests\Quote\StoreQuoteTagRequest;
use App\Http\Requests\Quote\StoreQuoteTemplateRequest;
use App\Http\Requests\Quote\SyncQuoteTagsRequest;
use App\Http\Requests\Quote\UpdateQuoteEquipmentRequest;
use App\Http\Requests\Quote\UpdateQuoteItemRequest;
use App\Http\Requests\Quote\UpdateQuoteRequest;
use App\Http\Requests\Quote\UpdateQuoteTemplateRequest;
use App\Http\Requests\Quote\UploadQuotePhotoRequest;
use App\Http\Resources\QuoteResource;
use App\Models\Quote;
use App\Models\QuoteEquipment;
use App\Models\QuoteItem;
use App\Models\QuotePhoto;
use App\Models\QuoteTag;
use App\Models\QuoteTemplate;
use App\Services\PdfGeneratorService;
use App\Services\QuoteService;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use App\Traits\ScopesByRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuoteController extends Controller
{
    use ResolvesCurrentTenant, ScopesByRole;

    private const QUOTE_RESPONSE_RELATIONS = [
        'customer',
        'seller',
        'internalApprover:id,name',
        'level2Approver:id,name',
        'equipments.equipment',
        'equipments.items.product',
        'equipments.items.service',
    ];

    private const PUBLIC_QUOTE_RESPONSE_RELATIONS = [
        'customer:id,name',
        'seller:id,name',
        'equipments.equipment',
        'equipments.items.product:id,name',
        'equipments.items.service:id,name',
    ];

    public function __construct(
        protected QuoteService $service
    ) {}

    private function quotePayload(Quote $quote): QuoteResource
    {
        return new QuoteResource($quote->load(self::QUOTE_RESPONSE_RELATIONS));
    }

    public function index(ListQuotesRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Quote::class);

        try {
            $result = $this->service->index(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId());

            return $result instanceof LengthAwarePaginator ? ApiResponse::paginated($result, resourceClass: QuoteResource::class) : ApiResponse::data($result, 200);
        } catch (\Exception $e) {
            Log::error('index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function store(StoreQuoteRequest $request): JsonResponse
    {
        $this->authorize('create', Quote::class);
        $validated = method_exists($request, 'validated') ? $request->validated() : $request->all();

        try {
            $tenantId = $this->tenantId();
            $quote = $this->service->createQuote($validated, $tenantId, (int) auth()->id());

            return ApiResponse::data(
                new QuoteResource($quote->load(['customer', 'seller', 'equipments.equipment', 'equipments.items.product', 'equipments.items.service'])),
                201
            );
        } catch (AuthorizationException $e) {
            return ApiResponse::message($e->getMessage(), 403);
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            report($e);

            return ApiResponse::message('Erro ao criar orçamento', 500);
        }
    }

    public function show(Quote $quote): JsonResponse
    {
        $this->authorize('view', $quote);

        try {
            $result = $this->service->show($quote, auth()->user(), $this->tenantId());

            return ApiResponse::data($result, 200);
        } catch (\Exception $e) {
            Log::error('show failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function update(UpdateQuoteRequest $request, Quote $quote): JsonResponse
    {
        $this->authorize('update', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }

        $validated = $request->validated();

        try {
            $updatedQuote = $this->service->updateQuote($quote, $validated, $request->user());

            return ApiResponse::data(new QuoteResource($updatedQuote->fresh([
                'customer',
                'seller',
                'equipments.equipment',
                'equipments.items.product',
                'equipments.items.service',
            ])));
        } catch (AuthorizationException $e) {
            return ApiResponse::message($e->getMessage(), 403);
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            report($e);

            return ApiResponse::message('Erro ao atualizar orçamento', 500);
        }
    }

    public function destroy(Quote $quote): JsonResponse|Response
    {
        $this->authorize('delete', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }
        if ($quote->workOrders()->exists()) {
            return ApiResponse::message('Orçamento possui OS vinculada e não pode ser excluído.', 409);
        }
        if ($quote->serviceCalls()->exists()) {
            return ApiResponse::message('Orçamento possui chamado vinculado e não pode ser excluído.', 409);
        }

        try {
            $result = $this->service->destroy($quote, auth()->user(), $this->tenantId());

            return response()->noContent();
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('destroy failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function bulkAction(BulkQuoteActionRequest $request): JsonResponse
    {
        try {
            $result = $this->service->bulkAction(
                $request->validated('action'),
                $request->validated('ids'),
                $request->user(),
                $this->tenantId()
            );

            return ApiResponse::data($result);
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('bulkAction failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao executar acao em massa', 500);
        }
    }

    // ── Ações de Negócio ──

    /**
     * Solicitar aprovação interna: draft -> pending_internal_approval.
     */
    public function requestInternalApproval(Quote $quote): JsonResponse
    {
        $this->authorize('send', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }

        try {
            $this->service->requestInternalApproval($quote);

            return ApiResponse::data($this->quotePayload($quote->fresh()));
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            report($e);

            return ApiResponse::message('Erro ao solicitar aprovação interna', 500);
        }
    }

    public function send(Quote $quote): JsonResponse
    {
        $this->authorize('send', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }

        try {
            $this->service->sendQuote($quote);

            return ApiResponse::data($this->quotePayload($quote->fresh()));
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            report($e);

            return ApiResponse::message('Erro ao enviar orçamento', 500);
        }
    }

    public function approve(ApproveQuoteRequest $request, Quote $quote): JsonResponse
    {
        $this->authorize('approve', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }

        try {
            $user = auth()->user();
            $this->service->approveQuote(
                $quote,
                $user,
                [
                    'approval_channel' => $request->validated('approval_channel'),
                    'approval_notes' => $request->validated('approval_notes'),
                    'term_accepted_at' => now(),
                ]
            );

            return ApiResponse::data($this->quotePayload($quote->fresh()));
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            report($e);

            return ApiResponse::message('Erro ao aprovar orçamento', 500);
        }
    }

    /**
     * Internal approval (before sending to client).
     * Only quotes in 'pending_internal_approval' can be internally approved.
     */
    public function internalApprove(Quote $quote): JsonResponse
    {
        $this->authorize('internalApprove', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }

        try {
            $result = $this->service->internalApprove($quote, auth()->user(), $this->tenantId());
            if (is_string($result)) {
                return ApiResponse::message($result, 422);
            }

            return ApiResponse::data($this->quotePayload($result), 200);
        } catch (\Exception $e) {
            Log::error('internalApprove failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function reject(RejectQuoteRequest $request, Quote $quote): JsonResponse
    {
        $this->authorize('approve', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }

        try {
            $this->service->rejectQuote($quote, $request->validated('reason'));

            return ApiResponse::data($this->quotePayload($quote->fresh()));
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            report($e);

            return ApiResponse::message('Erro ao rejeitar orçamento', 500);
        }
    }

    public function reopen(Quote $quote): JsonResponse
    {
        $this->authorize('update', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }

        try {
            $this->service->reopenQuote($quote);

            return ApiResponse::data($this->quotePayload($quote->fresh()));
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            report($e);

            return ApiResponse::message('Erro ao reabrir orçamento', 500);
        }
    }

    public function markAsInvoiced(Quote $quote): JsonResponse
    {
        $this->authorize('approve', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }

        try {
            $this->service->markAsInvoiced($quote, (int) auth()->id());

            return ApiResponse::data($this->quotePayload($quote->fresh()));
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            report($e);

            return ApiResponse::message('Erro ao faturar orçamento', 500);
        }
    }

    public function duplicate(Quote $quote): JsonResponse
    {
        $this->authorize('create', Quote::class);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }

        try {
            $newQuote = $this->service->duplicateQuote($quote);

            return ApiResponse::data(new QuoteResource($newQuote->load(self::QUOTE_RESPONSE_RELATIONS)), 201);
        } catch (\Exception $e) {
            Log::error('Quote duplicate failed', [
                'quote_id' => $quote->id,
                'error' => $e->getMessage(),
                'class' => get_class($e),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);

            return ApiResponse::message('Erro ao duplicar orçamento', 500);
        }
    }

    public function convertToWorkOrder(ConvertQuoteRequest $request, Quote $quote): JsonResponse
    {
        $this->authorize('convert', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }

        $isInstallationTesting = $request->boolean('is_installation_testing');

        try {
            $wo = $this->service->convertToWorkOrder($quote, (int) auth()->id(), $isInstallationTesting);

            return ApiResponse::data($wo->load('items'), 201);
        } catch (QuoteAlreadyConvertedException $e) {
            return ApiResponse::data([
                'work_order' => $e->workOrder->only(['id', 'number', 'os_number', 'status', 'business_number']),
            ], 409, ['message' => $e->getMessage()]);
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            report($e);

            return ApiResponse::message('Erro ao converter orçamento em OS', 500);
        }
    }

    public function convertToServiceCall(ConvertQuoteRequest $request, Quote $quote): JsonResponse
    {
        $this->authorize('convert', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }

        $isInstallationTesting = $request->boolean('is_installation_testing');

        try {
            $call = $this->service->convertToServiceCall($quote, (int) auth()->id(), $isInstallationTesting);

            return ApiResponse::data($call, 201);
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            report($e);

            return ApiResponse::message('Erro ao converter orçamento em chamado', 500);
        }
    }

    public function approveAfterTest(Quote $quote): JsonResponse
    {
        $this->authorize('convert', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }

        try {
            $updated = $this->service->approveAfterTest($quote, (int) auth()->id());

            return ApiResponse::data($this->quotePayload($updated));
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            report($e);

            return ApiResponse::message('Erro ao aprovar orçamento após teste', 500);
        }
    }

    public function sendToRenegotiation(Quote $quote): JsonResponse
    {
        $this->authorize('convert', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }

        try {
            $updated = $this->service->sendToRenegotiation($quote, (int) auth()->id());

            return ApiResponse::data($this->quotePayload($updated));
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            report($e);

            return ApiResponse::message('Erro ao enviar para renegociação', 500);
        }
    }

    public function revertFromRenegotiation(RevertFromRenegotiationRequest $request, Quote $quote): JsonResponse
    {
        $this->authorize('convert', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }

        try {
            $updated = $this->service->revertFromRenegotiation($quote, $request->validated()['target_status'], (int) auth()->id());

            return ApiResponse::data($this->quotePayload($updated));
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            report($e);

            return ApiResponse::message('Erro ao reverter renegociação', 500);
        }
    }

    // ── Equipamentos, Itens e Fotos ──

    public function addEquipment(AddQuoteEquipmentRequest $request, Quote $quote): JsonResponse
    {
        $this->authorize('update', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }

        try {
            $result = $this->service->addEquipment(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId(), $quote);

            return ApiResponse::data($result, 201);
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('addEquipment failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function removeEquipment(Quote $quote, QuoteEquipment $equipment): JsonResponse|Response
    {
        $this->authorize('update', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }

        try {
            $result = $this->service->removeEquipment($quote, $equipment, auth()->user(), $this->tenantId());

            return response()->noContent();
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('removeEquipment failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function updateEquipment(UpdateQuoteEquipmentRequest $request, QuoteEquipment $equipment): JsonResponse
    {
        $equipment->loadMissing('quote');
        $quote = $equipment->quote;
        abort_if(! $quote, 404, 'Orçamento não encontrado');
        $this->authorize('update', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }

        try {
            $result = $this->service->updateEquipment($equipment, method_exists($request, 'validated') ? $request->validated() : $request->all());

            return ApiResponse::data($result, 200);
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('updateEquipment failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function addItem(AddQuoteItemRequest $request, QuoteEquipment $equipment): JsonResponse
    {
        $equipment->loadMissing('quote');
        $quote = $equipment->quote;
        abort_if(! $quote, 404, 'Orçamento não encontrado');
        $this->authorize('update', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }
        $validated = method_exists($request, 'validated') ? $request->validated() : $request->all();

        try {
            $result = $this->service->addItem(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId(), $equipment);

            return ApiResponse::data($result, 201);
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('addItem failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function items(Quote $quote): JsonResponse
    {
        $this->authorize('view', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }

        try {
            $result = $this->service->items($quote);

            return ApiResponse::data($result, 200);
        } catch (\Exception $e) {
            Log::error('items failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function storeNestedItem(StoreQuoteNestedItemRequest $request, Quote $quote): JsonResponse
    {
        $this->authorize('update', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }

        try {
            $result = $this->service->storeNestedItem(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId(), $quote);

            return ApiResponse::data($result, 201);
        } catch (\Exception $e) {
            Log::error('storeNestedItem failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function updateItem(UpdateQuoteItemRequest $request, QuoteItem $item): JsonResponse
    {
        $item->loadMissing('quoteEquipment.quote');
        $quote = $item->quoteEquipment?->quote;
        abort_if(! $quote, 404, 'Item não pertence a um orçamento válido');
        $this->authorize('update', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }

        $validated = $request->validated();

        try {
            $item = $this->service->updateItem($item, $validated);

            return ApiResponse::data($item);
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            report($e);

            return ApiResponse::message('Erro ao atualizar item', 500);
        }
    }

    public function removeItem(QuoteItem $item): JsonResponse
    {
        $item->loadMissing('quoteEquipment.quote');
        $quote = $item->quoteEquipment?->quote;
        abort_if(! $quote, 404, 'Orçamento não encontrado');
        $this->authorize('update', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }

        try {
            $result = $this->service->removeItem($item);

            return ApiResponse::data($result, 200);
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('removeItem failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function addPhoto(UploadQuotePhotoRequest $request, Quote $quote): JsonResponse
    {
        $this->authorize('update', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }

        try {
            $validated = $request->validated();
            $file = $request->file('file');
            $result = $this->service->addPhoto($validated, $file, $request->user(), $this->tenantId(), $quote);

            return ApiResponse::data($result, 201);
        } catch (\Exception $e) {
            Log::error('addPhoto failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function removePhoto(QuotePhoto $photo): JsonResponse
    {
        $photo->loadMissing('quoteEquipment.quote');
        $quote = $photo->quoteEquipment?->quote;
        abort_if(! $quote, 404, 'Orçamento não encontrado');
        $this->authorize('update', $quote);

        try {
            $result = $this->service->removePhoto($photo);

            return ApiResponse::data($result, 200);
        } catch (\Exception $e) {
            Log::error('removePhoto failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function summary(): JsonResponse
    {
        $this->authorize('viewAny', Quote::class);

        try {
            $result = $this->service->summary(auth()->user(), $this->tenantId());

            return ApiResponse::data($result, 200);
        } catch (\Exception $e) {
            Log::error('summary failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function timeline(Quote $quote): JsonResponse
    {
        $this->authorize('view', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }

        try {
            $result = $this->service->timeline($quote, request()->user(), $this->tenantId());

            return ApiResponse::data($result, 200);
        } catch (\Exception $e) {
            Log::error('timeline failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        return $this->service->exportCsv(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId());
    }

    public function publicView(Quote $quote, Request $request): JsonResponse
    {
        if (! $quote->matchesPublicAccessToken((string) $request->query('token', ''))) {
            return ApiResponse::message('Token inválido', 403);
        }

        $this->service->trackClientView($quote);

        return ApiResponse::data(QuoteResource::forPublicContract(
            $quote->load(self::PUBLIC_QUOTE_RESPONSE_RELATIONS)
        ));
    }

    public function publicApprove(Quote $quote, Request $request): JsonResponse
    {
        if (! $quote->matchesPublicAccessToken((string) $request->query('token', ''))) {
            return ApiResponse::message('Token inválido', 403);
        }

        try {
            $quote->loadMissing('customer:id,name');

            $this->service->publicApprove($quote, [
                'client_ip_approval' => $request->ip(),
                'term_accepted_at' => now(),
                'approval_channel' => 'public_token',
                'approved_by_name' => $quote->customer?->name ?? 'Cliente',
            ]);

            $approvedQuote = $quote->fresh(self::PUBLIC_QUOTE_RESPONSE_RELATIONS);

            if (! $approvedQuote) {
                return ApiResponse::message('Orçamento não encontrado', 404);
            }

            return ApiResponse::data(
                QuoteResource::forPublicContract($approvedQuote),
                200,
                ['message' => 'Orçamento aprovado com sucesso']
            );
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            report($e);

            return ApiResponse::message('Erro ao aprovar orçamento', 500);
        }
    }

    // ── New endpoints (30-improvements) ──

    public function sendEmail(SendQuoteEmailRequest $request, Quote $quote): JsonResponse
    {
        $this->authorize('send', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }

        $validated = $request->validated();

        try {
            $emailLog = $this->service->sendEmail(
                $quote,
                $validated['recipient_email'],
                $validated['recipient_name'] ?? null,
                $validated['message'] ?? null,
                $request->user()->id,
            );

            return ApiResponse::data($emailLog, 201, ['message' => 'E-mail enviado com sucesso']);
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            report($e);
            Log::error('Erro ao enviar e-mail: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::message('Erro ao enviar e-mail', 500);
        }
    }

    public function advancedSummary(): JsonResponse
    {
        $this->authorize('viewAny', Quote::class);

        return ApiResponse::data($this->service->advancedSummary(
            $this->tenantId(),
            $this->shouldScopeByUser() ? (int) auth()->id() : null,
        ));
    }

    public function tags(Quote $quote): JsonResponse
    {
        $this->authorize('view', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }

        try {
            $result = $this->service->tags($quote, auth()->user(), $this->tenantId());

            return ApiResponse::data($result, 200);
        } catch (\Exception $e) {
            Log::error('tags failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function syncTags(SyncQuoteTagsRequest $request, Quote $quote): JsonResponse
    {
        $this->authorize('update', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }

        try {
            $result = $this->service->syncTags(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId(), $quote);

            return ApiResponse::data($result, 200);
        } catch (\Exception $e) {
            Log::error('syncTags failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function listTags(): JsonResponse
    {
        $this->authorize('viewAny', Quote::class);

        try {
            $result = $this->service->listTags(auth()->user(), $this->tenantId());

            return ApiResponse::data($result, 200);
        } catch (\Exception $e) {
            Log::error('listTags failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function storeTag(StoreQuoteTagRequest $request): JsonResponse
    {
        $this->authorize('create', Quote::class);

        try {
            $result = $this->service->storeTag(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId());

            return ApiResponse::data($result, 201);
        } catch (\Exception $e) {
            Log::error('storeTag failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function destroyTag(QuoteTag $tag): JsonResponse|Response
    {
        $this->authorize('viewAny', Quote::class);

        try {
            $result = $this->service->destroyTag($tag, request()->user(), $this->tenantId());

            return response()->noContent();
        } catch (\Exception $e) {
            Log::error('destroyTag failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function listTemplates(): JsonResponse
    {
        $this->authorize('viewAny', Quote::class);

        try {
            $result = $this->service->listTemplates(auth()->user(), $this->tenantId());

            return ApiResponse::data($result, 200);
        } catch (\Exception $e) {
            Log::error('listTemplates failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function storeTemplate(StoreQuoteTemplateRequest $request): JsonResponse
    {
        $this->authorize('create', Quote::class);

        try {
            $result = $this->service->storeTemplate(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId());

            return ApiResponse::data($result, 201);
        } catch (\Exception $e) {
            Log::error('storeTemplate failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function updateTemplate(UpdateQuoteTemplateRequest $request, QuoteTemplate $template): JsonResponse
    {
        $this->authorize('create', Quote::class);

        try {
            $result = $this->service->updateTemplate(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId(), $template);

            return ApiResponse::data($result, 200);
        } catch (\Exception $e) {
            Log::error('updateTemplate failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function destroyTemplate(QuoteTemplate $template): JsonResponse|Response
    {
        $this->authorize('viewAny', Quote::class);

        try {
            $result = $this->service->destroyTemplate($template, request()->user(), $this->tenantId());

            return response()->noContent();
        } catch (\Exception $e) {
            Log::error('destroyTemplate failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function createFromTemplate(CreateFromTemplateRequest $request, QuoteTemplate $template): JsonResponse
    {
        $this->authorize('create', Quote::class);
        if ($template->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Template não encontrado', 404);
        }

        try {
            $quote = $this->service->createFromTemplate(
                $template,
                $request->validated(),
                $this->tenantId(),
                $request->user()->id,
            );

            return ApiResponse::data($this->quotePayload($quote), 201);
        } catch (\Exception $e) {
            report($e);
            Log::error($e->getMessage(), ['exception' => $e]);

            return ApiResponse::message('Erro ao criar orçamento a partir do template', 500);
        }
    }

    public function compareQuotes(CompareQuotesRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Quote::class);

        try {
            $result = $this->service->compareQuotes(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId());
            if (is_string($result)) {
                return ApiResponse::message($result, 404);
            }

            return ApiResponse::data($result, 200);
        } catch (\Exception $e) {
            Log::error('compareQuotes failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function compareRevisions(Quote $quote): JsonResponse
    {
        $this->authorize('view', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }

        try {
            $result = $this->service->compareRevisions($quote);

            return ApiResponse::data($result, 200);
        } catch (\Exception $e) {
            Log::error('compareRevisions failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function approveLevel2(Request $request, Quote $quote): JsonResponse
    {
        $this->authorize('internalApprove', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }

        try {
            $updated = $this->service->internalApproveLevel2($quote, $request->user()->id);

            return ApiResponse::data($updated, 200, ['message' => 'Aprovação nível 2 realizada']);
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            report($e);

            return ApiResponse::message('Erro ao aprovar nível 2', 500);
        }
    }

    public function whatsappLink(Request $request, Quote $quote): JsonResponse
    {
        $this->authorize('view', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }

        try {
            $result = $this->service->whatsappLink(method_exists($request, 'validated') ? $request->validated() : $request->all(), $request->user(), $this->tenantId(), $quote);

            return ApiResponse::data($result, 200);
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('whatsappLink failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function installmentSimulation(Quote $quote): JsonResponse
    {
        $this->authorize('view', $quote);
        if ($error = $this->ensureTenantOwnership($quote, 'Orçamento')) {
            return $error;
        }

        try {
            $result = $this->service->installmentSimulation($quote);

            return ApiResponse::data($result, 200);
        } catch (\Exception $e) {
            Log::error('installmentSimulation failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function publicPdf(Quote $quote, Request $request)
    {
        $token = $request->input('token');

        if (! $quote->matchesPublicAccessToken((string) $token)) {
            return ApiResponse::message('Acesso não autorizado ou token inválido.', 403);
        }

        if ($quote->isExpired()) {
            return ApiResponse::message('Este orçamento já está expirado e não pode ser visualizado.', 410);
        }

        try {
            $inline = $request->boolean('inline');
            $filename = "Orcamento-{$quote->quote_number}.pdf";

            // Cache PDF content for 5 minutes keyed by quote id + last update
            $cacheKey = "quote_pdf:{$quote->id}:".($quote->updated_at->timestamp ?? 0);
            $pdfContent = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($quote) {
                $pdfService = app(PdfGeneratorService::class);

                return $pdfService->renderQuotePdf($quote)->output();
            });

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => ($inline ? 'inline' : 'attachment')."; filename=\"{$filename}\"",
                'Cache-Control' => 'private, max-age=300',
            ]);
        } catch (\Exception $e) {
            Log::error('publicPdf failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }
}
