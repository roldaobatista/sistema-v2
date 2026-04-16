<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GlobalSearchRequest;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    public function search(GlobalSearchRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $query = $validated['q'];
        $tenantId = $request->user()->current_tenant_id;
        $results = [];

        if ($query) {
            $results['customers'] = Customer::where('tenant_id', $tenantId)
                ->where('name', 'like', "%{$query}%")
                ->limit(5)
                ->get(['id', 'name']);

            $results['products'] = Product::where('tenant_id', $tenantId)
                ->where('name', 'like', "%{$query}%")
                ->limit(5)
                ->get(['id', 'name']);
        }

        return response()->json(['data' => $results]);
    }
}
