<?php

namespace App\Services;

use App\Models\SatisfactionSurvey;
use App\Models\Tenant;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PostServiceSurveyService
{
    public function __construct(
        private ?WhatsAppService $whatsApp = null,
        private ?ClientNotificationService $notification = null,
    ) {}

    /**
     * Envia pesquisa de satisfação para OS concluídas nas últimas 24h que ainda não têm survey.
     */
    public function processForTenant(Tenant $tenant): int
    {
        $sent = 0;

        $workOrders = WorkOrder::where('tenant_id', $tenant->id)
            ->where('status', WorkOrder::STATUS_COMPLETED)
            ->where('completed_at', '>=', now()->subDay())
            ->whereDoesntHave('satisfactionSurvey')
            ->with(['customer'])
            ->get();

        foreach ($workOrders as $wo) {
            if (! $wo->customer) {
                continue;
            }

            try {
                $customer = $wo->customer;

                // Cria o registro primeiro para obter o ID real e montar a URL correta
                $survey = SatisfactionSurvey::create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $wo->customer_id,
                    'work_order_id' => $wo->id,
                    'channel' => 'pending',
                ]);

                $surveyUrl = config('app.frontend_url')."/portal/pesquisa/{$survey->id}?token=".encrypt($survey->id);
                $message = "Olá {$customer->name}! Sua OS #{$wo->number} foi concluída. ".
                    "Avalie nosso atendimento: {$surveyUrl}";

                $sentChannel = null;

                // 1) Tenta WhatsApp
                if ($this->whatsApp && $customer->phone) {
                    try {
                        $this->whatsApp->sendText($tenant->id, $customer->phone, $message);
                        $sentChannel = 'whatsapp';
                    } catch (\Throwable $waEx) {
                        Log::warning("Survey WhatsApp falhou para OS #{$wo->id}: {$waEx->getMessage()}");
                    }
                }

                // 2) Fallback: email
                if (! $sentChannel && $customer->email) {
                    try {
                        Mail::raw($message, function ($m) use ($customer) {
                            $m->to($customer->email)
                                ->subject('Avalie nosso atendimento — Pesquisa de Satisfação');
                        });
                        $sentChannel = 'email';
                    } catch (\Throwable $mailEx) {
                        Log::warning("Survey email falhou para OS #{$wo->id}: {$mailEx->getMessage()}");
                    }
                }

                if (! $sentChannel) {
                    Log::warning("Survey não enviada para OS #{$wo->id}: cliente sem WhatsApp nem email. Survey ID={$survey->id} aguarda reenvio manual.");
                    $survey->update(['channel' => 'failed']);
                    continue;
                }

                $survey->update(['channel' => $sentChannel]);

                Log::info("Pesquisa de satisfação enviada para OS #{$wo->id} via {$sentChannel}", [
                    'tenant_id' => $tenant->id,
                    'customer_id' => $wo->customer_id,
                    'survey_id' => $survey->id,
                ]);

                $sent++;
            } catch (\Throwable $e) {
                Log::warning("Falha ao processar pesquisa para OS #{$wo->id}: {$e->getMessage()}");
            }
        }

        return $sent;
    }
}
