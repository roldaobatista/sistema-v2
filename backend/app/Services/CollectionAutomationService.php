<?php

namespace App\Services;

use App\Contracts\SmsProviderInterface;
use App\Models\AccountReceivable;
use App\Models\CollectionAction;
use App\Models\CollectionRule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Régua de cobrança automatizada.
 * Envia lembretes antes e depois do vencimento conforme regras configuradas.
 *
 * CollectionRule armazena uma lista de passos (`steps` JSON) — cada passo define
 * `days_offset` e `channel`, e opcionalmente `message_body` customizada. O serviço
 * itera rule × step para disparar ações, usando `step_index` para deduplicação.
 *
 * SMS: use COLLECTION_SMS_DRIVER=twilio e TWILIO_* no .env para envio real; padrão é log.
 */
class CollectionAutomationService
{
    public function __construct(
        private WhatsAppService $whatsApp,
        private ClientNotificationService $notificationService,
        private SmsProviderInterface $smsProvider,
    ) {}

    /**
     * Executa a régua de cobrança para todas as parcelas do tenant.
     */
    public function processForTenant(int $tenantId): array
    {
        $rules = CollectionRule::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        if ($rules->isEmpty()) {
            return ['processed' => 0, 'sent' => 0];
        }

        $receivables = AccountReceivable::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['pending', 'overdue'])
            ->whereNotNull('due_date')
            ->with(['customer', 'customer.contacts'])
            ->get();

        $sent = 0;
        foreach ($receivables as $ar) {
            foreach ($rules as $rule) {
                foreach ($this->normalizeSteps($rule) as $index => $step) {
                    if ($this->shouldTrigger($ar, $step) && ! $this->alreadySent($ar, $rule, $index)) {
                        $this->executeAction($tenantId, $ar, $rule, $index, $step);
                        $sent++;
                    }
                }
            }
        }

        return ['processed' => $receivables->count(), 'sent' => $sent];
    }

    /**
     * Normaliza os passos de uma regra. Ignora passos malformados e
     * garante os campos obrigatórios `days_offset` (int) e `channel` (string).
     *
     * @return array<int, array{days_offset:int, channel:string, message_body?:?string, template_type?:?string}>
     */
    private function normalizeSteps(CollectionRule $rule): array
    {
        $steps = $rule->steps ?? [];
        $normalized = [];
        foreach ($steps as $raw) {
            if (! is_array($raw) || ! array_key_exists('days_offset', $raw)) {
                continue;
            }

            $channel = isset($raw['channel']) && is_string($raw['channel']) ? $raw['channel'] : 'email';
            $normalized[] = [
                'days_offset' => (int) $raw['days_offset'],
                'channel' => $channel,
                'message_body' => $raw['message_body'] ?? null,
                'template_type' => $raw['template_type'] ?? null,
            ];
        }

        // Ordenar por days_offset para que lembretes pré-vencimento rodem antes
        usort($normalized, fn ($a, $b) => $a['days_offset'] <=> $b['days_offset']);

        return $normalized;
    }

    /**
     * @param  array{days_offset:int, channel:string}  $step
     */
    private function shouldTrigger(AccountReceivable $ar, array $step): bool
    {
        $targetDate = $ar->due_date->copy()->addDays($step['days_offset']);

        return now()->isSameDay($targetDate);
    }

    private function alreadySent(AccountReceivable $ar, CollectionRule $rule, int $stepIndex): bool
    {
        return CollectionAction::where('collection_rule_id', $rule->id)
            ->where('account_receivable_id', $ar->id)
            ->where('step_index', $stepIndex)
            ->whereIn('status', ['sent', 'delivered'])
            ->exists();
    }

    /**
     * @param  array{days_offset:int, channel:string, message_body?:?string}  $step
     */
    private function executeAction(int $tenantId, AccountReceivable $ar, CollectionRule $rule, int $stepIndex, array $step): void
    {
        $customer = $ar->customer;
        if (! $customer) {
            return;
        }

        $message = $this->buildMessage($ar, $step);
        $channel = $step['channel'];

        $action = CollectionAction::create([
            'tenant_id' => $tenantId,
            'collection_rule_id' => $rule->id,
            'account_receivable_id' => $ar->id,
            'step_index' => $stepIndex,
            'channel' => $channel,
            'status' => 'pending',
            'scheduled_at' => now(),
        ]);

        // Fallback order: preferred channel → alternatives
        $fallbackOrder = match ($channel) {
            'whatsapp' => ['whatsapp', 'email', 'sms'],
            'sms' => ['sms', 'whatsapp', 'email'],
            default => ['email', 'whatsapp', 'sms'],
        };

        try {
            $actuallySent = false;
            $usedChannel = $channel;

            foreach ($fallbackOrder as $tryChannel) {
                $result = match ($tryChannel) {
                    'whatsapp' => $this->sendViaWhatsApp($tenantId, $customer, $message, $ar),
                    'email' => $this->sendViaEmail($customer, $message, $ar),
                    'sms' => $this->sendViaSms($customer, $message),
                    default => null,
                };

                if ($result === true) {
                    $actuallySent = true;
                    $usedChannel = $tryChannel;
                    break;
                }
            }

            if ($actuallySent) {
                $fallbackNote = $usedChannel !== $channel ? " (fallback de {$channel} → {$usedChannel})" : '';
                $action->update(['status' => 'sent', 'sent_at' => now(), 'channel' => $usedChannel, 'response' => $fallbackNote ?: null]);
            } else {
                $action->update(['status' => 'skipped', 'response' => 'Nenhum canal de contato disponível para o cliente.']);
            }
        } catch (\Throwable $e) {
            $action->update(['status' => 'failed', 'response' => $e->getMessage()]);
            Log::error("Collection automation failed: {$e->getMessage()}");
        }
    }

    /**
     * @param  array{days_offset:int, message_body?:?string}  $step
     */
    private function buildMessage(AccountReceivable $ar, array $step): string
    {
        // Se o passo tem mensagem customizada, aplica interpolação simples
        if (! empty($step['message_body'])) {
            return $this->interpolate($step['message_body'], $ar);
        }

        $customer = $ar->customer;
        $offset = $step['days_offset'];
        $amountFmt = number_format((float) $ar->amount, 2, ',', '.');

        if ($offset < 0) {
            $daysUntil = abs($offset);

            return "Olá, {$customer->name}! Lembrete: a parcela de R$ {$amountFmt}".
                " vence em {$daysUntil} dia(s) ({$ar->due_date->format('d/m/Y')}). Em caso de dúvidas, entre em contato.";
        }

        if ($offset === 0) {
            return "Olá, {$customer->name}! A parcela de R$ {$amountFmt}".
                " vence hoje ({$ar->due_date->format('d/m/Y')}). Caso já tenha efetuado o pagamento, desconsidere.";
        }

        $daysOverdue = $ar->due_date->isPast() ? now()->diffInDays($ar->due_date) : 0;

        return "Olá, {$customer->name}. Identificamos que a parcela de R$ {$amountFmt}".
            " com vencimento em {$ar->due_date->format('d/m/Y')} encontra-se em atraso há {$daysOverdue} dia(s). ".
            'Pedimos a gentileza de regularizar. Caso já tenha pago, desconsidere.';
    }

    private function interpolate(string $template, AccountReceivable $ar): string
    {
        return strtr($template, [
            '{{customer}}' => (string) ($ar->customer->name ?? ''),
            '{{amount}}' => number_format((float) $ar->amount, 2, ',', '.'),
            '{{due_date}}' => $ar->due_date->format('d/m/Y'),
        ]);
    }

    private function sendViaWhatsApp(int $tenantId, $customer, string $message, $ar): bool
    {
        $phone = $customer->phone ?? $customer->contacts->first()?->phone;
        if ($phone) {
            $this->whatsApp->sendText($tenantId, $phone, $message, $ar);

            return true;
        }

        return false;
    }

    private function sendViaEmail($customer, string $message, $ar): bool
    {
        $email = $customer->email ?? $customer->contacts->first()?->email;
        if ($email) {
            Mail::raw($message, function ($m) use ($email, $ar) {
                $m->to($email)->subject('Lembrete de pagamento — Parcela R$ '.number_format((float) $ar->amount, 2, ',', '.'));
            });

            return true;
        }

        return false;
    }

    /**
     * Envia SMS via provedor configurado (COLLECTION_SMS_DRIVER=log|twilio).
     */
    private function sendViaSms($customer, string $message): bool
    {
        $phone = $customer->phone ?? $customer->contacts->first()?->phone;
        if (! $phone) {
            Log::warning('Collection SMS skipped: customer has no phone', ['customer_id' => $customer->id]);

            return false;
        }

        return $this->smsProvider->send($phone, $message);
    }
}
