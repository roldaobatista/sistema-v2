<?php

namespace App\Http\Controllers\Api\V1\Journey;

use App\Http\Controllers\Controller;
use App\Http\Requests\Journey\GenerateESocialRequest;
use App\Http\Requests\Journey\PayrollMonthRequest;
use App\Services\ESocial\JourneyESocialGenerator;
use App\Services\Journey\PayrollIntegrationService;

class PayrollIntegrationController extends Controller
{
    public function __construct(
        private PayrollIntegrationService $payrollService,
        private JourneyESocialGenerator $esocialGenerator,
    ) {}

    /**
     * @return mixed
     */
    public function monthSummary(PayrollMonthRequest $request)
    {
        $summary = $this->payrollService->exportMonthSummary(
            $request->user()->current_tenant_id,
            $request->validated('year_month'),
        );

        return response()->json(['data' => $summary]);
    }

    /**
     * @return mixed
     */
    public function blockingDays(PayrollMonthRequest $request)
    {
        $blocking = $this->payrollService->getBlockingDays(
            $request->user()->current_tenant_id,
            $request->validated('year_month'),
        );

        return response()->json(['data' => $blocking]);
    }

    /**
     * @return mixed
     */
    public function generateESocial(GenerateESocialRequest $request)
    {
        $yearMonth = $request->validated('year_month');
        $tenantId = $request->user()->current_tenant_id;
        $results = [];

        foreach ($request->validated('event_types') as $type) {
            $events = match ($type) {
                'S-1200' => $this->esocialGenerator->generateS1200ForMonth($tenantId, $yearMonth),
                'S-2230' => $this->esocialGenerator->generateS2230ForAbsences($tenantId, $yearMonth),
                default => collect(),
            };

            $results[$type] = [
                'generated' => $events->count(),
                'event_ids' => $events->pluck('id')->toArray(),
            ];
        }

        return response()->json(['data' => $results]);
    }
}
