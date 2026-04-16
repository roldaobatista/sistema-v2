<?php

namespace App\Http\Controllers\Api\V1\Journey;

use App\Http\Controllers\Controller;
use App\Http\Requests\Journey\StoreTravelRequestRequest;
use App\Http\Resources\Journey\TravelRequestResource;
use App\Models\TravelRequest;
use App\Support\ApiResponse;

class TravelRequestController extends Controller
{
    /**
     * @return mixed
     */
    public function index()
    {
        $paginator = TravelRequest::with(['user:id,name'])
            ->orderByDesc('departure_date')
            ->paginate(min((int) request()->input('per_page', 25), 100));

        return ApiResponse::paginated($paginator, resourceClass: TravelRequestResource::class);
    }

    /**
     * @return mixed
     */
    public function show(TravelRequest $travelRequest)
    {
        return new TravelRequestResource(
            $travelRequest->load(['user:id,name', 'overnightStays', 'advances', 'expenseReport.items'])
        );
    }

    /**
     * @return mixed
     */
    public function store(StoreTravelRequestRequest $request)
    {
        $travelRequest = TravelRequest::create([
            ...$request->validated(),
            'tenant_id' => $request->tenantId(),
            'status' => TravelRequest::STATUS_PENDING,
        ]);

        return (new TravelRequestResource($travelRequest->load('user:id,name')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @return mixed
     */
    public function update(StoreTravelRequestRequest $request, TravelRequest $travelRequest)
    {
        if (! $travelRequest->isPending()) {
            return ApiResponse::message('Apenas solicitações pendentes podem ser editadas.', 422);
        }

        $travelRequest->update($request->validated());

        return new TravelRequestResource($travelRequest->fresh(['user:id,name']));
    }

    /**
     * @return mixed
     */
    public function approve(TravelRequest $travelRequest)
    {
        if (! $travelRequest->isPending()) {
            return ApiResponse::message('Apenas solicitações pendentes podem ser aprovadas.', 422);
        }

        $travelRequest->update([
            'status' => TravelRequest::STATUS_APPROVED,
            'approved_by' => request()->user()->id,
        ]);

        return new TravelRequestResource($travelRequest->fresh(['user:id,name']));
    }

    /**
     * @return mixed
     */
    public function cancel(TravelRequest $travelRequest)
    {
        if ($travelRequest->status === TravelRequest::STATUS_COMPLETED) {
            return ApiResponse::message('Viagens concluídas não podem ser canceladas.', 422);
        }

        $travelRequest->update(['status' => TravelRequest::STATUS_CANCELLED]);

        return new TravelRequestResource($travelRequest->fresh());
    }

    /**
     * @return mixed
     */
    public function destroy(TravelRequest $travelRequest)
    {
        if (! $travelRequest->isPending()) {
            return ApiResponse::message('Apenas solicitações pendentes podem ser removidas.', 422);
        }

        $travelRequest->delete();

        return response()->noContent();
    }
}
