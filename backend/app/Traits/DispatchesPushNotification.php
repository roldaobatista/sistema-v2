<?php

namespace App\Traits;

use App\Services\WebPushService;
use Illuminate\Support\Facades\Log;

/**
 * Trait para listeners que devem enviar push notifications ao PWA.
 *
 * Uso:
 *   use DispatchesPushNotification;
 *   $this->sendPush($userId, 'Título', 'Corpo da mensagem', ['url' => '/tech/os/123']);
 */
trait DispatchesPushNotification
{
    /**
     * Envia push notification para um usuário específico.
     * Falha silenciosamente (push é best-effort).
     */
    protected function sendPush(int $userId, string $title, string $body, array $data = []): void
    {
        try {
            app(WebPushService::class)->sendToUser($userId, $title, $body, $data);
        } catch (\Throwable $e) {
            Log::warning('[Push] Falha ao enviar push notification', [
                'user_id' => $userId,
                'title' => $title,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envia push notification para todos os usuários de um tenant.
     */
    protected function sendPushToTenant(int $tenantId, string $title, string $body, array $data = []): void
    {
        try {
            app(WebPushService::class)->sendToTenant($tenantId, $title, $body, $data);
        } catch (\Throwable $e) {
            Log::warning('[Push] Falha ao enviar push para tenant', [
                'tenant_id' => $tenantId,
                'title' => $title,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envia push notification para todos os usuários com determinada role num tenant.
     */
    protected function sendPushToRole(int $tenantId, string $role, string $title, string $body, array $data = []): void
    {
        try {
            app(WebPushService::class)->sendToRole($tenantId, $role, $title, $body, $data);
        } catch (\Throwable $e) {
            Log::warning('[Push] Falha ao enviar push para role', [
                'tenant_id' => $tenantId,
                'role' => $role,
                'title' => $title,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
