<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Push\SubscribePushRequest;
use App\Http\Requests\Push\UnsubscribePushRequest;
use App\Models\PushSubscription;
use App\Services\WebPushService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PushSubscriptionController extends Controller
{
    /**
     * Subscribe to push notifications.
     */
    public function subscribe(SubscribePushRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            $subscription = PushSubscription::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'endpoint' => $request->input('endpoint'),
                ],
                [
                    'tenant_id' => $user->current_tenant_id,
                    'p256dh_key' => $request->input('keys.p256dh'),
                    'auth_key' => $request->input('keys.auth'),
                    'user_agent' => $request->userAgent(),
                ]
            );

            return ApiResponse::data(['id' => $subscription->id], 201, ['message' => 'Inscrição de push registrada com sucesso']);
        } catch (\Exception $e) {
            Log::error('Push subscribe failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar inscrição', 500);
        }
    }

    /**
     * Unsubscribe from push notifications.
     */
    public function unsubscribe(UnsubscribePushRequest $request): JsonResponse
    {
        $deleted = PushSubscription::where('user_id', $request->user()->id)
            ->where('endpoint', $request->input('endpoint'))
            ->delete();

        if ($deleted) {
            return ApiResponse::message('Inscrição removida com sucesso');
        }

        return ApiResponse::message('Inscrição não encontrada', 404);
    }

    /**
     * Send a test push notification.
     */
    public function test(Request $request, WebPushService $pushService): JsonResponse
    {
        $user = $request->user();

        $sent = $pushService->sendToUser(
            $user->id,
            'Notificação de teste',
            'Esta é uma notificação de teste do sistema Kalibrium.',
            ['type' => 'test', 'url' => '/']
        );

        return ApiResponse::message(
            $sent > 0
                ? "Notificação enviada para {$sent} dispositivo(s)"
                : 'Nenhum dispositivo inscrito encontrado',
            200,
            ['sent' => $sent]
        );
    }

    /**
     * Get VAPID public key for the frontend.
     */
    public function vapidKey(): JsonResponse
    {
        $publicKey = config('services.webpush.public_key');

        if (! $publicKey) {
            return ApiResponse::message('VAPID key não configurada', 404);
        }

        return ApiResponse::data(['publicKey' => $publicKey]);
    }
}
