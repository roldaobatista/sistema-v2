<?php

namespace App\Http\Controllers\Api\V1\Os;

use App\Http\Controllers\Controller;
use App\Http\Requests\Os\SendWorkOrderChatMessageRequest;
use App\Models\WorkOrder;
use App\Models\WorkOrderChat;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WorkOrderChatController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->authorize('view', $workOrder);

            if ($error = $this->ensureTenantOwnership($workOrder, 'OS Chat')) {
                return $error;
            }

            $messages = $workOrder->chats()
                ->with('user:id,name,avatar_url')
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->paginate(min((int) $request->input('per_page', 25), 100));

            return ApiResponse::paginated($messages);
        } catch (AuthorizationException $e) {
            return ApiResponse::message($e->getMessage() ?: 'Voce nao tem permissao para acessar o chat desta OS.', 403);
        } catch (\Exception $e) {
            Log::error('WorkOrderChat index failed', [
                'work_order_id' => $workOrder->id ?? null,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao carregar chat', 500);
        }
    }

    public function store(SendWorkOrderChatMessageRequest $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->authorize('update', $workOrder);

            DB::beginTransaction();

            if ($error = $this->ensureTenantOwnership($workOrder, 'OS Chat')) {
                DB::rollBack();

                return $error;
            }

            $validated = $request->validated();

            $data = [
                'tenant_id' => $workOrder->tenant_id,
                'work_order_id' => $workOrder->id,
                'user_id' => $request->user()->id,
                'message' => $validated['message'],
                'type' => $validated['type'] ?? 'text',
            ];

            if ($request->hasFile('file')) {
                $path = $request->file('file')->store("work-orders/{$workOrder->id}/chat", 'public');
                $data['file_path'] = $path;
                $data['type'] = 'file';
            }

            $chat = WorkOrderChat::create($data);

            DB::commit();

            return ApiResponse::data($chat->load('user:id,name,avatar_url'), 201);
        } catch (AuthorizationException $e) {
            DB::rollBack();

            return ApiResponse::message($e->getMessage() ?: 'Voce nao tem permissao para enviar mensagens nesta OS.', 403);
        } catch (ValidationException $e) {
            DB::rollBack();

            return ApiResponse::message('Dados invalidos.', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('WorkOrderChat store failed', [
                'work_order_id' => $workOrder->id ?? null,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao enviar mensagem', 500);
        }
    }

    public function markAsRead(Request $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->authorize('view', $workOrder);

            if ($error = $this->ensureTenantOwnership($workOrder, 'OS Chat')) {
                return $error;
            }

            $workOrder->chats()
                ->where('user_id', '!=', $request->user()?->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            return ApiResponse::message('Mensagens marcadas como lidas');
        } catch (AuthorizationException $e) {
            return ApiResponse::message($e->getMessage() ?: 'Voce nao tem permissao para acessar o chat desta OS.', 403);
        } catch (\Exception $e) {
            Log::error('WorkOrderChat markAsRead failed', [
                'work_order_id' => $workOrder->id ?? null,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao marcar como lido', 500);
        }
    }
}
