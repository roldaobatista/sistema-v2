<?php

namespace App\Http\Controllers\Api\V1\Journey;

use App\Http\Controllers\Controller;
use App\Http\Requests\Journey\IndexJourneyDayRequest;
use App\Http\Resources\Journey\JourneyDayResource;
use App\Models\JourneyEntry;
use App\Services\Journey\JourneyOrchestratorService;
use App\Support\ApiResponse;
use Carbon\Carbon;

class JourneyDayController extends Controller
{
    public function __construct(
        private JourneyOrchestratorService $orchestrator,
    ) {}

    /**
     * @return mixed
     */
    public function index(IndexJourneyDayRequest $request)
    {
        $query = JourneyEntry::with(['user:id,name', 'blocks']);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->input('date_to'));
        }

        if ($request->filled('approval_status')) {
            $status = $request->input('approval_status');
            $query->where(function ($q) use ($status) {
                $q->where('operational_approval_status', $status)
                    ->orWhere('hr_approval_status', $status);
            });
        }

        if ($request->has('is_closed')) {
            $query->where('is_closed', $request->boolean('is_closed'));
        }

        $paginator = $query->orderByDesc('date')
            ->paginate(min((int) $request->input('per_page', 25), 100));

        return ApiResponse::paginated($paginator, resourceClass: JourneyDayResource::class);
    }

    /**
     * @return mixed
     */
    public function show(JourneyEntry $journeyDay)
    {
        return new JourneyDayResource(
            $journeyDay->load(['user:id,name', 'blocks'])
        );
    }

    /**
     * @return mixed
     */
    public function reclassify(JourneyEntry $journeyDay)
    {
        $result = $this->orchestrator->reprocessDay(
            $journeyDay->user_id,
            Carbon::parse($journeyDay->date),
        );

        return new JourneyDayResource(
            $result->load(['user:id,name', 'blocks'])
        );
    }
}
