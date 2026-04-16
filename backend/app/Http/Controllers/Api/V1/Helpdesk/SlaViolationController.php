<?php

namespace App\Http\Controllers\Api\V1\Helpdesk;

use App\Http\Controllers\Controller;
use App\Http\Requests\Helpdesk\IndexSlaViolationRequest;
use App\Models\SlaViolation;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class SlaViolationController extends Controller
{
    public function index(IndexSlaViolationRequest $request): JsonResponse
    {
        $violations = SlaViolation::with(['ticket', 'slaPolicy'])
            ->paginate(min((int) $request->validated('per_page', 25), 100));

        return ApiResponse::paginated($violations);
    }

    public function show(SlaViolation $slaViolation): JsonResponse
    {
        return response()->json($slaViolation->load(['ticket', 'slaPolicy']));
    }
}
