<?php

namespace App\Http\Controllers\Api\V1\Os;

use App\Http\Controllers\Controller;
use App\Http\Requests\Os\StoreWorkOrderAttachmentRequest;
use App\Http\Requests\Os\UploadChecklistPhotoRequest;
use App\Models\WorkOrder;
use App\Models\WorkOrderAttachment;
use App\Support\ApiResponse;
use App\Support\FilenameSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WorkOrderAttachmentController extends Controller
{
    use ResolvesCurrentTenant;

    public function attachments(WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('view', $workOrder);
        if ($error = $this->ensureTenantOwnership($workOrder, 'OS')) {
            return $error;
        }

        return ApiResponse::data($workOrder->attachments()->with('uploader:id,name')->simplePaginate(15));
    }

    public function photos(WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('view', $workOrder);
        if ($error = $this->ensureTenantOwnership($workOrder, 'OS')) {
            return $error;
        }

        return ApiResponse::data(
            $workOrder->attachments()
                ->where('tenant_id', $this->tenantId())
                ->with('uploader:id,name')
                ->orderByDesc('created_at')
                ->simplePaginate(15)
        );
    }

    public function storeAttachment(StoreWorkOrderAttachmentRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('update', $workOrder);
        if ($error = $this->ensureTenantOwnership($workOrder, 'OS')) {
            return $error;
        }

        $path = '';

        try {
            $file = $request->file('file');
            $path = $file->store("work-orders/{$workOrder->id}/attachments", 'public');
            if (! is_string($path)) {
                return ApiResponse::message('Erro ao salvar anexo', 500);
            }

            $v = $request->validated();

            $attachment = $workOrder->attachments()->create([
                'tenant_id' => $this->tenantId(),
                'uploaded_by' => $request->user()->id,
                'file_name' => FilenameSanitizer::sanitize($file->getClientOriginalName()),
                'file_path' => $path,
                'file_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'description' => $v['description'] ?? null,
            ]);

            return ApiResponse::data($attachment->load('uploader:id,name'), 201);
        } catch (\Exception $e) {
            $pathForCleanup = $path;
            if ($pathForCleanup !== '') {
                Storage::disk('public')->delete($pathForCleanup);
            }
            Log::error('WorkOrder storeAttachment failed', ['wo_id' => $workOrder->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao enviar anexo', 500);
        }
    }

    public function destroyAttachment(WorkOrder $workOrder, WorkOrderAttachment $attachment): JsonResponse
    {
        $this->authorize('update', $workOrder);
        if ($error = $this->ensureTenantOwnership($workOrder, 'OS')) {
            return $error;
        }

        if ($attachment->work_order_id !== $workOrder->id) {
            return ApiResponse::message('Anexo não pertence a esta OS', 403);
        }

        try {
            $filePath = $attachment->file_path;
            $attachment->delete();
            Storage::disk('public')->delete($filePath);
        } catch (\Exception $e) {
            Log::error('WorkOrder destroyAttachment failed', ['attachment_id' => $attachment->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao remover anexo', 500);
        }

        return ApiResponse::noContent();
    }

    public function uploadChecklistPhoto(UploadChecklistPhotoRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('update', $workOrder);
        if ($error = $this->ensureTenantOwnership($workOrder, 'OS')) {
            return $error;
        }

        $path = $request->file('photo')->store("work-orders/{$workOrder->id}/checklist", 'public');
        if (! is_string($path)) {
            return ApiResponse::message('Erro ao salvar foto do checklist', 500);
        }

        $url = Storage::disk('public')->url($path);

        return ApiResponse::data([
            'path' => $path,
            'url' => $url,
            'checklist_item_id' => $request->input('checklist_item_id'),
            'step' => $request->input('step'),
        ], 201);
    }
}
