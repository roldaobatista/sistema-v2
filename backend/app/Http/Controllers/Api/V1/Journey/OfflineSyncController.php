<?php

namespace App\Http\Controllers\Api\V1\Journey;

use App\Http\Controllers\Controller;
use App\Http\Requests\Journey\OfflineSyncRequest;
use App\Services\Journey\OfflineSyncService;

class OfflineSyncController extends Controller
{
    public function __construct(
        private OfflineSyncService $syncService,
    ) {}

    /**
     * @return mixed
     */
    public function sync(OfflineSyncRequest $request)
    {
        $results = $this->syncService->processBatch(
            $request->user(),
            $request->validated('events'),
        );

        $accepted = collect($results)->where('status', 'accepted')->count();
        $duplicates = collect($results)->where('status', 'duplicate')->count();
        $rejected = collect($results)->where('status', 'rejected')->count();

        return response()->json([
            'data' => [
                'results' => $results,
                'summary' => [
                    'total' => count($results),
                    'accepted' => $accepted,
                    'duplicate' => $duplicates,
                    'rejected' => $rejected,
                ],
            ],
        ]);
    }
}
