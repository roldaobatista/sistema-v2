<?php

namespace App\Http\Controllers\Api\V1\Calibration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Calibration\StoreAccreditationScopeRequest;
use App\Http\Requests\Calibration\UpdateAccreditationScopeRequest;
use App\Http\Resources\AccreditationScopeResource;
use App\Models\AccreditationScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccreditationScopeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('accreditation.scope.manage'), 403);

        $scopes = AccreditationScope::where('tenant_id', $request->user()->current_tenant_id)
            ->orderBy('valid_until', 'desc')
            ->paginate(15);

        return AccreditationScopeResource::collection($scopes)->response();
    }

    public function store(StoreAccreditationScopeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $request->user()->current_tenant_id;

        $scope = AccreditationScope::create($data);

        return (new AccreditationScopeResource($scope))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('accreditation.scope.manage'), 403);

        $scope = AccreditationScope::findOrFail($id);
        abort_unless($scope->tenant_id === $request->user()->current_tenant_id, 404);

        return (new AccreditationScopeResource($scope))->response();
    }

    public function update(UpdateAccreditationScopeRequest $request, int $id): JsonResponse
    {
        $scope = AccreditationScope::findOrFail($id);
        abort_unless($scope->tenant_id === $request->user()->current_tenant_id, 404);

        $scope->update($request->validated());

        return (new AccreditationScopeResource($scope->fresh()))->response();
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('accreditation.scope.manage'), 403);

        $scope = AccreditationScope::findOrFail($id);
        abort_unless($scope->tenant_id === $request->user()->current_tenant_id, 404);

        $scope->delete();

        return response()->json(['message' => 'Escopo de acreditação removido.']);
    }

    public function active(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('accreditation.scope.manage'), 403);

        $scopes = AccreditationScope::where('tenant_id', $request->user()->current_tenant_id)
            ->active()
            ->get();

        return AccreditationScopeResource::collection($scopes)->response();
    }
}
