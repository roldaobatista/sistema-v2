<?php

namespace App\Http\Controllers\Api\V1\Journey;

use App\Http\Controllers\Controller;
use App\Http\Requests\Journey\ApproveJourneyRequest;
use App\Http\Requests\Journey\RejectJourneyRequest;
use App\Http\Resources\Journey\JourneyDayResource;
use App\Models\JourneyEntry;
use App\Services\Journey\DualApprovalService;
use App\Support\ApiResponse;

class JourneyApprovalController extends Controller
{
    public function __construct(
        private DualApprovalService $approvalService,
    ) {}

    /**
     * @return mixed
     */
    public function pending(string $level)
    {
        if (! in_array($level, ['operational', 'hr'])) {
            return ApiResponse::message('Nível inválido. Use: operational ou hr', 422);
        }

        $paginator = $this->approvalService->getPendingApprovals(
            request()->user()->current_tenant_id,
            $level,
            min((int) request()->input('per_page', 25), 100),
        );

        return ApiResponse::paginated($paginator, resourceClass: JourneyDayResource::class);
    }

    /**
     * @return mixed
     */
    public function approve(ApproveJourneyRequest $request, JourneyEntry $journeyDay, string $level)
    {
        $result = match ($level) {
            'operational' => $this->approvalService->approveOperational(
                $journeyDay,
                $request->user(),
                $request->input('notes'),
            ),
            'hr' => $this->approvalService->approveHr(
                $journeyDay,
                $request->user(),
                $request->input('notes'),
            ),
            default => abort(422, 'Nível inválido'),
        };

        return new JourneyDayResource($result->load(['user:id,name', 'blocks']));
    }

    /**
     * @return mixed
     */
    public function reject(RejectJourneyRequest $request, JourneyEntry $journeyDay, string $level)
    {
        $result = match ($level) {
            'operational' => $this->approvalService->rejectOperational(
                $journeyDay,
                $request->user(),
                $request->input('reason'),
            ),
            'hr' => $this->approvalService->rejectHr(
                $journeyDay,
                $request->user(),
                $request->input('reason'),
            ),
            default => abort(422, 'Nível inválido'),
        };

        return new JourneyDayResource($result->load(['user:id,name', 'blocks']));
    }

    /**
     * @return mixed
     */
    public function submit(JourneyEntry $journeyDay)
    {
        $result = $this->approvalService->submitForApproval($journeyDay);

        return new JourneyDayResource($result->load(['user:id,name', 'blocks']));
    }
}
