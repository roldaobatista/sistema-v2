<?php

namespace App\Http\Controllers\Api\V1\Journey;

use App\Http\Controllers\Controller;
use App\Http\Requests\Journey\AdjustJourneyBlockRequest;
use App\Http\Resources\Journey\JourneyBlockResource;
use App\Models\JourneyBlock;

class JourneyBlockController extends Controller
{
    /**
     * @return mixed
     */
    public function adjust(AdjustJourneyBlockRequest $request, JourneyBlock $journeyBlock)
    {
        $journeyBlock->update([
            'classification' => $request->input('classification'),
            'started_at' => $request->input('started_at'),
            'ended_at' => $request->input('ended_at'),
            'duration_minutes' => $request->input('ended_at')
                ? (int) now()->parse($request->input('started_at'))->diffInMinutes($request->input('ended_at'))
                : $journeyBlock->duration_minutes,
            'is_auto_classified' => false,
            'is_manually_adjusted' => true,
            'adjusted_by' => $request->user()->id,
            'adjustment_reason' => $request->input('adjustment_reason'),
        ]);

        // Recalculate totals on the parent JourneyEntry
        if ($journeyBlock->journeyEntry) {
            $journeyBlock->journeyEntry->recalculateTotals();
        } elseif ($journeyBlock->journeyDay) {
            $journeyBlock->journeyDay->recalculateTotals();
        }

        return new JourneyBlockResource($journeyBlock->fresh());
    }
}
