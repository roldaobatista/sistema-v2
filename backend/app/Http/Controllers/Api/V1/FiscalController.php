<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fiscal\CancelarNotaRequest;
use App\Http\Requests\Fiscal\CartaCorrecaoRequest;
use App\Http\Requests\Fiscal\EmitNfeRequest;
use App\Http\Requests\Fiscal\EmitNfseRequest;
use App\Http\Requests\Fiscal\InutilizarRequest;
use App\Http\Requests\Fiscal\SendFiscalEmailRequest;
use App\Models\FiscalEvent;
use App\Models\FiscalNote;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\WorkOrder;
use App\Services\Fiscal\ContingencyService;
use App\Services\Fiscal\Contracts\FiscalGatewayInterface;
use App\Services\Fiscal\DTO\NFeDTO;
use App\Services\Fiscal\FiscalEmailService;
use App\Services\Fiscal\FiscalNumberingService;
use App\Services\Fiscal\FiscalProvider;
use App\Services\Fiscal\FocusNFeProvider;
use App\Services\Fiscal\NFeDataBuilder;
use App\Services\Fiscal\NFSeDataBuilder;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class FiscalController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(
        private FiscalGatewayInterface $fiscalGateway,
        private FiscalProvider $provider,
    ) {}

    /**
     * List fiscal notes for the current tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();

        $query = FiscalNote::forTenant($tenantId)
            ->with(['customer:id,name', 'workOrder:id,number', 'creator:id,name'])
            ->orderByDesc('created_at');

        if ($request->filled('type')) {
            $query->ofType($request->input('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->input('to').' 23:59:59');
        }

        if ($request->filled('search')) {
            $search = SearchSanitizer::escapeLike($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('access_key', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $notes = $query->paginate($perPage);

        return ApiResponse::paginated($notes);
    }

    /**
     * Show a single fiscal note with events.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->tenantId();

        $note = FiscalNote::forTenant($tenantId)
            ->with(['customer', 'workOrder', 'quote', 'creator', 'events'])
            ->findOrFail($id);

        return ApiResponse::data($note);
    }

    /**
     * Consulta status de NF-e pelo protocolo/referência (via gateway externo).
     */
    public function consultarStatus(Request $request, string $protocolo): JsonResponse
    {
        $result = $this->fiscalGateway->consultarStatus($protocolo);

        if ($result->success) {
            return ApiResponse::data([
                'reference' => $result->reference,
                'access_key' => $result->accessKey,
                'number' => $result->number,
                'series' => $result->series,
                'status' => $result->status,
                'protocol_number' => $result->protocolNumber,
                'pdf_url' => $result->pdfUrl,
                'xml_url' => $result->xmlUrl,
            ]);
        }

        return ApiResponse::message($result->errorMessage, 422);
    }

    /**
     * Issue an NF-e.
     */
    public function emitirNFe(EmitNfeRequest $request): JsonResponse
    {
        $request->validated();

        return $this->emitDocument($request, FiscalNote::TYPE_NFE);
    }

    /**
     * Issue an NFS-e.
     */
    public function emitirNFSe(EmitNfseRequest $request): JsonResponse
    {
        $request->validated();

        return $this->emitDocument($request, FiscalNote::TYPE_NFSE);
    }

    /**
     * Issue NF-e from an existing Work Order.
     */
    public function emitirNFeFromWorkOrder(Request $request, int $workOrderId): JsonResponse
    {
        $tenantId = $this->tenantId();
        $wo = WorkOrder::where('tenant_id', $tenantId)->findOrFail($workOrderId);

        $items = $wo->items()->get()->map(fn ($item) => [
            'description' => $item->description ?? $item->name ?? 'Serviço',
            'quantity' => $item->quantity ?? 1,
            'unit_price' => $item->unit_price ?? 0,
            'ncm' => $item->ncm ?? null,
            'cfop' => $item->cfop ?? null,
            'unit' => $item->unit ?? 'UN',
        ])->toArray();

        if (empty($items)) {
            return ApiResponse::message('Ordem de Serviço sem itens', 422);
        }

        $request->merge([
            'customer_id' => $wo->customer_id,
            'work_order_id' => $wo->id,
            'items' => $items,
            'nature_of_operation' => 'Prestação de serviço',
        ]);

        return $this->emitDocument($request, FiscalNote::TYPE_NFE);
    }

    /**
     * Issue NFS-e from an existing Work Order.
     */
    public function emitirNFSeFromWorkOrder(Request $request, int $workOrderId): JsonResponse
    {
        $tenantId = $this->tenantId();
        $wo = WorkOrder::where('tenant_id', $tenantId)->findOrFail($workOrderId);

        $services = $wo->items()->get()->map(fn ($item) => [
            'description' => $item->description ?? $item->name ?? 'Serviço',
            'amount' => bcmul((string) ($item->quantity ?? 1), (string) ($item->unit_price ?? 0), 2),
            'quantity' => $item->quantity ?? 1,
            'service_code' => $item->service_code ?? null,
            'lc116_code' => $item->lc116_code ?? null,
            'municipal_service_code' => $item->municipal_service_code ?? null,
            'cnae_code' => $item->cnae_code ?? null,
            'iss_rate' => $item->iss_rate ?? null,
            'iss_retained' => $item->iss_retained ?? false,
        ])->toArray();

        if (empty($services)) {
            return ApiResponse::message('Ordem de Serviço sem itens', 422);
        }

        $request->merge([
            'customer_id' => $wo->customer_id,
            'work_order_id' => $wo->id,
            'services' => $services,
        ]);

        return $this->emitDocument($request, FiscalNote::TYPE_NFSE);
    }

    /**
     * Issue NF-e from an existing Quote.
     */
    public function emitirNFeFromQuote(Request $request, int $quoteId): JsonResponse
    {
        $tenantId = $this->tenantId();
        $quote = Quote::where('tenant_id', $tenantId)->findOrFail($quoteId);

        $quote->load('equipments.items.product');

        $items = $quote->equipments->flatMap(fn ($eq) => $eq->items)->map(fn ($item) => [
            'description' => $item->description ?? 'Item',
            'quantity' => $item->quantity ?? 1,
            'unit_price' => $item->unit_price ?? 0,
            'ncm' => $item->product?->ncm ?? null,
            'unit' => $item->product?->unit ?? 'UN',
        ])->toArray();

        if (empty($items)) {
            return ApiResponse::message('Orçamento sem itens', 422);
        }

        $request->merge([
            'customer_id' => $quote->customer_id,
            'quote_id' => $quote->id,
            'items' => $items,
            'nature_of_operation' => 'Venda de mercadoria',
        ]);

        return $this->emitDocument($request, FiscalNote::TYPE_NFE);
    }

    /**
     * Cancel a fiscal note.
     */
    public function cancelar(CancelarNotaRequest $request, int $id): JsonResponse
    {
        $request->validated();
        $tenantId = $this->tenantId();
        $note = FiscalNote::forTenant($tenantId)->findOrFail($id);

        if (! $note->canCancel()) {
            $reason = $note->cancelDeniedReason() ?? 'Esta nota não pode ser cancelada';

            return ApiResponse::message($reason, 409);
        }

        try {
            $referenceOrKey = $note->reference ?? $note->access_key;

            if (! $referenceOrKey) {
                return ApiResponse::message('Nota sem referência ou chave de acesso para cancelamento', 422);
            }

            $result = $note->isNFSe() && $this->provider instanceof FocusNFeProvider
                ? $this->provider->cancelarNFSe($referenceOrKey, $request->input('justificativa'))
                : $this->provider->cancelar($referenceOrKey, $request->input('justificativa'));

            $this->logEvent($note, 'cancellation', $request->user()->id, $result);

            if ($result->success) {
                $note->update([
                    'status' => FiscalNote::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'cancel_reason' => $request->input('justificativa'),
                ]);

                return ApiResponse::data($note->fresh(), 200, ['message' => 'Nota cancelada com sucesso']);
            }

            return ApiResponse::message('Erro ao cancelar: '.$result->errorMessage, 422);

        } catch (\Exception $e) {
            Log::error('Fiscal note cancel failed', ['id' => $id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro interno ao cancelar nota', 500);
        }
    }

    /**
     * Inutilizar numeração NF-e.
     */
    public function inutilizar(InutilizarRequest $request): JsonResponse
    {
        $request->validated();
        $tenantId = $this->tenantId();
        $tenant = Tenant::findOrFail($tenantId);

        try {
            $result = $this->provider->inutilizar([
                'cnpj' => preg_replace('/\D/', '', $tenant->document ?? ''),
                'serie' => $request->input('serie'),
                'numero_inicial' => $request->input('numero_inicial'),
                'numero_final' => $request->input('numero_final'),
                'justificativa' => $request->input('justificativa'),
            ]);

            if ($result->success) {
                FiscalEvent::create([
                    'fiscal_note_id' => null,
                    'tenant_id' => $tenantId,
                    'event_type' => 'inutilization',
                    'protocol_number' => $result->protocolNumber,
                    'description' => "Inutilização série {$request->input('serie')} números {$request->input('numero_inicial')} a {$request->input('numero_final')}",
                    'response_payload' => $result->rawResponse,
                    'status' => 'authorized',
                    'user_id' => $request->user()->id,
                ]);

                return ApiResponse::data($result->rawResponse, 200, ['message' => 'Numeração inutilizada com sucesso']);
            }

            return ApiResponse::message('Erro: '.$result->errorMessage, 422);

        } catch (\Exception $e) {
            Log::error('Inutilização failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro interno ao inutilizar numeração', 500);
        }
    }

    /**
     * Issue a Carta de Correção (CC-e) for an NF-e.
     */
    public function cartaCorrecao(CartaCorrecaoRequest $request, int $id): JsonResponse
    {
        $request->validated();
        $tenantId = $this->tenantId();
        $note = FiscalNote::forTenant($tenantId)->findOrFail($id);

        if (! $note->canCorrect()) {
            return ApiResponse::message('Carta de correção só pode ser emitida para NF-e autorizada', 409);
        }

        try {
            $referenceOrKey = $note->reference ?? $note->access_key;

            if (! $referenceOrKey) {
                return ApiResponse::message('Nota sem referência ou chave de acesso', 422);
            }

            $result = $this->provider->cartaCorrecao($referenceOrKey, $request->input('correcao'));

            $this->logEvent($note, 'correction', $request->user()->id, $result);

            if ($result->success) {
                return ApiResponse::data($result->rawResponse, 200, ['message' => 'Carta de correção emitida com sucesso']);
            }

            return ApiResponse::message('Erro: '.$result->errorMessage, 422);

        } catch (\Exception $e) {
            Log::error('CC-e failed', ['id' => $id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro interno ao emitir carta de correção', 500);
        }
    }

    /**
     * Download PDF (DANFE).
     */
    public function downloadPdf(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $note = FiscalNote::forTenant($tenantId)->findOrFail($id);

        if ($note->pdf_url) {
            return ApiResponse::data(['url' => $note->pdf_url]);
        }

        if ($note->pdf_path && Storage::exists($note->pdf_path)) {
            $pdf = Storage::get($note->pdf_path);

            return ApiResponse::data(['pdf_base64' => base64_encode($pdf)]);
        }

        $referenceOrKey = $note->reference ?? $note->access_key;
        if (! $referenceOrKey) {
            return ApiResponse::message('Nota sem referência ou chave de acesso', 422);
        }

        try {
            $pdf = $this->provider->downloadPdf($referenceOrKey);

            $path = $this->storeFiscalFile($note, $pdf, 'pdf');
            $note->update(['pdf_path' => $path]);

            return ApiResponse::data(['pdf_base64' => base64_encode($pdf)]);
        } catch (\Exception $e) {
            Log::error('Fiscal PDF download failed', ['id' => $id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao baixar PDF', 500);
        }
    }

    /**
     * Download XML.
     */
    public function downloadXml(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $note = FiscalNote::forTenant($tenantId)->findOrFail($id);

        if ($note->xml_path && Storage::exists($note->xml_path)) {
            $xml = Storage::get($note->xml_path);

            return ApiResponse::data(['xml' => $xml]);
        }

        if ($note->xml_url) {
            return ApiResponse::data(['url' => $note->xml_url]);
        }

        $referenceOrKey = $note->reference ?? $note->access_key;
        if (! $referenceOrKey) {
            return ApiResponse::message('Nota sem referência ou chave de acesso', 422);
        }

        try {
            $xml = $this->provider->downloadXml($referenceOrKey);

            $path = $this->storeFiscalFile($note, $xml, 'xml');
            $note->update(['xml_path' => $path]);

            return ApiResponse::data(['xml' => $xml]);
        } catch (\Exception $e) {
            Log::error('Fiscal XML download failed', ['id' => $id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao baixar XML', 500);
        }
    }

    /**
     * List fiscal events for a note.
     */
    public function events(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $note = FiscalNote::forTenant($tenantId)->findOrFail($id);

        $events = $note->events()->with('user:id,name')->get();

        return ApiResponse::data($events);
    }

    /**
     * Get fiscal statistics/dashboard data.
     */
    public function stats(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $month = $request->input('month', now()->format('Y-m'));

        $baseQuery = FiscalNote::forTenant($tenantId)
            ->where('created_at', '>=', "{$month}-01")
            ->where('created_at', '<', Carbon::parse("{$month}-01")->addMonth()->format('Y-m-d'));

        return ApiResponse::data([
            'total' => (clone $baseQuery)->count(),
            'authorized' => (clone $baseQuery)->where('status', FiscalNote::STATUS_AUTHORIZED)->count(),
            'pending' => (clone $baseQuery)->whereIn('status', [FiscalNote::STATUS_PENDING, FiscalNote::STATUS_PROCESSING])->count(),
            'rejected' => (clone $baseQuery)->where('status', FiscalNote::STATUS_REJECTED)->count(),
            'cancelled' => (clone $baseQuery)->where('status', FiscalNote::STATUS_CANCELLED)->count(),
            'total_nfe' => (clone $baseQuery)->where('type', FiscalNote::TYPE_NFE)->where('status', FiscalNote::STATUS_AUTHORIZED)->count(),
            'total_nfse' => (clone $baseQuery)->where('type', FiscalNote::TYPE_NFSE)->where('status', FiscalNote::STATUS_AUTHORIZED)->count(),
            'total_amount' => (clone $baseQuery)->where('status', FiscalNote::STATUS_AUTHORIZED)->sum('total_amount'),
        ]);
    }

    // ─── Private helpers ─────────────────────────────────

    private function emitDocument(Request $request, string $type): JsonResponse
    {
        try {
            $user = $request->user();
            $tenantId = $this->tenantId();
            $tenant = Tenant::findOrFail($tenantId);

            $isNFe = $type === FiscalNote::TYPE_NFE;
            $items = $request->input($isNFe ? 'items' : 'services', []);

            $totalAmount = $isNFe
                ? collect($items)->reduce(fn (string $carry, array $item) => bcadd($carry, bcmul((string) ($item['quantity'] ?? 1), (string) ($item['unit_price'] ?? 0), 2), 2), '0')
                : collect($items)->reduce(fn (string $carry, array $s) => bcadd($carry, (string) ($s['amount'] ?? 0), 2), '0');

            // Phase 1: create note + allocate number inside transaction
            $note = DB::transaction(function () use ($type, $tenantId, $tenant, $isNFe, $items, $totalAmount, $request, $user) {
                $reference = FiscalNote::generateReference($type, $tenantId);

                $numberingService = new FiscalNumberingService;
                $numbering = $isNFe
                    ? $numberingService->nextNFeNumber($tenant)
                    : $numberingService->nextNFSeRpsNumber($tenant);

                return FiscalNote::create([
                    'tenant_id' => $tenantId,
                    'type' => $type,
                    'customer_id' => $request->input('customer_id'),
                    'work_order_id' => $request->input('work_order_id'),
                    'quote_id' => $request->input('quote_id'),
                    'status' => FiscalNote::STATUS_PENDING,
                    'provider' => config('services.fiscal.provider', 'focusnfe'),
                    'reference' => $reference,
                    'number' => $numbering['number'],
                    'series' => $numbering['series'],
                    'total_amount' => $totalAmount,
                    'nature_of_operation' => $request->input('nature_of_operation', $isNFe ? 'Venda de mercadoria' : null),
                    'cfop' => $request->input('cfop'),
                    'items_data' => $items,
                    'environment' => $tenant->fiscal_environment ?? 'homologation',
                    'created_by' => $user->id,
                ]);
            });

            // Phase 2: build payload and call external API OUTSIDE transaction
            if ($isNFe) {
                $builder = new NFeDataBuilder($tenant, $note, $items, [
                    'cfop' => $request->input('cfop'),
                    'payment_method' => $request->input('payment_method'),
                    'informacoes_complementares' => $request->input('informacoes_complementares'),
                ]);
                $payload = $builder->build();
            } else {
                $builder = new NFSeDataBuilder($tenant, $note, $items, [
                    'iss_rate' => $request->input('iss_rate'),
                    'iss_retained' => $request->input('iss_retained'),
                    'exigibilidade_iss' => $request->input('exigibilidade_iss'),
                    'natureza_tributacao' => $request->input('natureza_tributacao'),
                    'service_code' => $request->input('services.0.service_code'),
                    'municipal_service_code' => $request->input('services.0.municipal_service_code'),
                    'pis_rate' => $request->input('pis_rate'),
                    'cofins_rate' => $request->input('cofins_rate'),
                    'inss_rate' => $request->input('inss_rate'),
                    'ir_rate' => $request->input('ir_rate'),
                    'csll_rate' => $request->input('csll_rate'),
                    'informacoes_complementares' => $request->input('informacoes_complementares'),
                ]);
                $payload = $builder->build();
            }

            $payload['ref'] = $note->reference;

            $result = $isNFe
                ? $this->fiscalGateway->emitirNFe(NFeDTO::fromBuiltPayload($payload))
                : $this->provider->emitirNFSe($payload);

            $this->logEvent($note, 'emission', $user->id, $result);

            if (! $result->success && $this->isConnectionIssueMessage($result->errorMessage)) {
                $note->update(['status' => FiscalNote::STATUS_PENDING, 'contingency_mode' => true]);
                app(ContingencyService::class)->saveOffline($note, $payload);

                return ApiResponse::data($note->fresh(), 202, [
                    'message' => 'Provider fiscal indisponível. Nota salva em contingência para envio posterior.',
                    'success' => true,
                    'contingency' => true,
                ]);
            }

            // Phase 3: update note with result
            if ($result->success) {
                $updateData = [
                    'provider_id' => $result->providerId ?? $result->reference,
                    'access_key' => $result->accessKey,
                    'status' => $result->status === 'processing' ? FiscalNote::STATUS_PROCESSING : FiscalNote::STATUS_AUTHORIZED,
                    'protocol_number' => $result->protocolNumber,
                    'verification_code' => $result->verificationCode,
                    'pdf_url' => $result->pdfUrl,
                    'xml_url' => $result->xmlUrl,
                    'raw_response' => $result->rawResponse,
                ];

                if ($result->number) {
                    $updateData['number'] = $result->number;
                }
                if ($result->series) {
                    $updateData['series'] = $result->series;
                }

                if ($result->status !== 'processing') {
                    $updateData['issued_at'] = now();
                }

                $note->update($updateData);
            } else {
                $note->update([
                    'status' => FiscalNote::STATUS_REJECTED,
                    'error_message' => $result->errorMessage,
                    'raw_response' => $result->rawResponse,
                ]);
            }

            $statusCode = $result->success ? ($result->status === 'processing' ? 202 : 201) : 422;

            $message = $result->success
                ? ($result->status === 'processing' ? 'Nota em processamento na SEFAZ' : ($isNFe ? 'NF-e emitida com sucesso' : 'NFS-e emitida com sucesso'))
                : 'Erro na emissão';

            return ApiResponse::data($note->fresh(), $statusCode, ['message' => $message, 'success' => $result->success]);

        } catch (ValidationException $e) {
            return ApiResponse::message('Erro de validação', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            Log::error("{$type} emission failed", ['error' => $e->getMessage()]);

            if ($this->isConnectionError($e) && isset($note) && isset($payload)) {
                try {
                    $note->update(['status' => FiscalNote::STATUS_PENDING, 'contingency_mode' => true]);
                    $contingency = app(ContingencyService::class);
                    $contingency->saveOffline($note, $payload);

                    return ApiResponse::data($note->fresh(), 202, [
                        'message' => 'SEFAZ indisponível. Nota salva em contingência para envio posterior.',
                        'success' => true,
                        'contingency' => true,
                    ]);
                } catch (\Exception $contingencyEx) {
                    Log::error('Contingency save also failed', ['error' => $contingencyEx->getMessage()]);
                }
            }

            return ApiResponse::message('Erro interno ao emitir nota fiscal', 500);
        }
    }

    /**
     * Send fiscal note by email (PDF + XML).
     */
    public function sendEmail(SendFiscalEmailRequest $request, int $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $note = FiscalNote::forTenant($tenantId)->findOrFail($id);
        $request->validated();
        $emailService = new FiscalEmailService;
        $result = $emailService->send(
            $note,
            $request->input('email'),
            $request->input('message')
        );

        return ApiResponse::data($result, $result['success'] ? 200 : 422);
    }

    /**
     * Retransmit all contingency (offline) notes.
     */
    public function retransmitContingency(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $tenant = Tenant::findOrFail($tenantId);

        $contingency = app(ContingencyService::class);
        $result = $contingency->retransmitPending($tenant);

        return ApiResponse::data($result);
    }

    /**
     * Get contingency status (pending count).
     */
    public function contingencyStatus(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $contingency = app(ContingencyService::class);

        return ApiResponse::data([
            'pending_count' => $contingency->pendingCount($tenantId),
            'sefaz_available' => $contingency->isSefazAvailable(),
        ]);
    }

    /**
     * Retransmit a single contingency note.
     */
    public function retransmitSingleNote(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $note = FiscalNote::forTenant($tenantId)
            ->where('contingency_mode', true)
            ->findOrFail($id);

        $contingency = app(ContingencyService::class);
        $result = $contingency->retransmitNote($note);

        return ApiResponse::data($result, $result['success'] ? 200 : 422);
    }

    /**
     * Check if an exception is a connection error (SEFAZ down).
     */
    private function isConnectionError(\Exception $e): bool
    {
        $message = strtolower($e->getMessage());
        $connectionPatterns = $this->connectionErrorPatterns();

        foreach ($connectionPatterns as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function isConnectionIssueMessage(?string $message): bool
    {
        if (! is_string($message) || trim($message) === '') {
            return false;
        }

        $message = strtolower($message);
        $connectionPatterns = $this->connectionErrorPatterns();

        foreach ($connectionPatterns as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function connectionErrorPatterns(): array
    {
        $connectionPatterns = [
            'connection refused',
            'timeout',
            'could not resolve',
            'ssl',
            'curl error',
            '503',
            '502',
            'falha na comunicacao com o provedor fiscal',
            'provedor fiscal temporariamente indisponível',
            'provedor fiscal temporariamente indisponivel',
        ];

        return $connectionPatterns;
    }

    private function logEvent(FiscalNote $note, string $eventType, int $userId, $result): void
    {
        FiscalEvent::create([
            'fiscal_note_id' => $note->id,
            'tenant_id' => $note->tenant_id,
            'event_type' => $eventType,
            'protocol_number' => $result->protocolNumber ?? null,
            'description' => $this->eventDescription($eventType, $note),
            'response_payload' => $result->rawResponse,
            'status' => $result->success ? 'authorized' : 'rejected',
            'error_message' => $result->errorMessage,
            'user_id' => $userId,
        ]);
    }

    private function eventDescription(string $type, FiscalNote $note): string
    {
        return match ($type) {
            'emission' => "Emissão {$note->type} #{$note->id}",
            'cancellation' => "Cancelamento {$note->type} #{$note->number}",
            'correction' => "Carta de Correção {$note->type} #{$note->number}",
            'inutilization' => 'Inutilização de numeração',
            default => "Evento {$type}",
        };
    }

    private function storeFiscalFile(FiscalNote $note, string $content, string $extension): string
    {
        $year = now()->format('Y');
        $month = now()->format('m');
        $dir = "fiscal/{$note->tenant_id}/{$extension}/{$year}/{$month}";
        $filename = "{$note->type}_{$note->number}_{$note->id}.{$extension}";

        Storage::put("{$dir}/{$filename}", $content);

        return "{$dir}/{$filename}";
    }
}
