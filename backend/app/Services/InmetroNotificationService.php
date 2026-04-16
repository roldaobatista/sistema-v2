<?php

namespace App\Services;

use App\Models\InmetroBaseConfig;
use App\Models\InmetroCompetitor;
use App\Models\InmetroInstrument;
use App\Models\Notification;
use App\Models\User;
use App\Support\BrazilPhone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class InmetroNotificationService
{
    private const DEFAULT_WHATSAPP_MSG = "Olá, {owner_name}! Somos da {company_name}, empresa permissionária do INMETRO.\n\nIdentificamos que o equipamento {instrument_type} (Nº {instrument_number}) localizado em {city} foi REPROVADO na última verificação metrológica.\n\nPrecisamos agendar o reparo e nova verificação o mais rápido possível para regularizar a situação.\n\nPodemos ajudar! Temos disponibilidade para atendimento imediato.\n\nAguardamos seu retorno. 🔧";

    private const DEFAULT_EMAIL_SUBJECT = 'URGENTE: Equipamento Reprovado — {instrument_type} Nº {instrument_number}';

    private const DEFAULT_EMAIL_BODY = "<h2>Comunicado de Reprovação Metrológica</h2><p>Prezado(a) <strong>{owner_name}</strong>,</p><p>Informamos que o equipamento abaixo foi <strong style='color:red'>REPROVADO</strong> na última verificação realizada pelo INMETRO:</p><table style='border-collapse:collapse;width:100%'><tr><td style='padding:8px;border:1px solid #ddd'><strong>Tipo</strong></td><td style='padding:8px;border:1px solid #ddd'>{instrument_type}</td></tr><tr><td style='padding:8px;border:1px solid #ddd'><strong>Nº Inmetro</strong></td><td style='padding:8px;border:1px solid #ddd'>{instrument_number}</td></tr><tr><td style='padding:8px;border:1px solid #ddd'><strong>Marca/Modelo</strong></td><td style='padding:8px;border:1px solid #ddd'>{brand} {model}</td></tr><tr><td style='padding:8px;border:1px solid #ddd'><strong>Localização</strong></td><td style='padding:8px;border:1px solid #ddd'>{city}</td></tr></table><p>É necessário realizar o <strong>reparo e nova verificação</strong> para regularizar o equipamento junto ao INMETRO.</p><p>Nossa empresa está à disposição para agendamento imediato.</p><p>Atenciosamente,<br>{company_name}</p>";

    /**
     * Check for recently rejected instruments and create urgent notifications with WhatsApp + email.
     */
    public function checkAndNotifyRejections(int $tenantId): int
    {
        $count = 0;
        $config = InmetroBaseConfig::where('tenant_id', $tenantId)->first();

        $rejectedInstruments = InmetroInstrument::query()
            ->join('inmetro_locations', 'inmetro_instruments.location_id', '=', 'inmetro_locations.id')
            ->join('inmetro_owners', 'inmetro_locations.owner_id', '=', 'inmetro_owners.id')
            ->where('inmetro_owners.tenant_id', $tenantId)
            ->where('inmetro_instruments.current_status', 'rejected')
            ->select(
                'inmetro_instruments.*',
                'inmetro_owners.name as owner_name',
                'inmetro_owners.id as owner_id',
                'inmetro_owners.document as owner_document',
                'inmetro_owners.phone as owner_phone',
                'inmetro_owners.phone2 as owner_phone2',
                'inmetro_owners.email as owner_email',
                'inmetro_owners.lead_status',
                'inmetro_locations.address_city',
                'inmetro_locations.address_street',
                'inmetro_locations.address_state'
            )
            ->get();

        foreach ($rejectedInstruments as $instrument) {
            $alreadyNotified = Notification::where('tenant_id', $tenantId)
                ->where('type', 'inmetro_rejection')
                ->where('notifiable_type', 'inmetro_instrument')
                ->where('notifiable_id', $instrument->id)
                ->where('created_at', '>=', now()->subDays(3))
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            // Build WhatsApp link
            $whatsappLink = $this->buildWhatsappLink($instrument, $config);

            // Build email draft
            $emailDraft = $this->buildEmailDraft($instrument, $config);

            // Get target users by roles
            $users = $this->getNotificationTargetUsers($tenantId, $config);

            foreach ($users as $user) {
                Notification::create([
                    'tenant_id' => $tenantId,
                    'user_id' => $user->id,
                    'type' => 'inmetro_rejection',
                    'title' => "🔴 REPROVADO: {$instrument->owner_name}",
                    'message' => "Instrumento {$instrument->inmetro_number} ({$instrument->instrument_type}) foi REPROVADO pelo INMETRO.\n"
                        ."Cidade: {$instrument->address_city}\n"
                        ."Marca: {$instrument->brand} | Modelo: {$instrument->model}\n"
                        ."Telefone: {$instrument->owner_phone}\n"
                        .'AÇÃO: Contato IMEDIATO — cliente precisa de reparo!',
                    'icon' => 'alert-octagon',
                    'color' => 'red',
                    'notifiable_type' => 'inmetro_instrument',
                    'notifiable_id' => $instrument->id,
                    'data' => [
                        'priority' => 'critical',
                        'owner_id' => $instrument->owner_id,
                        'owner_name' => $instrument->owner_name,
                        'owner_document' => $instrument->owner_document,
                        'owner_phone' => $instrument->owner_phone,
                        'owner_phone2' => $instrument->owner_phone2,
                        'owner_email' => $instrument->owner_email,
                        'instrument_number' => $instrument->inmetro_number,
                        'instrument_type' => $instrument->instrument_type,
                        'brand' => $instrument->brand,
                        'model' => $instrument->model,
                        'city' => $instrument->address_city,
                        'address' => $instrument->address_street,
                        'state' => $instrument->address_state,
                        'whatsapp_link' => $whatsappLink,
                        'email_draft' => $emailDraft,
                    ],
                ]);
            }

            if ($users->isNotEmpty()) {
                $count++;
            }
        }

        Log::info('INMETRO rejection notifications', ['tenant' => $tenantId, 'count' => $count]);

        return $count;
    }

    /**
     * Check for instruments expiring soon and create tiered notifications.
     */
    public function checkAndNotifyExpirations(int $tenantId): int
    {
        $count = 0;
        $config = InmetroBaseConfig::where('tenant_id', $tenantId)->first();

        $thresholds = [
            ['days' => 7, 'emoji' => '🔴', 'priority' => 'critical', 'icon' => 'alert-octagon', 'color' => 'red', 'action' => 'Contato IMEDIATO — vencimento em dias!', 'cooldown' => 3],
            ['days' => 30, 'emoji' => '🟠', 'priority' => 'urgent', 'icon' => 'alert-triangle', 'color' => 'orange', 'action' => 'Agendar visita urgente.', 'cooldown' => 10],
            ['days' => 60, 'emoji' => '🟡', 'priority' => 'high', 'icon' => 'alert-circle', 'color' => 'yellow', 'action' => 'Iniciar contato e enviar proposta.', 'cooldown' => 20],
            ['days' => 90, 'emoji' => '🔵', 'priority' => 'normal', 'icon' => 'clipboard', 'color' => 'blue', 'action' => 'Preparar proposta comercial.', 'cooldown' => 30],
        ];

        foreach ($thresholds as $threshold) {
            $instruments = InmetroInstrument::query()
                ->join('inmetro_locations', 'inmetro_instruments.location_id', '=', 'inmetro_locations.id')
                ->join('inmetro_owners', 'inmetro_locations.owner_id', '=', 'inmetro_owners.id')
                ->where('inmetro_owners.tenant_id', $tenantId)
                ->whereNull('inmetro_owners.converted_to_customer_id')
                ->where('inmetro_instruments.current_status', '!=', 'rejected')
                ->whereNotNull('inmetro_instruments.next_verification_at')
                ->where('inmetro_instruments.next_verification_at', '<=', now()->addDays($threshold['days']))
                ->where('inmetro_instruments.next_verification_at', '>', $threshold['days'] === 7 ? now()->subDays(365) : now()->addDays($threshold['days'] - 30))
                ->select(
                    'inmetro_instruments.*',
                    'inmetro_owners.name as owner_name',
                    'inmetro_owners.id as owner_id',
                    'inmetro_owners.phone as owner_phone',
                    'inmetro_locations.address_city'
                )
                ->get();

            foreach ($instruments as $instrument) {
                $daysLeft = (int) now()->startOfDay()->diffInDays($instrument->next_verification_at, false);

                $alreadyNotified = Notification::where('tenant_id', $tenantId)
                    ->where('type', 'inmetro_expiration')
                    ->where('notifiable_type', 'inmetro_instrument')
                    ->where('notifiable_id', $instrument->id)
                    ->where('created_at', '>=', now()->subDays($threshold['cooldown']))
                    ->exists();

                if ($alreadyNotified) {
                    continue;
                }

                $title = $daysLeft <= 0
                    ? "{$threshold['emoji']} VENCIDO: {$instrument->owner_name}"
                    : "{$threshold['emoji']} Vence em {$daysLeft}d: {$instrument->owner_name}";

                $whatsappLink = $this->buildWhatsappLink($instrument, $config, 'expiration');
                $users = $this->getNotificationTargetUsers($tenantId, $config);

                foreach ($users as $user) {
                    Notification::create([
                        'tenant_id' => $tenantId,
                        'user_id' => $user->id,
                        'type' => 'inmetro_expiration',
                        'title' => $title,
                        'message' => "Instrumento {$instrument->inmetro_number} ({$instrument->instrument_type})\n"
                            ."Cidade: {$instrument->address_city}\n"
                            ."{$threshold['action']}",
                        'icon' => $threshold['icon'],
                        'color' => $threshold['color'],
                        'notifiable_type' => 'inmetro_instrument',
                        'notifiable_id' => $instrument->id,
                        'data' => [
                            'priority' => $threshold['priority'],
                            'days_left' => $daysLeft,
                            'owner_id' => $instrument->owner_id,
                            'owner_phone' => $instrument->owner_phone,
                            'whatsapp_link' => $whatsappLink,
                        ],
                    ]);
                }

                if ($users->isNotEmpty()) {
                    $count++;
                }
            }
        }

        Log::info('INMETRO expiration notifications', ['tenant' => $tenantId, 'count' => $count]);

        return $count;
    }

    /**
     * Check for new competitors.
     */
    public function checkAndNotifyNewCompetitors(int $tenantId): int
    {
        $count = 0;
        $config = InmetroBaseConfig::where('tenant_id', $tenantId)->first();

        $newCompetitors = InmetroCompetitor::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDay())
            ->get();

        foreach ($newCompetitors as $competitor) {
            $alreadyNotified = Notification::where('tenant_id', $tenantId)
                ->where('type', 'inmetro_new_competitor')
                ->where('notifiable_type', 'inmetro_competitor')
                ->where('notifiable_id', $competitor->id)
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            $species = is_array($competitor->authorized_species) ? implode(', ', $competitor->authorized_species) : '';
            $users = $this->getNotificationTargetUsers($tenantId, $config);

            foreach ($users as $user) {
                Notification::create([
                    'tenant_id' => $tenantId,
                    'user_id' => $user->id,
                    'type' => 'inmetro_new_competitor',
                    'title' => "⚠️ Novo concorrente: {$competitor->name}",
                    'message' => "Nova oficina autorizada detectada em {$competitor->city}/{$competitor->state}.\n"
                        ."CNPJ: {$competitor->cnpj}\n"
                        ."Espécies: {$species}\n"
                        ."Autorização: {$competitor->authorization_number}",
                    'icon' => 'user-plus',
                    'color' => 'orange',
                    'notifiable_type' => 'inmetro_competitor',
                    'notifiable_id' => $competitor->id,
                    'data' => [
                        'competitor_id' => $competitor->id,
                        'city' => $competitor->city,
                        'state' => $competitor->state,
                    ],
                ]);
            }

            if ($users->isNotEmpty()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Run all notification checks after a sync.
     */
    public function runAllChecks(int $tenantId): array
    {
        return [
            'rejections' => $this->checkAndNotifyRejections($tenantId),
            'expirations' => $this->checkAndNotifyExpirations($tenantId),
            'new_competitors' => $this->checkAndNotifyNewCompetitors($tenantId),
        ];
    }

    // ─── Private Helpers ───

    private function buildWhatsappLink(object $instrument, ?InmetroBaseConfig $config, string $type = 'rejection'): ?string
    {
        $phone = $instrument->owner_phone ?? $instrument->owner_phone2 ?? null;
        if (! $phone) {
            return null;
        }

        $cleanPhone = BrazilPhone::whatsappDigits($phone);
        if ($cleanPhone === null) {
            return null;
        }

        $template = $config?->whatsapp_message_template ?? self::DEFAULT_WHATSAPP_MSG;

        $companyName = $config?->tenant?->name ?? 'Nossa Empresa';
        $message = str_replace(
            ['{owner_name}', '{company_name}', '{instrument_type}', '{instrument_number}', '{city}', '{brand}', '{model}'],
            [
                $instrument->owner_name ?? 'Cliente',
                $companyName,
                $instrument->instrument_type ?? 'Equipamento',
                $instrument->inmetro_number ?? '',
                $instrument->address_city ?? '',
                $instrument->brand ?? '',
                $instrument->model ?? '',
            ],
            $template
        );

        return "https://wa.me/{$cleanPhone}?text=".urlencode($message);
    }

    private function buildEmailDraft(object $instrument, ?InmetroBaseConfig $config): array
    {
        $companyName = $config?->tenant?->name ?? 'Nossa Empresa';

        $replacements = [
            '{owner_name}' => $instrument->owner_name ?? 'Cliente',
            '{company_name}' => $companyName,
            '{instrument_type}' => $instrument->instrument_type ?? 'Equipamento',
            '{instrument_number}' => $instrument->inmetro_number ?? '',
            '{city}' => $instrument->address_city ?? '',
            '{brand}' => $instrument->brand ?? '',
            '{model}' => $instrument->model ?? '',
        ];

        $subject = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $config?->email_subject_template ?? self::DEFAULT_EMAIL_SUBJECT
        );

        $body = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $config?->email_body_template ?? self::DEFAULT_EMAIL_BODY
        );

        return [
            'to' => $instrument->owner_email ?? null,
            'subject' => $subject,
            'body' => $body,
        ];
    }

    private function getNotificationTargetUsers(int $tenantId, ?InmetroBaseConfig $config): Collection
    {
        $roles = $config?->notification_roles ?? ['admin', 'comercial', 'secretaria', 'vendedor'];

        // Try to filter by roles using Spatie
        $query = User::where('tenant_id', $tenantId);

        if (method_exists(User::class, 'role')) {
            return $query->role($roles)->get();
        }

        // Fallback: all users of tenant
        return $query->get();
    }
}
