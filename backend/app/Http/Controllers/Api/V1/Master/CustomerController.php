<?php

namespace App\Http\Controllers\Api\V1\Master;

use App\Events\CustomerCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\IndexCustomerRequest;
use App\Http\Requests\Customer\StoreCustomerAddressRequest;
use App\Http\Requests\Customer\StoreCustomerContactRequest;
use App\Http\Requests\Customer\StoreCustomerDocumentRequest;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\EquipmentResource;
use App\Http\Resources\QuoteResource;
use App\Http\Resources\WorkOrderResource;
use App\Models\AccountReceivable;
use App\Models\CrmDeal;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Lookups\BaseLookup;
use App\Models\Lookups\ContractType;
use App\Models\Lookups\CustomerCompanySize;
use App\Models\Lookups\CustomerRating;
use App\Models\Lookups\CustomerSegment;
use App\Models\Lookups\LeadSource;
use App\Models\Quote;
use App\Models\ServiceCall;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(IndexCustomerRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Customer::class);
        $query = Customer::with(['contacts', 'assignedSeller:id,name'])
            ->withCount('documents')
            ->withMin('equipments as nearest_calibration_at', 'next_calibration_at');

        if ($search = $request->get('search')) {
            $search = SearchSanitizer::escapeLike($search);
            $digitsOnlySearch = preg_replace('/\D+/', '', (string) $request->get('search'));
            $query->where(function ($q) use ($search, $digitsOnlySearch) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('trade_name', 'like', "%{$search}%")
                    ->orWhere('document', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");

                if (! is_string($digitsOnlySearch) || $digitsOnlySearch === '') {
                    return;
                }

                $normalizedColumns = [
                    'document',
                    'phone',
                    'phone2',
                    'state_registration',
                    'municipal_registration',
                ];

                foreach ($normalizedColumns as $column) {
                    $q->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$column}, ''), '.', ''), '-', ''), '/', ''), '(', ''), ')', ''), ' ', ''), '+', ''), '_', '') LIKE ?",
                        ["%{$digitsOnlySearch}%"]
                    );
                }
            });
        }

        if ($request->has('type')) {
            $query->where('type', $request->get('type'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('segment')) {
            $query->where('segment', $request->get('segment'));
        }

        if ($request->has('rating')) {
            $query->where('rating', $request->get('rating'));
        }

        if ($request->has('source')) {
            $query->where('source', $request->get('source'));
        }

        if ($request->has('assigned_seller_id')) {
            $query->where('assigned_seller_id', $request->get('assigned_seller_id'));
        }

        $sortField = $request->get('sort', 'name');
        $sortDir = $request->get('direction', 'asc');
        $allowedSorts = ['name', 'created_at', 'health_score', 'last_contact_at', 'rating'];

        if (in_array($sortField, $allowedSorts, true)) {
            $query->orderBy($sortField, $sortDir === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('name');
        }

        $customers = $query->paginate(min((int) $request->get('per_page', 20), 100));

        return ApiResponse::paginated($customers, resourceClass: CustomerResource::class);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $this->authorize('create', Customer::class);
        $validated = $request->validated();
        $tenantId = $this->tenantId();

        try {
            $customer = DB::transaction(function () use ($validated, $tenantId) {
                $customerPayload = collect($validated)
                    ->except(['contacts', 'tenant_id'])
                    ->toArray();
                $customerPayload['tenant_id'] = $tenantId;

                $customer = Customer::create($customerPayload);

                foreach ($validated['contacts'] ?? [] as $contactData) {
                    $customer->contacts()->create([
                        ...$contactData,
                        'tenant_id' => $tenantId,
                    ]);
                }

                CustomerCreated::dispatch($customer);

                return $customer;
            });

            return ApiResponse::data(new CustomerResource($customer->load(['contacts', 'assignedSeller:id,name'])), 201);
        } catch (\Throwable $e) {
            Log::error('Customer store failed', [
                'tenant_id' => $tenantId,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro interno ao criar cliente.', 500);
        }
    }

    public function show(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);
        if ($deny = $this->ensureTenantOwnership($customer, 'Cliente')) {
            return $deny;
        }

        return ApiResponse::data(new CustomerResource(
            $customer->load(['contacts', 'assignedSeller:id,name', 'equipments'])
        ));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);
        if ($deny = $this->ensureTenantOwnership($customer, 'Cliente')) {
            return $deny;
        }

        $validated = $request->validated();
        $tenantId = $this->tenantId();

        try {
            DB::transaction(function () use ($validated, $request, $customer, $tenantId) {
                $customer->update(collect($validated)->except(['contacts', 'tenant_id'])->toArray());

                if (! $request->has('contacts')) {
                    return;
                }

                $providedContacts = $validated['contacts'] ?? [];
                $existingContactIds = [];

                foreach ($providedContacts as $contactData) {
                    if (! empty($contactData['id'])) {
                        $contactId = (int) $contactData['id'];
                        $updateData = collect($contactData)->except('id')->toArray();

                        $customer->contacts()
                            ->where('id', $contactId)
                            ->where('tenant_id', $tenantId)
                            ->update($updateData);

                        $existingContactIds[] = $contactId;
                        continue;
                    }

                    $newContact = $customer->contacts()->create([
                        ...$contactData,
                        'tenant_id' => $tenantId,
                    ]);
                    $existingContactIds[] = $newContact->id;
                }

                $customer->contacts()
                    ->where('tenant_id', $tenantId)
                    ->whereNotIn('id', $existingContactIds)
                    ->delete();
            });

            return ApiResponse::data(new CustomerResource($customer->load(['contacts', 'assignedSeller:id,name'])));
        } catch (\Throwable $e) {
            Log::error('Customer update failed', [
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro interno ao atualizar cliente.', 500);
        }
    }

    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);
        if ($deny = $this->ensureTenantOwnership($customer, 'Cliente')) {
            return $deny;
        }

        $workOrdersCount = $this->countDependency(WorkOrder::class, $customer->id);
        $receivablesCount = $this->countDependency(
            AccountReceivable::class,
            $customer->id,
            fn ($query) => $query->whereIn('status', [
                AccountReceivable::STATUS_PENDING,
                AccountReceivable::STATUS_PARTIAL,
                AccountReceivable::STATUS_OVERDUE,
            ])
        );
        $quotesCount = $this->countDependency(Quote::class, $customer->id);
        $dealsCount = $this->countDependency(CrmDeal::class, $customer->id);
        $serviceCallsCount = $this->countDependency(ServiceCall::class, $customer->id);
        $equipmentsCount = $this->countDependency(Equipment::class, $customer->id);

        if (
            $workOrdersCount > 0
            || $receivablesCount > 0
            || $quotesCount > 0
            || $dealsCount > 0
            || $serviceCallsCount > 0
            || $equipmentsCount > 0
        ) {
            $blocks = [];
            if ($workOrdersCount > 0) {
                $blocks[] = "{$workOrdersCount} ordem(ns) de servico";
            }
            if ($receivablesCount > 0) {
                $blocks[] = "{$receivablesCount} pendencia(s) financeira(s)";
            }
            if ($quotesCount > 0) {
                $blocks[] = "{$quotesCount} orcamento(s)";
            }
            if ($dealsCount > 0) {
                $blocks[] = "{$dealsCount} negociacao(oes)";
            }
            if ($serviceCallsCount > 0) {
                $blocks[] = "{$serviceCallsCount} chamado(s)";
            }
            if ($equipmentsCount > 0) {
                $blocks[] = "{$equipmentsCount} equipamento(s)";
            }

            return ApiResponse::message(
                'Não é possivel excluir - cliente possui '.implode(', ', $blocks),
                409,
                [
                    'dependencies' => [
                        'active_work_orders' => $workOrdersCount,
                        'receivables' => $receivablesCount,
                        'quotes' => $quotesCount,
                        'deals' => $dealsCount,
                        'service_calls' => $serviceCallsCount,
                        'equipments' => $equipmentsCount,
                    ],
                ]
            );
        }

        try {
            DB::transaction(fn () => $customer->delete());

            return ApiResponse::noContent();
        } catch (\Throwable $e) {
            Log::error('Customer destroy failed', [
                'tenant_id' => $this->tenantId(),
                'customer_id' => $customer->id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao excluir cliente', 500);
        }
    }

    public function addresses(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);
        if ($deny = $this->ensureTenantOwnership($customer, 'Cliente')) {
            return $deny;
        }

        return ApiResponse::data(
            $customer->addresses()
                ->where('tenant_id', $this->tenantId())
                ->orderByDesc('is_main')
                ->orderBy('id')
                ->get()
        );
    }

    public function storeAddress(StoreCustomerAddressRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);
        if ($deny = $this->ensureTenantOwnership($customer, 'Cliente')) {
            return $deny;
        }

        $address = $customer->addresses()->create([
            ...$request->validated(),
            'tenant_id' => $this->tenantId(),
        ]);

        return ApiResponse::data($address, 201);
    }

    public function contacts(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);
        if ($deny = $this->ensureTenantOwnership($customer, 'Cliente')) {
            return $deny;
        }

        return ApiResponse::data(
            $customer->contacts()
                ->where('tenant_id', $this->tenantId())
                ->orderByDesc('is_primary')
                ->orderBy('name')
                ->get()
        );
    }

    public function storeContact(StoreCustomerContactRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);
        if ($deny = $this->ensureTenantOwnership($customer, 'Cliente')) {
            return $deny;
        }

        $contact = $customer->contacts()->create([
            ...$request->validated(),
            'tenant_id' => $this->tenantId(),
        ]);

        return ApiResponse::data($contact, 201);
    }

    public function workOrders(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);
        if ($deny = $this->ensureTenantOwnership($customer, 'Cliente')) {
            return $deny;
        }

        $items = WorkOrder::query()
            ->where('tenant_id', $this->tenantId())
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->integer('per_page', 15), 100));

        return ApiResponse::paginated($items, resourceClass: WorkOrderResource::class);
    }

    public function equipments(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);
        if ($deny = $this->ensureTenantOwnership($customer, 'Cliente')) {
            return $deny;
        }

        $items = Equipment::query()
            ->where('tenant_id', $this->tenantId())
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->integer('per_page', 15), 100));

        return ApiResponse::paginated($items, resourceClass: EquipmentResource::class);
    }

    public function quotes(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);
        if ($deny = $this->ensureTenantOwnership($customer, 'Cliente')) {
            return $deny;
        }

        $items = Quote::query()
            ->with(['customer:id,name', 'seller:id,name', 'tags:id,name,color'])
            ->where('tenant_id', $this->tenantId())
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->integer('per_page', 15), 100));

        return ApiResponse::paginated($items, resourceClass: QuoteResource::class);
    }

    public function options(): JsonResponse
    {
        $sources = $this->lookupMap(LeadSource::class);
        $segments = $this->lookupMap(CustomerSegment::class);
        $contractTypes = $this->lookupMap(ContractType::class);
        $companySizes = $this->lookupMap(CustomerCompanySize::class);
        $ratings = $this->lookupMap(CustomerRating::class);

        if (empty($sources)) {
            $sources = Customer::SOURCES;
        }
        if (empty($segments)) {
            $segments = Customer::SEGMENTS;
        }
        if (empty($contractTypes)) {
            $contractTypes = Customer::CONTRACT_TYPES;
        }
        if (empty($companySizes)) {
            $companySizes = Customer::COMPANY_SIZES;
        }
        if (empty($ratings)) {
            $ratings = Customer::RATINGS;
        }

        return ApiResponse::data([
            'sources' => $sources,
            'segments' => $segments,
            'company_sizes' => $companySizes,
            'contract_types' => $contractTypes,
            'ratings' => $ratings,
        ]);
    }

    /**
     * @param  class-string<BaseLookup>  $lookupClass
     * @return array<string, string>
     */
    private function lookupMap(string $lookupClass): array
    {
        return $lookupClass::query()
            ->active()
            ->ordered()
            ->pluck('name', 'slug')
            ->all();
    }

    public function stats(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);
        if ($deny = $this->ensureTenantOwnership($customer, 'Cliente')) {
            return $deny;
        }

        return ApiResponse::data([
            'customer_id' => $customer->id,
            'equipments_count' => $customer->equipments()->count(),
            'work_orders_count' => $customer->workOrders()->count(),
            'quotes_count' => $customer->quotes()->count(),
            'service_calls_count' => $customer->serviceCalls()->count(),
            'open_receivables_count' => $customer->accountsReceivable()
                ->whereIn('status', [
                    AccountReceivable::STATUS_PENDING,
                    AccountReceivable::STATUS_PARTIAL,
                    AccountReceivable::STATUS_OVERDUE,
                ])
                ->count(),
            'nearest_calibration_at' => $customer->nearest_calibration_at,
            'health_score' => $customer->health_score,
        ]);
    }

    public function documents(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);
        if ($deny = $this->ensureTenantOwnership($customer, 'Cliente')) {
            return $deny;
        }

        return ApiResponse::data(
            $customer->documents()
                ->where('tenant_id', $this->tenantId())
                ->orderByDesc('created_at')
                ->get()
        );
    }

    public function storeDocument(StoreCustomerDocumentRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);
        if ($deny = $this->ensureTenantOwnership($customer, 'Cliente')) {
            return $deny;
        }

        $validated = $request->validated();

        $path = $request->file('file')->store('customer_documents', 'public');

        $document = $customer->documents()->create([
            'tenant_id' => $this->tenantId(),
            'title' => $validated['title'],
            'type' => $validated['type'] ?? null,
            'file_path' => $path,
            'file_name' => $request->file('file')->getClientOriginalName(),
            'file_size' => $request->file('file')->getSize(),
            'expiry_date' => $validated['expiry_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'uploaded_by' => $request->user()->id,
        ]);

        return ApiResponse::data($document, 201);
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function countDependency(string $modelClass, int $customerId, ?callable $callback = null): int
    {
        if (! class_exists($modelClass)) {
            return 0;
        }

        $query = $modelClass::where('tenant_id', $this->tenantId())
            ->where('customer_id', $customerId);

        if ($callback !== null) {
            $callback($query);
        }

        return (int) $query->count();
    }
}
