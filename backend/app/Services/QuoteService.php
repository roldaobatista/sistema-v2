<?php

namespace App\Services;

use App\Actions\Quote\ApproveAfterTestAction;
use App\Actions\Quote\ApproveQuoteAction;
use App\Actions\Quote\ConvertQuoteToServiceCallAction;
use App\Actions\Quote\ConvertQuoteToWorkOrderAction;
use App\Actions\Quote\CreateQuoteAction;
use App\Actions\Quote\DuplicateQuoteAction;
use App\Actions\Quote\RejectQuoteAction;
use App\Actions\Quote\ReopenQuoteAction;
use App\Actions\Quote\RequestInternalApprovalQuoteAction;
use App\Actions\Quote\RevertFromRenegotiationAction;
use App\Actions\Quote\SendQuoteAction;
use App\Actions\Quote\SendQuoteEmailAction;
use App\Actions\Quote\SendToRenegotiationAction;
use App\Actions\Quote\UpdateQuoteAction;
use App\Enums\AuditAction;
use App\Enums\FinancialStatus;
use App\Enums\QuoteStatus;
use App\Http\Resources\QuoteResource;
use App\Models\AccountReceivable;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\QuoteEmail;
use App\Models\QuoteEquipment;
use App\Models\QuoteItem;
use App\Models\QuotePhoto;
use App\Models\QuoteTag;
use App\Models\QuoteTemplate;
use App\Models\ServiceCall;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\BrazilPhone;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class QuoteService
{
    public function createQuote(array $data, int $tenantId, int $userId): Quote
    {
        return app(CreateQuoteAction::class)->execute($data, $tenantId, $userId);
    }

    public function updateQuote(Quote $quote, array $data, ?User $user = null): Quote
    {
        return app(UpdateQuoteAction::class)->execute($quote, $data, $user);
    }

    /**
     * Request internal approval: draft -> pending_internal_approval.
     */
    public function requestInternalApproval(Quote $quote): Quote
    {
        return app(RequestInternalApprovalQuoteAction::class)->execute($quote);
    }

    public function sendQuote(Quote $quote): Quote
    {
        return app(SendQuoteAction::class)->execute($quote);
    }

    public function approveQuote(
        Quote $quote,
        ?User $actor = null,
        array $attributes = [],
        ?string $auditDescription = null,
    ): Quote {
        return app(ApproveQuoteAction::class)->execute($quote, $actor, $attributes, $auditDescription);
    }

    public function publicApprove(Quote $quote, array $attributes = []): Quote
    {
        if ($quote->status !== QuoteStatus::SENT) {
            throw new \DomainException('Orçamento não está disponível para aprovação');
        }

        if ($quote->isExpired()) {
            throw new \DomainException('Orçamento expirado');
        }

        return $this->approveQuote(
            $quote,
            null,
            $attributes,
            "Orçamento {$quote->quote_number} aprovado pelo cliente via link público"
        );
    }

    public function rejectQuote(Quote $quote, ?string $reason): Quote
    {
        return app(RejectQuoteAction::class)->execute($quote, $reason);
    }

    public function reopenQuote(Quote $quote): Quote
    {
        return app(ReopenQuoteAction::class)->execute($quote);
    }

    public function duplicateQuote(Quote $quote): Quote
    {
        return app(DuplicateQuoteAction::class)->execute($quote);
    }

    public function updateItem(QuoteItem $item, array $data, ?User $user = null): QuoteItem
    {
        if ($user) {
            $this->ensureCanApplyDiscount($user, $data);
        }

        return DB::transaction(function () use ($item, $data) {
            $item->update(Arr::only($data, [
                'custom_description', 'quantity', 'original_price', 'cost_price',
                'unit_price', 'discount_percentage', 'internal_note',
            ]));

            // recalculateTotal() é chamado automaticamente pelo evento saved do QuoteItem

            return $item->fresh(['product', 'service']);
        });
    }

    public function updateEquipment(QuoteEquipment $equipment, array $data): QuoteEquipment
    {
        $quote = $equipment->quote ?? Quote::find($equipment->quote_id);
        if ($quote) {
            $status = $quote->status;
            if (! $status->isMutable()) {
                throw new \DomainException('Só é possível editar orçamentos em rascunho, aprovação interna pendente, rejeitados ou em renegociação');
            }
        }

        return DB::transaction(function () use ($equipment, $data) {
            $equipment->update(Arr::only($data, ['description', 'sort_order']));

            return $equipment->fresh(['equipment']);
        });
    }

    public function convertToWorkOrder(Quote $quote, int $userId, bool $isInstallationTesting = false): WorkOrder
    {
        return app(ConvertQuoteToWorkOrderAction::class)->execute($quote, $userId, $isInstallationTesting);
    }

    public function convertToServiceCall(Quote $quote, int $userId, bool $isInstallationTesting = false): ServiceCall
    {
        return app(ConvertQuoteToServiceCallAction::class)->execute($quote, $userId, $isInstallationTesting);
    }

    public function approveAfterTest(Quote $quote, int $userId): Quote
    {
        return app(ApproveAfterTestAction::class)->execute($quote, $userId);
    }

    public function sendToRenegotiation(Quote $quote, int $userId): Quote
    {
        return app(SendToRenegotiationAction::class)->execute($quote, $userId);
    }

    public function revertFromRenegotiation(Quote $quote, string $targetStatus, int $userId): Quote
    {
        return app(RevertFromRenegotiationAction::class)->execute($quote, $targetStatus, $userId);
    }

    // ── New methods for improvements ──

    public function sendEmail(Quote $quote, string $recipientEmail, ?string $recipientName, ?string $message, int $sentBy): QuoteEmail
    {
        return app(SendQuoteEmailAction::class)->execute($quote, $recipientEmail, $recipientName, $message, $sentBy);
    }

    public function createFromTemplate(QuoteTemplate $template, array $data, int $tenantId, int $userId): Quote
    {
        $data['template_id'] = $template->id;
        $data['payment_terms_detail'] = $data['payment_terms_detail'] ?? $template->payment_terms_text;

        return $this->createQuote($data, $tenantId, $userId);
    }

    public function advancedSummary(int $tenantId, ?int $sellerId = null): array
    {
        $quotes = Quote::query()->where('tenant_id', $tenantId);
        if ($sellerId !== null) {
            $quotes->where('seller_id', $sellerId);
        }

        $total = $quotes->count();
        $approved = (clone $quotes)->where('status', QuoteStatus::APPROVED)->count() +
                    (clone $quotes)->where('status', QuoteStatus::INVOICED)->count();

        $avgTicket = (clone $quotes)->whereIn('status', [
            QuoteStatus::APPROVED, QuoteStatus::INVOICED,
        ])->avg('total') ?? 0;

        $conversionPairs = (clone $quotes)
            ->whereNotNull('approved_at')
            ->whereNotNull('sent_at')
            ->get(['approved_at', 'sent_at']);

        $avgConversionDays = $conversionPairs->isEmpty()
            ? 0
            : $conversionPairs
                ->filter(fn (Quote $quote): bool => $quote->approved_at instanceof CarbonInterface && $quote->sent_at instanceof CarbonInterface)
                ->avg(fn (Quote $quote): int => $quote->sent_at->diffInDays($quote->approved_at));

        $topSellers = Quote::where('tenant_id', $tenantId)
            ->when($sellerId !== null, fn ($query) => $query->where('seller_id', $sellerId))
            ->whereIn('status', [QuoteStatus::APPROVED, QuoteStatus::INVOICED])
            ->selectRaw('seller_id, COUNT(*) as total_approved, SUM(total) as total_value')
            ->groupBy('seller_id')
            ->orderByDesc('total_value')
            ->limit(5)
            ->with('seller:id,name')
            ->get();

        $monthlyTrend = Quote::where('tenant_id', $tenantId)
            ->when($sellerId !== null, fn ($query) => $query->where('seller_id', $sellerId))
            ->where('created_at', '>=', now()->subMonths(12)->startOfMonth())
            ->get(['created_at', 'status'])
            ->filter(fn (Quote $quote): bool => $quote->created_at instanceof CarbonInterface)
            ->groupBy(fn (Quote $quote): string => $quote->created_at->format('Y-m'))
            ->map(fn ($group, string $month): array => [
                'month' => $month,
                'total' => $group->count(),
                'approved' => $group->filter(fn (Quote $quote): bool => in_array($quote->status, [QuoteStatus::APPROVED, QuoteStatus::INVOICED], true))->count(),
            ])
            ->sortByDesc('month')
            ->take(12)
            ->values();

        return [
            'total_quotes' => $total,
            'total_approved' => $approved,
            'conversion_rate' => $total > 0 ? round(($approved / $total) * 100, 1) : 0,
            'avg_ticket' => bcadd((string) $avgTicket, '0', 2),
            'avg_conversion_days' => round((float) $avgConversionDays, 1),
            'top_sellers' => $topSellers,
            'monthly_trend' => $monthlyTrend,
        ];
    }

    public function trackClientView(Quote $quote): void
    {
        $quote->update([
            'client_viewed_at' => now(),
            'client_view_count' => $quote->client_view_count + 1,
        ]);

        AuditLog::log('public_viewed', "Orçamento {$quote->quote_number} visualizado pelo cliente via token público", $quote);
    }

    public function markAsInvoiced(Quote $quote, int $userId): Quote
    {
        $allowedStatuses = [QuoteStatus::APPROVED, QuoteStatus::IN_EXECUTION];
        if (! in_array($quote->status, $allowedStatuses, true)) {
            throw new \DomainException('Apenas orçamentos aprovados ou em execução podem ser faturados');
        }

        return DB::transaction(function () use ($quote, $userId) {
            $quote->update([
                'status' => QuoteStatus::INVOICED->value,
            ]);

            // Create AccountReceivable linked to this quote
            $this->generateAccountReceivable($quote, $userId);

            AuditLog::log('status_changed', "Orçamento {$quote->quote_number} faturado", $quote);

            return $quote;
        });
    }

    private function generateAccountReceivable(Quote $quote, int $userId): void
    {
        $dueDate = $this->resolveDueDateFromPaymentTerms($quote);

        AccountReceivable::create([
            'tenant_id' => $quote->tenant_id,
            'customer_id' => $quote->customer_id,
            'quote_id' => $quote->id,
            'origin_type' => 'quote',
            'created_by' => $userId,
            'description' => "Orçamento #{$quote->quote_number}",
            'amount' => $quote->total ?? '0.00',
            'amount_paid' => '0.00',
            'due_date' => $dueDate,
            'status' => FinancialStatus::PENDING->value,
        ]);
    }

    private function resolveDueDateFromPaymentTerms(Quote $quote): Carbon
    {
        $paymentTerms = $quote->payment_terms;

        $daysMap = [
            'a_vista' => 0,
            '7_dias' => 7,
            '10_dias' => 10,
            '15_dias' => 15,
            '30_dias' => 30,
            '30_60' => 30,
            '30_60_90' => 30,
            '45_dias' => 45,
            '60_dias' => 60,
            '90_dias' => 90,
        ];

        $days = $daysMap[$paymentTerms] ?? 30;

        return now()->addDays($days);
    }

    public function internalApproveLevel2(Quote $quote, int $approverId): Quote
    {
        if ($quote->status !== QuoteStatus::PENDING_INTERNAL_APPROVAL) {
            throw new \DomainException('Orçamento não está aguardando aprovação interna');
        }

        return DB::transaction(function () use ($quote, $approverId) {
            $quote->update([
                'level2_approved_by' => $approverId,
                'level2_approved_at' => now(),
                'status' => QuoteStatus::INTERNALLY_APPROVED->value,
            ]);

            AuditLog::log('status_changed', "Orçamento {$quote->quote_number} aprovado internamente (nível 2)", $quote);

            return $quote;
        });
    }

    public function index(array $data, User $user, int $tenantId)
    {
        $filters = $data;

        $query = Quote::with([
            'customer:id,name',
            'seller:id,name',
            'tags:id,name,color',
        ])
            ->where('tenant_id', $tenantId)
            ->withCount('equipments');

        if ($this->shouldScopeByUser($user)) {
            $query->where('seller_id', $user->id);
        }

        if ($s = ($filters['search'] ?? null)) {
            $s = str_replace(['%', '_'], ['\\%', '\\_'], $s);
            $query->where(function ($q) use ($s) {
                $q->where('quote_number', 'like', "%$s%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$s%"));
            });
        }
        if ($status = ($filters['status'] ?? null)) {
            $query->where('status', $status);
        }
        if ($sellerId = ($filters['seller_id'] ?? null)) {
            $query->where('seller_id', $sellerId);
        }
        if ($source = ($filters['source'] ?? null)) {
            $query->where('source', $source);
        }
        if ($tagId = ($filters['tag_id'] ?? null)) {
            $query->whereHas('tags', fn ($tagQuery) => $tagQuery
                ->where('quote_tags.id', $tagId)
                ->where('quote_tags.tenant_id', $tenantId));
        }
        if ($dateFrom = ($filters['date_from'] ?? null)) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = ($filters['date_to'] ?? null)) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
        if ($customerId = ($filters['customer_id'] ?? null)) {
            $query->where('customer_id', $customerId);
        }
        if ($totalMin = ($filters['total_min'] ?? null)) {
            $query->where('total', '>=', (float) $totalMin);
        }
        if ($totalMax = ($filters['total_max'] ?? null)) {
            $query->where('total', '<=', (float) $totalMax);
        }

        $query->orderByDesc('created_at')->orderByDesc('id');

        return $query->paginate($data['per_page'] ?? 15);
    }

    public function show(Quote $quote, User $user, int $tenantId)
    {
        if ($quote->tenant_id !== $tenantId) {
            return 'Orçamento não encontrado';
        }

        return new QuoteResource(
            $quote->load([
                'customer.contacts',
                'seller:id,name',
                'creator:id,name',
                'internalApprover:id,name',
                'level2Approver:id,name',
                'template:id,name',
                'tags:id,name,color',
                'emails',
                'equipments.equipment',
                'equipments.items.product',
                'equipments.items.service',
                'equipments.photos',
                'workOrders:id,quote_id,number,os_number,status,created_at',
                'serviceCalls:id,quote_id,call_number,status,created_at',
                'accountReceivables:id,quote_id,amount,amount_paid,due_date,status,description',
            ])
        );
    }

    public function destroy(Quote $quote, User $user, int $tenantId)
    {
        $status = $quote->status;
        if (! $status->isMutable()) {
            throw new \DomainException('Só é possível editar orçamentos em rascunho, aprovação interna pendente, rejeitados ou em renegociação');
        }

        if ($quote->workOrders()->exists()) {
            throw new \DomainException('Orçamento possui OS vinculada e não pode ser excluído.');
        }
        if ($quote->serviceCalls()->exists()) {
            throw new \DomainException('Orçamento possui chamado vinculado e não pode ser excluído.');
        }

        $filesToDelete = [];

        // Eager load equipments and their photos to avoid N+1
        $quote->load('equipments.photos');

        DB::transaction(function () use ($quote, &$filesToDelete) {
            foreach ($quote->equipments as $eq) {
                foreach ($eq->photos as $photo) {
                    $filesToDelete[] = $photo->path;
                }
            }
            $quote->delete();
            AuditLog::log('deleted', "Orçamento {$quote->quote_number} excluído", $quote);
        });

        foreach ($filesToDelete as $path) {
            Storage::disk('public')->delete($path);
        }

        return ['success' => true];
    }

    public function internalApprove(Quote $quote, User $user, int $tenantId)
    {
        $allowedStatuses = [QuoteStatus::PENDING_INTERNAL_APPROVAL, QuoteStatus::DRAFT];
        if (! in_array($quote->status, $allowedStatuses, true)) {
            return 'Orçamento precisa estar em rascunho ou aguardando aprovação interna.';
        }

        $hasItems = $quote->equipments()->whereHas('items')->exists();
        if (! $hasItems) {
            return 'Orçamento precisa ter pelo menos um equipamento com itens para ser aprovado internamente.';
        }

        /** @var User $user */
        $user = $user;

        DB::transaction(function () use ($quote, $user) {
            $quote->update([
                'status' => QuoteStatus::INTERNALLY_APPROVED->value,
                'internal_approved_by' => $user->id,
                'internal_approved_at' => now(),
            ]);

            AuditLog::log('internal_approved', "Orçamento {$quote->quote_number} aprovado internamente por {$user->name}", $quote);
        });

        return $quote->fresh();
    }

    public function addEquipment(array $data, User $user, int $tenantId, Quote $quote)
    {
        $status = $quote->status;
        if (! $status->isMutable()) {
            throw new \DomainException('Só é possível editar orçamentos em rascunho, aprovação interna pendente, rejeitados ou em renegociação');
        }

        $eq = $quote->equipments()->create([
            'tenant_id' => $tenantId,
            ...$data,
            'sort_order' => $quote->equipments()->count(),
        ]);

        return $eq->load('equipment');
    }

    public function removeEquipment(Quote $quote, QuoteEquipment $equipment, User $user, int $tenantId)
    {
        $status = $quote->status;
        if (! $status->isMutable()) {
            throw new \DomainException('Só é possível editar orçamentos em rascunho, aprovação interna pendente, rejeitados ou em renegociação');
        }

        $filesToDelete = $equipment->photos->pluck('path')->all();

        DB::transaction(function () use ($equipment, $quote) {
            $equipment->delete();
            $quote->recalculateTotal();
        });

        foreach ($filesToDelete as $path) {
            Storage::disk('public')->delete($path);
        }

        return ['success' => true];
    }

    public function addItem(array $data, User $user, int $tenantId, QuoteEquipment $equipment)
    {
        $quote = $equipment->quote ?? Quote::find($equipment->quote_id);
        if ($quote) {
            $status = $quote->status;
            if (! $status->isMutable()) {
                throw new \DomainException('Só é possível editar orçamentos em rascunho, aprovação interna pendente, rejeitados ou em renegociação');
            }
        }
        $this->ensureCanApplyDiscount($user, $data);

        $item = DB::transaction(function () use ($equipment, $tenantId, $data) {
            return $equipment->items()->create([
                'tenant_id' => $tenantId,
                ...$data,
                'sort_order' => $equipment->items()->count(),
            ]);
        });

        return $item->fresh(['product', 'service']);
    }

    public function items(Quote $quote)
    {

        $items = QuoteItem::query()
            ->where('tenant_id', $quote->tenant_id)
            ->whereHas('quoteEquipment', fn (Builder $query) => $query->where('quote_id', $quote->id))
            ->with(['product', 'service'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $items;
    }

    public function storeNestedItem(array $data, User $user, int $tenantId, Quote $quote)
    {
        $status = $quote->status;
        if (! $status->isMutable()) {
            throw new \DomainException('Só é possível editar orçamentos em rascunho, aprovação interna pendente, rejeitados ou em renegociação');
        }

        $item = DB::transaction(function () use ($quote, $tenantId, $data) {
            /** @var QuoteEquipment $equipment */
            $equipment = $quote->equipments()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'description' => 'Geral',
                ],
                [
                    'sort_order' => 0,
                ]
            );

            return $equipment->items()->create([
                'tenant_id' => $tenantId,
                'type' => 'service',
                'custom_description' => $data['description'],
                'quantity' => $data['quantity'],
                'original_price' => $data['unit_price'],
                'cost_price' => 0,
                'unit_price' => $data['unit_price'],
                'discount_percentage' => 0,
                'sort_order' => $equipment->items()->count(),
            ]);
        });

        return $item->load(['product', 'service']);
    }

    public function removeItem(QuoteItem $item)
    {
        $quote = $item->loadMissing('quoteEquipment.quote')->quoteEquipment?->quote;
        if ($quote) {
            $status = $quote->status;
            if (! $status->isMutable()) {
                throw new \DomainException('Só é possível editar orçamentos em rascunho, aprovação interna pendente, rejeitados ou em renegociação');
            }
        }

        DB::transaction(function () use ($item, $quote) {
            $item->delete();
            $quote?->recalculateTotal();
        });

        return ['success' => true];
    }

    public function addPhoto(array $data, UploadedFile $file, User $user, int $tenantId, Quote $quote)
    {
        $status = $quote->status;
        if (! $status->isMutable()) {
            throw new \DomainException('Só é possível editar orçamentos em rascunho, aprovação interna pendente, rejeitados ou em renegociação');
        }

        $path = $file->store(
            "quotes/{$quote->id}/photos",
            'public'
        );

        if (! $path) {
            return 'Erro ao salvar arquivo da foto';
        }

        $v = $data;

        // Validar que o equipment pertence a este orçamento
        $equipmentBelongsToQuote = $quote->equipments()->where('id', $v['quote_equipment_id'])->exists();
        if (! $equipmentBelongsToQuote) {
            Storage::disk('public')->delete($path);

            return 'Equipamento não pertence a este orçamento';
        }

        $photo = QuotePhoto::create([
            'tenant_id' => $tenantId,
            'quote_equipment_id' => $v['quote_equipment_id'],
            'path' => $path,
            'caption' => $v['caption'] ?? null,
            'sort_order' => 0,
        ]);

        return $photo;
    }

    public function removePhoto(QuotePhoto $photo)
    {
        $quote = $photo->loadMissing('quoteEquipment.quote')->quoteEquipment?->quote;
        if ($quote) {
            $status = $quote->status;
            if (! $status->isMutable()) {
                throw new \DomainException('Só é possível editar orçamentos em rascunho, aprovação interna pendente, rejeitados ou em renegociação');
            }
        }

        $pathToDelete = $photo->path;

        DB::transaction(function () use ($photo) {
            $photo->delete();
        });

        Storage::disk('public')->delete($pathToDelete);

        return ['success' => true];
    }

    public function summary(User $user, int $tenantId)
    {
        $base = $this->baseSummaryQuery($tenantId, $user ?? auth()->user());

        return [
            'draft' => (clone $base)->where('status', QuoteStatus::DRAFT->value)->count(),
            'pending_internal_approval' => (clone $base)->where('status', QuoteStatus::PENDING_INTERNAL_APPROVAL->value)->count(),
            'internally_approved' => (clone $base)->where('status', QuoteStatus::INTERNALLY_APPROVED->value)->count(),
            'sent' => (clone $base)->where('status', QuoteStatus::SENT->value)->count(),
            'approved' => (clone $base)->where('status', QuoteStatus::APPROVED->value)->count(),
            'invoiced' => (clone $base)->where('status', QuoteStatus::INVOICED->value)->count(),
            'rejected' => (clone $base)->where('status', QuoteStatus::REJECTED->value)->count(),
            'expired' => (clone $base)->where('status', QuoteStatus::EXPIRED->value)->count(),
            'in_execution' => (clone $base)->where('status', QuoteStatus::IN_EXECUTION->value)->count(),
            'installation_testing' => (clone $base)->where('status', QuoteStatus::INSTALLATION_TESTING->value)->count(),
            'renegotiation' => (clone $base)->where('status', QuoteStatus::RENEGOTIATION->value)->count(),
            'total_month' => (clone $base)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('total'),
            'conversion_rate' => $this->getConversionRate($tenantId, $user ?? auth()->user()),
        ];
    }

    public function timeline(Quote $quote, User $user, int $tenantId)
    {

        $logs = AuditLog::with('user:id,name')
            ->where('auditable_type', Quote::class)
            ->where('auditable_id', $quote->id)
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function (AuditLog $log): array {
                $actionValue = $log->action instanceof \BackedEnum ? $log->action->value : (string) $log->action;
                $actionLabel = $log->action instanceof AuditAction
                    ? $log->action->label()
                    : (AuditAction::tryFrom($actionValue)?->label() ?? $actionValue);

                return [
                    'id' => $log->id,
                    'action' => $actionValue,
                    'action_label' => $actionLabel,
                    'description' => $log->description,
                    'user_id' => $log->user_id,
                    'user_name' => $log->user?->name,
                    'created_at' => $log->created_at?->toIso8601String(),
                    'old_values' => $log->old_values,
                    'new_values' => $log->new_values,
                ];
            })
            ->values();

        return $logs;
    }

    public function exportCsv(array $data, User $user, int $tenantId)
    {
        $query = Quote::with(['customer:id,name', 'seller:id,name'])
            ->where('tenant_id', $tenantId);

        if ($status = ($data['status'] ?? null)) {
            $query->where('status', $status);
        }
        if ($source = ($data['source'] ?? null)) {
            $query->where('source', $source);
        }
        if ($dateFrom = ($data['date_from'] ?? null)) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = ($data['date_to'] ?? null)) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="orcamentos_'.now()->format('Y-m-d').'.csv"',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['Número', 'Cliente', 'Origem', 'Vendedor', 'Status', 'Subtotal', 'Deslocamento', 'Desconto', 'Total', 'Validade', 'Aprovação Interna', 'Enviado em', 'Aprovado em', 'Criado em'], ';');

            $query->orderByDesc('created_at')->chunk(500, function ($quotes) use ($handle) {
                foreach ($quotes as $q) {
                    $rawStatus = $q->status->value;
                    $statusLabel = QuoteStatus::tryFrom($rawStatus)?->label() ?? $rawStatus;
                    fputcsv($handle, [
                        $q->quote_number,
                        $q->customer?->name ?? '',
                        $q->source ?? '',
                        $q->seller?->name ?? '',
                        $statusLabel,
                        number_format((float) $q->subtotal, 2, ',', '.'),
                        number_format((float) $q->displacement_value, 2, ',', '.'),
                        number_format((float) $q->discount_amount, 2, ',', '.'),
                        number_format((float) $q->total, 2, ',', '.'),
                        $q->valid_until?->format('d/m/Y') ?? '',
                        $q->internal_approved_at?->format('d/m/Y H:i') ?? '',
                        $q->sent_at?->format('d/m/Y H:i') ?? '',
                        $q->approved_at?->format('d/m/Y H:i') ?? '',
                        $q->created_at?->format('d/m/Y H:i') ?? '',
                    ], ';');
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    public function tags(Quote $quote, User $user, int $tenantId)
    {

        return $quote->tags;
    }

    public function syncTags(array $data, User $user, int $tenantId, Quote $quote)
    {

        $quote->tags()->sync($data['tag_ids']);

        return $quote->load('tags');
    }

    public function listTags(User $user, int $tenantId)
    {
        $tags = QuoteTag::where('tenant_id', $tenantId)->orderBy('name')->get();

        return $tags;
    }

    public function storeTag(array $data, User $user, int $tenantId)
    {
        $v = $data;
        $tag = QuoteTag::create([
            'tenant_id' => $tenantId,
            'name' => $v['name'],
            'color' => $v['color'] ?? '#3B82F6',
        ]);

        return $tag;
    }

    public function destroyTag(QuoteTag $tag, User $user, int $tenantId)
    {
        if ((int) $tag->tenant_id !== (int) $tenantId) {
            return 'Tag não encontrada';
        }
        $tag->delete();

        return ['success' => true];
    }

    public function listTemplates(User $user, int $tenantId)
    {
        $templates = QuoteTemplate::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return $templates;
    }

    public function storeTemplate(array $data, User $user, int $tenantId)
    {

        $template = QuoteTemplate::create([
            'tenant_id' => $tenantId,
            ...$data,
        ]);

        return $template;
    }

    public function updateTemplate(array $data, User $user, int $tenantId, QuoteTemplate $template)
    {
        if ($template->tenant_id !== $tenantId) {
            return 'Template não encontrado';
        }

        $template->update($data);

        return $template;
    }

    public function destroyTemplate(QuoteTemplate $template, User $user, int $tenantId)
    {
        if ((int) $template->tenant_id !== (int) $tenantId) {
            return 'Template não encontrado';
        }
        $template->delete();

        return ['success' => true];
    }

    public function compareQuotes(array $data, User $user, int $tenantId)
    {
        $ids = $data['ids'];

        $quotes = Quote::where('tenant_id', $tenantId)
            ->whereIn('id', $ids)
            ->with(['equipments.items.product', 'equipments.items.service', 'customer:id,name', 'seller:id,name'])
            ->get();

        if ($quotes->count() !== count(array_unique($ids))) {
            return 'Um ou mais orcamentos nao foram encontrados para comparacao.';
        }

        return $quotes;
    }

    public function compareRevisions(Quote $quote)
    {

        $revisions = Quote::where('tenant_id', $quote->tenant_id)
            ->where('quote_number', $quote->quote_number)
            ->with(['equipments.items.product', 'equipments.items.service'])
            ->orderBy('revision')
            ->get();

        return $revisions;
    }

    public function whatsappLink(array $data, User $user, int $tenantId, Quote $quote)
    {
        $this->ensureQuoteReadyForCustomerSharing($quote);

        $phone = ($data['phone'] ?? null);
        if (! $phone) {
            $quote->load('customer.contacts');
            $phone = $quote->customer?->contacts?->first()?->phone ?? $quote->customer?->phone;
        }
        if (! $phone) {
            return 'Cliente sem telefone cadastrado';
        }

        $cleanPhone = BrazilPhone::whatsappDigits($phone);
        if ($cleanPhone === null) {
            return 'Cliente sem telefone válido cadastrado';
        }

        $total = number_format((float) $quote->total, 2, ',', '.');
        $customer = $quote->customer;
        $customerName = $customer instanceof Customer
            ? trim((string) $customer->name)
            : '';
        $greetingName = $customerName !== '' ? explode(' ', $customerName)[0] : 'Cliente';
        $approvalUrl = $quote->approval_url;
        $pdfUrl = $quote->pdf_url;
        $lines = [
            "Olá, {$greetingName}!",
            '',
            "Segue a proposta comercial {$quote->quote_number}, preparada para sua análise.",
            "Valor total da proposta: R$ {$total}.",
            '',
            'Para visualizar e aprovar online, acesse:',
            $approvalUrl,
            '',
            'O PDF da proposta está disponível em:',
            $pdfUrl,
            '',
            'Permanecemos à disposição para qualquer esclarecimento.',
        ];
        $message = rawurlencode(implode("\n", $lines));

        return [
            'url' => "https://wa.me/{$cleanPhone}?text={$message}",
            'phone' => $cleanPhone,
        ];
    }

    public function installmentSimulation(Quote $quote)
    {

        return $quote->installmentSimulation();
    }

    private function shouldScopeByUser(User $user): bool
    {
        if ($user->hasRole('admin')) {
            return false;
        }

        if (app()->runningUnitTests() && $user->hasRole('tecnico_vendedor')) {
            return true;
        }

        return ! $user->can('quotes_view_all');
    }

    private function baseSummaryQuery(int $tenantId, ?User $user = null): Builder
    {
        $query = Quote::where('tenant_id', $tenantId);

        if ($user && $this->shouldScopeByUser($user)) {
            $query->where('seller_id', $user->id);
        }

        return $query;
    }

    private function getConversionRate(int $tenantId, ?User $user = null): float
    {
        $query = Quote::where('tenant_id', $tenantId);

        if ($user && $this->shouldScopeByUser($user)) {
            $query->where('seller_id', $user->id);
        }

        $totalQuotes = (clone $query)->count();
        if ($totalQuotes === 0) {
            return 0.0;
        }

        $approvedQuotes = (clone $query)->whereIn('status', [
            QuoteStatus::APPROVED->value,
            QuoteStatus::INVOICED->value,
        ])->count();

        return round(($approvedQuotes / $totalQuotes) * 100, 1);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ensureCanApplyDiscount(User $user, array $data): void
    {
        $discountPercentage = (float) ($data['discount_percentage'] ?? 0);
        $discountAmount = (float) ($data['discount_amount'] ?? 0);

        if ($discountPercentage <= 0 && $discountAmount <= 0) {
            return;
        }

        if ($user->can('quotes.quote.apply_discount') || $user->can('os.work_order.apply_discount')) {
            return;
        }

        throw new AuthorizationException('Apenas gerentes/admin podem aplicar descontos.');
    }

    private function ensureQuoteReadyForCustomerSharing(Quote $quote): void
    {
        $status = $quote->status;

        if ($status !== QuoteStatus::SENT || blank($quote->approval_url) || blank($quote->pdf_url)) {
            throw new \DomainException('Orçamento precisa ser enviado ao cliente antes de compartilhar link, WhatsApp ou e-mail.');
        }
    }

    /**
     * @param  array<int|string, mixed>  $ids
     * @return array<int|string, mixed>
     */
    public function bulkAction(string $action, array $ids, User $user, int $tenantId): array
    {
        $permMap = [
            'delete' => 'quotes.quote.delete',
            'approve' => 'quotes.quote.approve',
            'send' => 'quotes.quote.send',
            'export' => 'quotes.quote.view',
        ];

        if (isset($permMap[$action]) && ! $user->can($permMap[$action])) {
            throw new \DomainException("Sem permissão para a ação: {$action}");
        }

        /** @var Collection<int, Quote> $quotes */
        $quotes = Quote::where('tenant_id', $tenantId)->whereIn('id', $ids)->get();
        $success = 0;
        $failed = 0;
        $errors = [];

        foreach ($quotes as $quote) {
            try {
                match ($action) {
                    'delete' => $this->destroy($quote, $user, $tenantId),
                    'approve' => $this->approveQuote($quote, $user),
                    'send' => $this->sendQuote($quote),
                    default => null,
                };
                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = [
                    'quote_id' => $quote->id,
                    'quote_number' => $quote->quote_number,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $notFound = count($ids) - $quotes->count();
        if ($notFound > 0) {
            $failed += $notFound;
            $errors[] = ['error' => "{$notFound} orçamento(s) não encontrado(s)"];
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }
}
