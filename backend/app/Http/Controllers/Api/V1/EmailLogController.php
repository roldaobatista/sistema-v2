<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmailLogResource;
use App\Models\EmailLog;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = EmailLog::where('tenant_id', $request->user()->current_tenant_id)
            ->latest()
            ->paginate(max(1, min($request->integer('per_page', 15), 100)));

        return ApiResponse::paginated($items, resourceClass: EmailLogResource::class);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $item = EmailLog::where('tenant_id', $request->user()->current_tenant_id)
            ->findOrFail($id);

        return (new EmailLogResource($item))->response();
    }
}
