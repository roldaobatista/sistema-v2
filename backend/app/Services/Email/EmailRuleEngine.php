<?php

namespace App\Services\Email;

use App\Enums\AgendaItemOrigin;
use App\Enums\AgendaItemPriority;
use App\Enums\AgendaItemType;
use App\Enums\ServiceCallStatus;
use App\Models\AgendaItem;
use App\Models\Email;
use App\Models\EmailRule;
use App\Models\Notification;
use App\Models\ServiceCall;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class EmailRuleEngine
{
    public function apply(Email $email): array
    {
        $appliedActions = [];

        $rules = EmailRule::where('tenant_id', $email->tenant_id)
            ->active()
            ->get();

        foreach ($rules as $rule) {
            if ($rule->matchesEmail($email)) {
                foreach ($rule->actions as $action) {
                    try {
                        $result = $this->executeAction($action, $email);
                        if ($result) {
                            $appliedActions[] = [
                                'rule_id' => $rule->id,
                                'rule_name' => $rule->name,
                                'action' => $action['type'],
                                'result' => $result,
                            ];
                        }
                    } catch (\Exception $e) {
                        Log::warning('Email rule action failed', [
                            'rule_id' => $rule->id,
                            'action' => $action['type'],
                            'email_id' => $email->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        if (! empty($appliedActions)) {
            Log::info('Email rules applied', [
                'email_id' => $email->id,
                'actions_count' => count($appliedActions),
            ]);
        }

        return $appliedActions;
    }

    private function executeAction(array $action, Email $email): ?string
    {
        $type = $action['type'] ?? '';
        $params = $action['params'] ?? [];

        return match ($type) {
            'assign_category' => $this->actionAssignCategory($email, $params),
            'create_task' => $this->actionCreateTask($email, $params),
            'create_chamado' => $this->actionCreateChamado($email, $params),
            'star' => $this->actionStar($email),
            'archive' => $this->actionArchive($email),
            'notify_user' => $this->actionNotifyUser($email, $params),
            'mark_read' => $this->actionMarkRead($email),
            default => null,
        };
    }

    private function actionAssignCategory(Email $email, array $params): string
    {
        $category = $params['category'] ?? 'outro';
        $email->update(['ai_category' => $category]);

        return "Category set to: {$category}";
    }

    private function actionCreateTask(Email $email, array $params): string
    {
        $responsavelId = $params['user_id']
            ?? User::where('tenant_id', $email->tenant_id)->first()?->id;

        $priority = match ($email->ai_priority) {
            'alta' => AgendaItemPriority::ALTA,
            'baixa' => AgendaItemPriority::BAIXA,
            default => AgendaItemPriority::MEDIA,
        };

        $item = AgendaItem::criarDeOrigem(
            $email,
            AgendaItemType::TAREFA,
            "[Email] {$email->subject}",
            $responsavelId,
            [
                'origin' => AgendaItemOrigin::AUTO,
                'priority' => $priority,
                'short_description' => $email->ai_summary ?? $email->snippet,
                'context' => [
                    'email_id' => $email->id,
                    'from' => $email->from_address,
                    'date' => $email->date->toISOString(),
                ],
            ]
        );

        return "Task created: #{$item->id}";
    }

    private function actionCreateChamado(Email $email, array $params): string
    {
        if (empty($email->customer_id)) {
            return 'Email sem cliente vinculado para criar chamado';
        }

        $data = [
            'tenant_id' => $email->tenant_id,
            'call_number' => ServiceCall::nextNumber((int) $email->tenant_id),
            'customer_id' => $email->customer_id,
            'created_by' => $email->assigned_to_user_id,
            'status' => ServiceCallStatus::PENDING_SCHEDULING->value,
            'priority' => $this->mapEmailPriority($email->ai_priority),
            'observations' => $this->buildEmailObservations($email),
        ];

        if (Schema::hasColumn('service_calls', 'source')) {
            $data['source'] = 'email';
        }

        $chamado = ServiceCall::create($data);

        $email->update([
            'linked_type' => ServiceCall::class,
            'linked_id' => $chamado->id,
        ]);

        return "Chamado created: #{$chamado->id}";
    }

    private function mapEmailPriority(?string $priority): string
    {
        return match (strtolower((string) $priority)) {
            'alta', 'high' => 'high',
            'baixa', 'low' => 'low',
            'urgente', 'urgent' => 'urgent',
            default => 'normal',
        };
    }

    private function buildEmailObservations(Email $email): string
    {
        $summary = trim((string) ($email->ai_summary ?? ''));
        $body = trim((string) ($email->body_text ?? strip_tags($email->body_html ?? '')));

        $parts = array_filter([
            "[Email] {$email->subject}",
            $summary !== '' ? "Resumo IA: {$summary}" : null,
            $body !== '' ? "Conteudo: {$body}" : null,
            "Remetente: {$email->from_address}",
        ]);

        return implode("\n\n", $parts);
    }

    private function actionStar(Email $email): string
    {
        $email->update(['is_starred' => true]);

        return 'Starred';
    }

    private function actionArchive(Email $email): string
    {
        $email->update(['is_archived' => true]);

        return 'Archived';
    }

    private function actionMarkRead(Email $email): string
    {
        $email->update(['is_read' => true, 'status' => 'read']);

        return 'Marked as read';
    }

    private function actionNotifyUser(Email $email, array $params): string
    {
        $userId = $params['user_id'] ?? null;
        if (! $userId) {
            return 'No user specified for notification';
        }

        Notification::notify(
            (int) $email->tenant_id,
            (int) $userId,
            'email_rule_triggered',
            'Novo email relevante',
            [
                'message' => "De: {$email->from_address} — {$email->subject}",
                'icon' => 'mail',
                'color' => 'blue',
                'link' => "/email?id={$email->id}",
                'data' => [
                    'email_id' => $email->id,
                    'category' => $email->ai_category,
                ],
            ]
        );

        return "Notified user #{$userId}";
    }
}
