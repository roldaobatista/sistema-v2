<?php

namespace App\Http\Controllers\Api\V1\Journey;

use App\Http\Controllers\Controller;
use App\Http\Requests\Journey\SubmitExpenseReportRequest;
use App\Models\TravelExpenseItem;
use App\Models\TravelExpenseReport;
use App\Models\TravelRequest;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;

class TravelExpenseController extends Controller
{
    /**
     * @return mixed
     */
    public function submitReport(SubmitExpenseReportRequest $request, TravelRequest $travelRequest)
    {
        return DB::transaction(function () use ($request, $travelRequest) {
            $report = TravelExpenseReport::updateOrCreate(
                [
                    'tenant_id' => $request->user()->current_tenant_id,
                    'travel_request_id' => $travelRequest->id,
                ],
                [
                    'created_by' => $request->user()->id,
                    'status' => 'submitted',
                ],
            );

            // Clear old items and recreate
            $report->items()->delete();

            foreach ($request->input('items') as $item) {
                TravelExpenseItem::create([
                    'travel_expense_report_id' => $report->id,
                    ...$item,
                ]);
            }

            $report->recalculate();

            return response()->json([
                'data' => $report->fresh(['items'])->toArray(),
            ]);
        });
    }

    /**
     * @return mixed
     */
    public function approveReport(TravelRequest $travelRequest)
    {
        $report = TravelExpenseReport::where('travel_request_id', $travelRequest->id)->firstOrFail();

        if ($report->status !== 'submitted') {
            return ApiResponse::message('Apenas relatórios submetidos podem ser aprovados.', 422);
        }

        $report->update([
            'status' => 'approved',
            'approved_by' => request()->user()->id,
        ]);

        return response()->json(['data' => $report->fresh(['items'])->toArray()]);
    }
}
