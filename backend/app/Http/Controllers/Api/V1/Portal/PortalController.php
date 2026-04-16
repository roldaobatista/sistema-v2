<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Events\ServiceCallCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\NewServiceCallRequest;
use App\Http\Requests\Portal\SubmitSignatureRequest;
use App\Http\Requests\Portal\UpdateQuoteStatusRequest;
use App\Http\Resources\PortalAccountReceivableResource;
use App\Http\Resources\PortalServiceCallResource;
use App\Http\Resources\QuoteResource;
use App\Http\Resources\WorkOrderResource;
use App\Http\Resources\WorkOrderSignatureResource;
use App\Models\AccountReceivable;
use App\Models\AuditLog;
use App\Models\ClientPortalUser;
use App\Models\Equipment;
use App\Models\Quote;
use App\Models\ServiceCall;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\QuoteService;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PortalController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(
        private readonly QuoteService $quoteService,
    ) {}

    private function portalUser(Request $request): ClientPortalUser
    {
        $user = $request->user();

        if (! $user instanceof ClientPortalUser || ! $user->tokenCan('portal:access')) {
            abort(403, 'Acesso restrito ao portal do cliente.');
        }

        return $user;
    }

    public function workOrders(Request $request)
    {
        $user = $this->portalUser($request);

        $workOrders = WorkOrder::where('tenant_id', $this->tenantId())
            ->where('customer_id', $user->customer_id)
            ->with(['customer:id,name,latitude,longitude', 'equipment', 'items', 'statusHistory'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return ApiResponse::paginated($workOrders, resourceClass: WorkOrderResource::class);
    }

    public function workOrderShow(Request $request, int $workOrderId)
    {
        $user = $this->portalUser($request);

        $workOrder = WorkOrder::where('tenant_id', $this->tenantId())
            ->where('customer_id', $user->customer_id)
            ->with([
                'customer:id,name',
                'items',
                'equipment',
                'equipmentsList:id,brand,model,serial_number,tag',
                'assignee:id,name',
                'technicians:id,name',
                'driver:id,name',
                'statusHistory.user:id,name',
                'attachments',
            ])
            ->findOrFail($workOrderId);

        return ApiResponse::data(new WorkOrderResource($workOrder));
    }

    public function quotes(Request $request)
    {
        $user = $this->portalUser($request);

        $quotes = Quote::where('tenant_id', $this->tenantId())
            ->where('customer_id', $user->customer_id)
            ->with([
                'seller:id,name',
                'equipments.equipment:id,brand,model,serial_number',
                'equipments.items.product:id,name',
                'equipments.items.service:id,name',
            ])
            ->orderByDesc('created_at')
            ->paginate(20);

        return ApiResponse::paginated($quotes, resourceClass: QuoteResource::class);
    }

    public function updateQuoteStatus(UpdateQuoteStatusRequest $request, int $id)
    {
        $user = $this->portalUser($request);

        $quote = Quote::where('tenant_id', $this->tenantId())
            ->where('customer_id', $user->customer_id)
            ->findOrFail($id);

        $validated = $request->validated();

        $status = $quote->status instanceof \BackedEnum ? $quote->status->value : (string) $quote->status;

        if ($status !== Quote::STATUS_SENT) {
            return ApiResponse::message('Este orcamento nao pode mais ser alterado.', 422);
        }

        if ($quote->isExpired()) {
            return ApiResponse::message('Este orcamento esta expirado.', 422);
        }

        if ($validated['action'] === 'approve') {
            $actor = $quote->seller ?: User::where('tenant_id', $quote->tenant_id)->orderBy('id')->first();
            $quote = $this->quoteService->approveQuote(
                $quote,
                $actor,
                [
                    'approval_channel' => 'portal',
                    'approved_by_name' => $user->name,
                    'approval_notes' => $validated['comments'] ?? null,
                ],
                "Orçamento {$quote->quote_number} aprovado pelo cliente via portal"
            );
        } else {
            $quote = DB::transaction(function () use ($quote, $validated, $user) {
                $quote->update([
                    'status' => Quote::STATUS_REJECTED,
                    'rejected_at' => now(),
                    'rejection_reason' => $validated['comments'] ?? null,
                    'approval_channel' => 'portal',
                    'approved_by_name' => $user->name,
                    'approval_notes' => $validated['comments'] ?? null,
                ]);

                AuditLog::log(
                    'status_changed',
                    "Orçamento {$quote->quote_number} rejeitado pelo cliente via portal",
                    $quote
                );

                return $quote->fresh();
            });
        }

        $updatedQuote = $quote->fresh();
        $updatedStatus = $updatedQuote->status instanceof \BackedEnum
            ? $updatedQuote->status->value
            : (string) $updatedQuote->status;

        return ApiResponse::data(
            new QuoteResource($updatedQuote),
            200,
            ['message' => "Orcamento {$updatedStatus} com sucesso."]
        );
    }

    public function financials(Request $request)
    {
        $user = $this->portalUser($request);

        $financials = AccountReceivable::where('tenant_id', $this->tenantId())
            ->where('customer_id', $user->customer_id)
            ->whereNotIn('status', [
                AccountReceivable::STATUS_PAID,
                AccountReceivable::STATUS_CANCELLED,
                AccountReceivable::STATUS_RENEGOTIATED,
            ])
            ->orderBy('due_date')
            ->paginate(max(1, min($request->integer('per_page', 20), 100)));

        return ApiResponse::paginated($financials, resourceClass: PortalAccountReceivableResource::class);
    }

    public function certificates(Request $request)
    {
        $user = $this->portalUser($request);
        $status = (string) $request->query('status', '');

        if (! Schema::hasTable('calibration_certificates')) {
            return ApiResponse::paginated($this->emptyPaginator($request, 20));
        }

        $query = DB::table('calibration_certificates')
            ->join('equipments', 'calibration_certificates.equipment_id', '=', 'equipments.id')
            ->where('calibration_certificates.tenant_id', $this->tenantId())
            ->where('equipments.customer_id', $user->customer_id)
            ->select(
                'calibration_certificates.id',
                'calibration_certificates.equipment_id',
                'calibration_certificates.number',
                'calibration_certificates.issued_at',
                'calibration_certificates.valid_until',
                'calibration_certificates.file_path',
                'equipments.brand',
                'equipments.model',
                'equipments.tag',
                'equipments.serial_number'
            );

        if ($status === 'valid') {
            $query->where('calibration_certificates.valid_until', '>', now()->addDays(30));
        } elseif ($status === 'expiring_soon') {
            $query->whereBetween('calibration_certificates.valid_until', [now(), now()->addDays(30)]);
        } elseif ($status === 'expired') {
            $query->where('calibration_certificates.valid_until', '<', now());
        }

        $paginator = $query
            ->orderByDesc('calibration_certificates.issued_at')
            ->paginate(max(1, min($request->integer('per_page', 20), 100)));

        $rawCertificates = $paginator->getCollection();

        // Batch-load measurement counts to avoid N+1 queries
        $equipmentIds = $rawCertificates->pluck('equipment_id')->filter()->unique()->values();
        $measurementCounts = [];
        if ($equipmentIds->isNotEmpty() && Schema::hasTable('calibration_readings') && Schema::hasTable('equipment_calibrations')) {
            $measurementCounts = DB::table('calibration_readings')
                ->join('equipment_calibrations', 'calibration_readings.equipment_calibration_id', '=', 'equipment_calibrations.id')
                ->whereIn('equipment_calibrations.equipment_id', $equipmentIds)
                ->select('equipment_calibrations.equipment_id', DB::raw('COUNT(*) as cnt'))
                ->groupBy('equipment_calibrations.equipment_id')
                ->pluck('cnt', 'equipment_id')
                ->toArray();
        }

        $paginator->setCollection(
            $rawCertificates->map(function (object $certificate) use ($measurementCounts): array {
                $equipmentName = trim(implode(' ', array_filter([
                    $certificate->brand ?? null,
                    $certificate->model ?? null,
                ])));

                return [
                    'id' => $certificate->id,
                    'certificate_number' => $certificate->number,
                    'equipment_name' => $equipmentName !== '' ? $equipmentName : ($certificate->serial_number ?? 'Equipamento'),
                    'equipment_tag' => $certificate->tag ?: $certificate->serial_number,
                    'calibration_date' => $certificate->issued_at,
                    'next_calibration_date' => $certificate->valid_until,
                    'status' => $this->mapPortalCertificateStatus($certificate->valid_until),
                    'download_url' => $certificate->file_path ? asset('storage/'.ltrim($certificate->file_path, '/')) : null,
                    'measurements_count' => (int) ($measurementCounts[$certificate->equipment_id] ?? 0),
                ];
            })
                ->values()
        );

        return ApiResponse::paginated($paginator);
    }

    public function equipment(Request $request)
    {
        $user = $this->portalUser($request);

        $equipment = Equipment::query()
            ->where('tenant_id', $this->tenantId())
            ->where('customer_id', $user->customer_id)
            ->orderBy('brand')
            ->orderBy('model')
            ->select([
                'id',
                'brand',
                'model',
                'serial_number',
                'tag',
                'location',
                'last_calibration_at',
                'next_calibration_at',
            ])
            ->paginate(max(1, min($request->integer('per_page', 20), 100)));

        $equipment->getCollection()->transform(fn (Equipment $item): array => [
            'id' => $item->id,
            'brand' => $item->brand,
            'model' => $item->model,
            'serial_number' => $item->serial_number,
            'tag' => $item->tag,
            'location' => $item->location,
            'next_calibration_at' => $item->next_calibration_at?->toDateString(),
            'last_os_date' => null,
            'os_count' => 0,
            'calibration_status' => $this->mapPortalEquipmentStatus($item->calibration_status),
        ]);

        return ApiResponse::paginated($equipment);
    }

    public function newServiceCall(NewServiceCallRequest $request)
    {
        $user = $this->portalUser($request);
        $validated = $request->validated();

        if (! empty($validated['equipment_id'])) {
            $exists = Equipment::where('tenant_id', $this->tenantId())
                ->where('id', $validated['equipment_id'])
                ->where('customer_id', $user->customer_id)
                ->exists();

            if (! $exists) {
                return ApiResponse::message('Equipamento invalido.', 403);
            }
        }

        try {
            $fallbackUserId = User::query()
                ->where('tenant_id', $this->tenantId())
                ->orderBy('id')
                ->value('id');

            $serviceCall = DB::transaction(function () use ($user, $validated, $fallbackUserId) {
                $serviceCall = ServiceCall::create([
                    'tenant_id' => $this->tenantId(),
                    'call_number' => ServiceCall::nextNumber($this->tenantId()),
                    'customer_id' => $user->customer_id,
                    'created_by' => $fallbackUserId,
                    'status' => ServiceCall::STATUS_OPEN,
                    'priority' => $validated['priority'] ?? 'normal',
                    'observations' => $validated['description'],
                ]);

                if (! empty($validated['equipment_id'])) {
                    $serviceCall->equipments()->attach($validated['equipment_id'], [
                        'observations' => $validated['description'],
                    ]);
                }

                return $serviceCall;
            });

            // Processar anexos se enviados
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    if (! $file->isValid()) {
                        continue;
                    }
                    $path = $file->store("service-calls/{$serviceCall->id}/attachments", 'public');
                    $serviceCall->attachments()->create([
                        'tenant_id' => $this->tenantId(),
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'file_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                }
            }

            if ($fallbackUserId) {
                $eventUser = User::query()->find($fallbackUserId);
                event(new ServiceCallCreated($serviceCall, $eventUser));
            }

            return ApiResponse::data(
                new PortalServiceCallResource($serviceCall->load('equipments:id,brand,model,serial_number,next_calibration_at')),
                201
            );
        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::message('Erro ao abrir chamado. Tente novamente.', 500);
        }
    }

    public function workOrderPhotos(Request $request, int $workOrderId)
    {
        $user = $this->portalUser($request);

        $workOrder = WorkOrder::where('tenant_id', $this->tenantId())
            ->where('customer_id', $user->customer_id)
            ->findOrFail($workOrderId);

        $attachments = $workOrder->attachments()->get(['id', 'file_name', 'file_path', 'file_type', 'created_at']);
        $photoChecklist = $workOrder->photo_checklist ?? [];

        return ApiResponse::data([
            'attachments' => $attachments,
            'photo_checklist' => $photoChecklist,
        ]);
    }

    public function submitSignature(SubmitSignatureRequest $request, int $workOrderId)
    {
        $user = $this->portalUser($request);

        $validated = $request->validated();

        $workOrder = WorkOrder::where('tenant_id', $this->tenantId())
            ->where('customer_id', $user->customer_id)
            ->findOrFail($workOrderId);

        $signature = $workOrder->signatures()->create([
            'tenant_id' => $workOrder->tenant_id,
            'signer_name' => $validated['signer_name'],
            'signature_data' => $validated['signature_data'],
            'signer_type' => 'customer',
            'signed_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return ApiResponse::data(
            new WorkOrderSignatureResource($signature),
            201,
            ['message' => 'Assinatura registrada com sucesso']
        );
    }

    private function mapPortalCertificateStatus(mixed $validUntil): string
    {
        if (! $validUntil) {
            return 'draft';
        }

        $date = Carbon::parse($validUntil);

        if ($date->isPast()) {
            return 'expired';
        }

        if ($date->lte(now()->addDays(30))) {
            return 'expiring_soon';
        }

        return 'valid';
    }

    public function knowledgeBase(Request $request)
    {
        if (! Schema::hasTable('knowledge_base_articles')) {
            return ApiResponse::paginated($this->emptyPaginator($request, 15));
        }

        $tenantId = $this->tenantId();

        $paginator = DB::table('knowledge_base_articles')
            ->where('tenant_id', $tenantId)
            ->where('is_published', true)
            ->orderByDesc('created_at')
            ->paginate(max(1, min($request->integer('per_page', 15), 100)));

        return ApiResponse::paginated($paginator);
    }

    public function nps(Request $request)
    {
        if (! Schema::hasTable('satisfaction_surveys')) {
            return ApiResponse::paginated($this->emptyPaginator($request, 15));
        }

        $tenantId = $this->tenantId();

        $paginator = DB::table('satisfaction_surveys')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->paginate(max(1, min($request->integer('per_page', 15), 100)));

        return ApiResponse::paginated($paginator);
    }

    private function mapPortalEquipmentStatus(?string $status): ?string
    {
        return match ($status) {
            'vencida' => 'expired',
            'vence_em_breve' => 'expiring',
            'em_dia' => 'valid',
            default => null,
        };
    }

    private function emptyPaginator(Request $request, int $defaultPerPage): LengthAwarePaginator
    {
        $perPage = max(1, min($request->integer('per_page', $defaultPerPage), 100));
        $page = max($request->integer('page', 1), 1);

        return new LengthAwarePaginator(
            [],
            0,
            $perPage,
            $page,
            ['path' => $request->url()]
        );
    }
}
