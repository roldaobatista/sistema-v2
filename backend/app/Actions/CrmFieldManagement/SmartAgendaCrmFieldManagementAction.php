<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\ContactPolicy;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Quote;
use App\Models\User;

class SmartAgendaCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $userId = isset($data['user_id']) ? $data['user_id'] : $user->id;

        $policies = ContactPolicy::where('tenant_id', $tenantId)->active()->get();

        $customers = Customer::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($q) use ($userId) {
                $q->where('assigned_seller_id', $userId)->orWhereNull('assigned_seller_id');
            })
            ->select('id', 'name', 'rating', 'health_score', 'last_contact_at',
                'next_follow_up_at', 'segment', 'address_city', 'latitude', 'longitude')
            ->get();

        $suggestions = $customers->map(function ($c) use ($policies) {
            $daysSinceContact = $c->last_contact_at ? (int) $c->last_contact_at->diffInDays(now()) : 999;
            $policy = $policies->first(function ($p) use ($c) {
                return $p->target_type === 'all'
                    || ($p->target_type === 'rating' && $p->target_value === $c->rating)
                    || ($p->target_type === 'segment' && $p->target_value === $c->segment);
            });
            $maxDays = $policy ? $policy->max_days_without_contact : 90;
            $daysUntilDue = $maxDays - $daysSinceContact;

            $score = 0;
            if ($daysUntilDue <= 0) {
                $score += 100;
            } elseif ($daysUntilDue <= 7) {
                $score += 80;
            } elseif ($daysUntilDue <= 14) {
                $score += 50;
            }
            if ($c->rating === 'A') {
                $score += 30;
            } elseif ($c->rating === 'B') {
                $score += 20;
            }
            if ($c->health_score && $c->health_score < 50) {
                $score += 20;
            }

            $hasCalibrationExpiring = Equipment::where('customer_id', $c->id)
                ->whereNotNull('next_calibration_at')
                ->where('next_calibration_at', '<=', now()->addDays(30))
                ->exists();
            if ($hasCalibrationExpiring) {
                $score += 25;
            }

            $hasPendingQuote = Quote::where('customer_id', $c->id)
                ->where('status', 'pending')
                ->exists();
            if ($hasPendingQuote) {
                $score += 15;
            }

            $c->setAttribute('priority_score', $score);
            $c->setAttribute('days_since_contact', $daysSinceContact);
            $c->setAttribute('max_days_allowed', $maxDays);
            $c->setAttribute('days_until_due', $daysUntilDue);
            $c->setAttribute('has_calibration_expiring', $hasCalibrationExpiring);
            $c->setAttribute('has_pending_quote', $hasPendingQuote);
            $c->setAttribute('suggested_action', $daysUntilDue <= 0 ? 'Contato urgente' :
                ($hasCalibrationExpiring ? 'Oportunidade calibração' :
                ($hasPendingQuote ? 'Follow-up orçamento' : 'Contato de manutenção')));

            return $c;
        })
            ->filter(fn ($c) => (int) $c->getAttribute('priority_score') > 0)
            ->sortByDesc('priority_score')
            ->values()
            ->take(30);

        return $suggestions;
    }
}
