<?php

namespace App\Services;

use App\Contracts\SmsProviderInterface;
use App\Mail\QuoteReadyMail;
use App\Mail\WorkOrderStatusMail;
use App\Models\Quote;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ClientNotificationService
{
    public function notifyOsCreated(WorkOrder $wo): void
    {
        if (! $this->isEnabled('notify_client_os_created', $wo->tenant_id)) {
            return;
        }

        $customer = $wo->customer;
        if (! $customer) {
            return;
        }

        if ($customer->email && $this->customerWantsChannel($customer, 'email')) {
            $this->sendMail($customer->email, new WorkOrderStatusMail($wo, 'created'));
        }

        if ($this->isWhatsAppEnabled($wo->tenant_id) && $customer->phone && $this->customerWantsChannel($customer, 'whatsapp')) {
            $this->sendWhatsApp(
                $wo->tenant_id,
                $customer->phone,
                "Olá {$customer->name}! Uma nova OS #{$wo->number} foi criada para você. Acompanhe pelo nosso portal."
            );
        }

        if ($this->isSmsEnabled($wo->tenant_id) && $customer->phone && $this->customerWantsChannel($customer, 'sms')) {
            $this->sendSms($wo->tenant_id, $customer->phone, "OS #{$wo->number} criada. Acompanhe pelo portal.");
        }
    }

    public function notifyOsAwaitingApproval(WorkOrder $wo): void
    {
        if (! $this->isEnabled('notify_client_os_awaiting', $wo->tenant_id)) {
            return;
        }

        $customer = $wo->customer;
        if (! $customer) {
            return;
        }

        if ($customer->email) {
            $this->sendMail($customer->email, new WorkOrderStatusMail($wo, 'awaiting_approval'));
        }

        if ($this->isWhatsAppEnabled($wo->tenant_id) && $customer->phone) {
            $this->sendWhatsApp(
                $wo->tenant_id,
                $customer->phone,
                "Olá {$customer->name}! A OS #{$wo->number} está aguardando sua aprovação. Acesse o portal para aprovar ou rejeitar."
            );
        }
    }

    public function notifyOsCompleted(WorkOrder $wo): void
    {
        if (! $this->isEnabled('notify_client_os_completed', $wo->tenant_id)) {
            return;
        }

        $customer = $wo->customer;
        if (! $customer) {
            return;
        }

        if ($customer->email && $this->customerWantsChannel($customer, 'email')) {
            $this->sendMail($customer->email, new WorkOrderStatusMail($wo, 'completed'));
        }

        if ($this->isWhatsAppEnabled($wo->tenant_id) && $customer->phone && $this->customerWantsChannel($customer, 'whatsapp')) {
            $this->sendWhatsApp(
                $wo->tenant_id,
                $customer->phone,
                "Olá {$customer->name}! A OS #{$wo->number} foi concluída. Obrigado pela preferência!"
            );
        }

        if ($this->isSmsEnabled($wo->tenant_id) && $customer->phone && $this->customerWantsChannel($customer, 'sms')) {
            $this->sendSms($wo->tenant_id, $customer->phone, "OS #{$wo->number} concluida. Obrigado pela preferencia!");
        }
    }

    public function notifySignatureRequired(WorkOrder $wo): void
    {
        if (! $this->isEnabled('notify_client_signature_required', $wo->tenant_id)) {
            return;
        }

        $customer = $wo->customer;
        if (! $customer) {
            return;
        }

        $portalUrl = config('app.url')."/portal/os/{$wo->id}/assinatura";

        if ($customer->email) {
            $this->sendMail($customer->email, new WorkOrderStatusMail($wo, 'signature_required'));
        }

        if ($this->isWhatsAppEnabled($wo->tenant_id) && $customer->phone) {
            $this->sendWhatsApp(
                $wo->tenant_id,
                $customer->phone,
                "Olá {$customer->name}! A OS #{$wo->number} foi concluída e precisa da sua assinatura. Acesse o portal para assinar: {$portalUrl}"
            );
        }

        if ($this->isSmsEnabled($wo->tenant_id) && $customer->phone) {
            $this->sendSms($wo->tenant_id, $customer->phone, "OS #{$wo->number} concluida. Assine pelo portal: {$portalUrl}");
        }
    }

    public function notifyQuoteReady(Quote $quote): void
    {
        if (! $this->isEnabled('notify_client_quote_ready', $quote->tenant_id)) {
            return;
        }

        $customer = $quote->customer;
        if (! $customer) {
            return;
        }

        if ($customer->email) {
            $this->sendMail($customer->email, new QuoteReadyMail($quote));
        }

        if ($this->isWhatsAppEnabled($quote->tenant_id) && $customer->phone) {
            $total = number_format($quote->total_amount ?? 0, 2, ',', '.');
            $this->sendWhatsApp(
                $quote->tenant_id,
                $customer->phone,
                "Olá {$customer->name}! Seu orçamento #{$quote->number} (R$ {$total}) está pronto para análise. Acesse o portal para aprovar."
            );
        }
    }

    /**
     * Alert: OS completed without billing.
     */
    public function alertOsWithoutBilling(WorkOrder $wo): void
    {
        if (! $this->isEnabled('alert_os_no_billing', $wo->tenant_id)) {
            return;
        }

        $message = "⚠️ A OS #{$wo->number} foi concluída mas NÃO possui faturamento vinculado. Verifique.";

        $this->sendAdminWhatsApp($wo->tenant_id, $message);
        $this->sendAdminPush($wo->tenant_id, 'OS sem faturamento', $message, ['url' => "/os/{$wo->id}"]);
    }

    /**
     * Alert: Recurring contract expiring soon.
     */
    public function alertContractExpiring(int $tenantId, string $contractNumber, string $customerName, string $expiryDate): void
    {
        if (! $this->isEnabled('alert_contract_expiring', $tenantId)) {
            return;
        }

        $message = "📋 Contrato {$contractNumber} de {$customerName} vence em {$expiryDate}. Providencie a renovação.";

        $this->sendAdminWhatsApp($tenantId, $message);
        $this->sendAdminPush($tenantId, 'Contrato vencendo', $message);
    }

    /**
     * Alert: Standard weight certificate expiring.
     */
    public function alertCertificateExpiring(int $tenantId, string $equipmentName, string $expiryDate): void
    {
        if (! $this->isEnabled('alert_certificate_expiring', $tenantId)) {
            return;
        }

        $message = "⚖️ Certificado do peso padrão '{$equipmentName}' vence em {$expiryDate}. Agende recalibração.";

        $this->sendAdminWhatsApp($tenantId, $message);
        $this->sendAdminPush($tenantId, 'Certificado vencendo', $message);
    }

    // ─── Private helpers ─────────────────────────────────

    private function isEnabled(string $key, int $tenantId): bool
    {
        return (bool) SystemSetting::where('tenant_id', $tenantId)
            ->where('key', $key)
            ->value('value');
    }

    private function isWhatsAppEnabled(int $tenantId): bool
    {
        return $this->isEnabled('whatsapp_enabled', $tenantId)
            && $this->getSetting('evolution_api_url', $tenantId)
            && $this->getSetting('evolution_api_key', $tenantId);
    }

    private function getSetting(string $key, int $tenantId): ?string
    {
        return SystemSetting::where('tenant_id', $tenantId)
            ->where('key', $key)
            ->value('value');
    }

    private function isSmsEnabled(int $tenantId): bool
    {
        return $this->isEnabled('sms_notifications_enabled', $tenantId);
    }

    /**
     * Verifica se o cliente deseja receber notificações por determinado canal.
     * Se notification_preferences não configurado, todos os canais são permitidos (backward compatible).
     */
    private function customerWantsChannel($customer, string $channel): bool
    {
        $prefs = $customer->notification_preferences ?? null;
        if (! is_array($prefs) || empty($prefs)) {
            return true; // sem preferência = aceita tudo
        }

        return in_array($channel, $prefs, true);
    }

    private function sendSms(int $tenantId, string $phone, string $message): void
    {
        try {
            $smsProvider = app(SmsProviderInterface::class);
            $smsProvider->send($phone, $message);
        } catch (\Throwable $e) {
            Log::warning("ClientNotification: Failed to send SMS to {$phone}", [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendMail(string $to, $mailable): void
    {
        try {
            Mail::to($to)->queue($mailable);
        } catch (\Throwable $e) {
            Log::warning("ClientNotification: Failed to send email to {$to}", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendWhatsApp(int $tenantId, string $phone, string $message): void
    {
        try {
            $apiUrl = $this->getSetting('evolution_api_url', $tenantId);
            $apiKey = $this->getSetting('evolution_api_key', $tenantId);
            $instance = $this->getSetting('evolution_instance', $tenantId);

            if (! $apiUrl || ! $apiKey || ! $instance) {
                return;
            }

            $phone = preg_replace('/\D/', '', $phone);
            if (strlen($phone) <= 11) {
                $phone = '55'.$phone;
            }

            Http::timeout(30)->withHeaders(['apikey' => $apiKey])
                ->post(rtrim($apiUrl, '/')."/message/sendText/{$instance}", [
                    'number' => $phone,
                    'text' => $message,
                ]);
        } catch (\Throwable $e) {
            Log::warning('ClientNotification: WhatsApp send failed', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendAdminWhatsApp(int $tenantId, string $message): void
    {
        if (! $this->isWhatsAppEnabled($tenantId)) {
            return;
        }

        $adminPhone = $this->getSetting('company_phone', $tenantId);
        if (! $adminPhone) {
            return;
        }

        $this->sendWhatsApp($tenantId, $adminPhone, $message);
    }

    private function sendAdminPush(int $tenantId, string $title, string $body, array $data = []): void
    {
        try {
            $pushService = app(WebPushService::class);
            $pushService->sendToRole($tenantId, Role::GERENTE, $title, $body, $data);
        } catch (\Throwable $e) {
            Log::warning('ClientNotification: Push send failed', ['error' => $e->getMessage()]);
        }
    }
}
