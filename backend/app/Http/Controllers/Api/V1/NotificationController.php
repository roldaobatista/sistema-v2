<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\IndexNotificationRequest;
use App\Models\Notification;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    /**
     * Listar notificacoes do usuario.
     */
    public function index(IndexNotificationRequest $request): JsonResponse
    {
        try {
            $limit = max(1, min(100, (int) $request->input('limit', 30)));

            $notifications = Notification::where('user_id', $request->user()->id)
                ->orderByDesc('created_at')
                ->take($limit)
                ->paginate(min((int) request()->input('per_page', 25), 100));

            $unreadCount = Notification::where('user_id', $request->user()->id)
                ->unread()
                ->count();

            return ApiResponse::data(
                [
                    'notifications' => $notifications->items(),
                    'unread_count' => $unreadCount,
                ],
                extra: [
                    'success' => true,
                    'meta' => [
                        'current_page' => $notifications->currentPage(),
                        'per_page' => $notifications->perPage(),
                        'total' => $notifications->total(),
                        'last_page' => $notifications->lastPage(),
                    ],
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Notification index failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return ApiResponse::data(
                ['notifications' => [], 'unread_count' => 0],
                extra: ['success' => false]
            );
        }
    }

    /**
     * Marcar uma notificacao como lida.
     */
    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        try {
            if ($notification->user_id !== $request->user()->id) {
                return ApiResponse::message('Acesso negado.', 403);
            }

            if (! $notification->read_at) {
                $notification->update(['read_at' => now()]);
            }

            return ApiResponse::data(['notification' => $notification->fresh()], extra: ['success' => true]);
        } catch (\Exception $e) {
            Log::error('Notification markRead failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao marcar notificacao', 500);
        }
    }

    /**
     * Marcar TODAS como lidas.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        try {
            $updated = Notification::where('user_id', $request->user()->id)
                ->unread()
                ->update(['read_at' => now()]);

            return ApiResponse::data(['updated' => $updated], extra: ['success' => true]);
        } catch (\Exception $e) {
            Log::error('Notification markAllRead failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao marcar notificacoes', 500);
        }
    }

    /**
     * Excluir uma notificacao.
     */
    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        try {
            if ($notification->user_id !== $request->user()->id) {
                return ApiResponse::message('Acesso negado.', 403);
            }

            $notification->delete();

            return ApiResponse::noContent();
        } catch (\Exception $e) {
            Log::error('Notification destroy failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir notificacao', 500);
        }
    }

    /**
     * Contar nao lidas (polling leve).
     */
    public function unreadCount(Request $request): JsonResponse
    {
        try {
            $count = Notification::where('user_id', $request->user()->id)
                ->unread()
                ->count();

            return ApiResponse::data(['unread_count' => $count], extra: ['success' => true]);
        } catch (\Throwable $e) {
            Log::error('Notification unreadCount failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return ApiResponse::data(['unread_count' => 0], extra: ['success' => false]);
        }
    }
}
