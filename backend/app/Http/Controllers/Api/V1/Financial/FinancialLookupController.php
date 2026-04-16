<?php

namespace App\Http\Controllers\Api\V1\Financial;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Lookups\SupplierContractPaymentFrequency;
use App\Models\PaymentMethod;
use App\Models\Supplier;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialLookupController extends Controller
{
    public function suppliers(Request $request): JsonResponse
    {
        $query = Supplier::query()
            ->select(['id', 'name'])
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = SearchSanitizer::escapeLike((string) $request->string('search'));
            $query->where('name', 'like', "%{$search}%");
        }

        if (! $request->boolean('include_inactive', false)) {
            $query->where('is_active', true);
        }

        return ApiResponse::data(
            $query->limit(min((int) $request->integer('limit', 200), 200))->get()
        );
    }

    public function customers(Request $request): JsonResponse
    {
        $query = Customer::query()
            ->select(['id', 'name', 'document'])
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = SearchSanitizer::escapeLike((string) $request->string('search'));
            $query->where('name', 'like', "%{$search}%");
        }

        if (! $request->boolean('include_inactive', false)) {
            $query->where('is_active', true);
        }

        return ApiResponse::data(
            $query->limit(min((int) $request->integer('limit', 200), 200))->get()
        );
    }

    public function workOrders(Request $request): JsonResponse
    {
        $query = WorkOrder::query()
            ->select(['id', 'number', 'os_number', 'customer_id', 'total'])
            ->with(['customer:id,name'])
            ->latest('id');

        if ($request->filled('search')) {
            $search = SearchSanitizer::escapeLike((string) $request->string('search'));
            $query->where(function ($workOrderQuery) use ($search) {
                $workOrderQuery
                    ->where('number', 'like', "%{$search}%")
                    ->orWhere('os_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        return ApiResponse::data(
            $query->limit(min((int) $request->integer('limit', 100), 100))->get()
        );
    }

    public function paymentMethods(): JsonResponse
    {
        return ApiResponse::data(
            PaymentMethod::query()
                ->select(['id', 'code', 'name', 'is_active'])
                ->orderBy('name')
                ->get()
        );
    }

    public function bankAccounts(Request $request): JsonResponse
    {
        $query = BankAccount::query()
            ->select(['id', 'name', 'bank_name', 'is_active'])
            ->orderBy('name');

        if (! $request->boolean('include_inactive', false)) {
            $query->where('is_active', true);
        }

        return ApiResponse::data(
            $query->limit(min((int) $request->integer('limit', 100), 100))->get()
        );
    }

    public function supplierContractPaymentFrequencies(): JsonResponse
    {
        $lookupClass = SupplierContractPaymentFrequency::class;

        return ApiResponse::data(
            $lookupClass::query()
                ->select(['id', 'name', 'slug'])
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
        );
    }
}
