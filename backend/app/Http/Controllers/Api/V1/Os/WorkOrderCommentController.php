<?php

namespace App\Http\Controllers\Api\V1\Os;

use App\Http\Controllers\Controller;
use App\Http\Requests\Os\StoreWorkOrderCommentRequest;
use App\Models\WorkOrder;
use App\Models\WorkOrderChat;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;

class WorkOrderCommentController extends Controller
{
    use ResolvesCurrentTenant;

    public function comments(WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('view', $workOrder);
        if ($error = $this->ensureTenantOwnership($workOrder, 'OS')) {
            return $error;
        }

        return ApiResponse::paginated(
            $workOrder->chats()
                ->where('tenant_id', $this->tenantId())
                ->with('user:id,name,avatar_url')
                ->orderBy('created_at')
                ->paginate(30)
        );
    }

    public function storeComment(StoreWorkOrderCommentRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('update', $workOrder);
        if ($error = $this->ensureTenantOwnership($workOrder, 'OS')) {
            return $error;
        }

        $comment = WorkOrderChat::create([
            'tenant_id' => $this->tenantId(),
            'work_order_id' => $workOrder->id,
            'user_id' => $request->user()->id,
            'message' => $request->validated()['content'],
            'type' => 'comment',
        ]);

        return ApiResponse::data($comment->load('user:id,name,avatar_url'), 201);
    }
}
