<?php

namespace App\Http\Controllers\Api\V1\Contracts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contracts\StoreContractAddendumRequest;
use App\Http\Requests\Contracts\UpdateContractAddendumRequest;
use App\Models\ContractAddendum;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ContractAddendumController extends Controller
{
    public function index(Request $request)
    {
        $paginator = ContractAddendum::with('contract')
            ->paginate(min((int) $request->input('per_page', 25), 100));

        return ApiResponse::paginated($paginator);
    }

    public function store(StoreContractAddendumRequest $request)
    {
        $addendum = ContractAddendum::create(array_merge($request->validated(), [
            'tenant_id' => $request->user()->current_tenant_id,
            'created_by' => $request->user()->id,
        ]));

        return response()->json($addendum->load('contract'), 201);
    }

    public function show(ContractAddendum $addendum)
    {
        return response()->json($addendum->load('contract'));
    }

    public function update(UpdateContractAddendumRequest $request, ContractAddendum $addendum)
    {
        $addendum->update($request->validated());

        return response()->json($addendum->load('contract'));
    }

    public function destroy(ContractAddendum $addendum)
    {
        $addendum->delete();

        return response()->noContent();
    }
}
