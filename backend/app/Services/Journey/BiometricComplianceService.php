<?php

namespace App\Services\Journey;

use App\Models\BiometricConsent;
use App\Models\User;

class BiometricComplianceService
{
    public function hasActiveConsent(User $user, string $dataType, ?int $tenantId = null): bool
    {
        $tenantId = $this->tenantIdFor($user, $tenantId);

        $consent = BiometricConsent::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->where('data_type', $dataType)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->latest('consented_at')
            ->first();

        return $consent && $consent->isActive();
    }

    public function getAlternativeMethod(User $user, string $dataType, ?int $tenantId = null): ?string
    {
        $tenantId = $this->tenantIdFor($user, $tenantId);

        $consent = BiometricConsent::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->where('data_type', $dataType)
            ->latest()
            ->first();

        return $consent?->alternative_method;
    }

    public function grantConsent(
        User $user,
        string $dataType,
        string $legalBasis,
        string $purpose,
        ?string $alternativeMethod = null,
        int $retentionDays = 365,
        ?int $tenantId = null,
    ): BiometricConsent {
        $tenantId = $this->tenantIdFor($user, $tenantId);

        // Revoke previous active consent for same type
        BiometricConsent::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->where('data_type', $dataType)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return BiometricConsent::withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'data_type' => $dataType,
            'legal_basis' => $legalBasis,
            'purpose' => $purpose,
            'consented_at' => now(),
            'alternative_method' => $alternativeMethod,
            'retention_days' => $retentionDays,
            'is_active' => true,
        ]);
    }

    public function revokeConsent(User $user, string $dataType, ?int $tenantId = null): bool
    {
        $tenantId = $this->tenantIdFor($user, $tenantId);

        $consent = BiometricConsent::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->where('data_type', $dataType)
            ->where('is_active', true)
            ->first();

        if (! $consent) {
            return false;
        }

        $consent->revoke();

        return true;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getConsentsForUser(User $user, ?int $tenantId = null): array
    {
        $tenantId = $this->tenantIdFor($user, $tenantId);

        $consents = BiometricConsent::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->orderByDesc('consented_at')
            ->get();

        $result = [];
        foreach (['geolocation', 'facial', 'fingerprint', 'voice'] as $type) {
            $latest = $consents->where('data_type', $type)->first();
            $result[$type] = [
                'has_consent' => $latest && $latest->isActive(),
                'consent' => $latest,
            ];
        }

        return $result;
    }

    public function purgeExpiredData(int $tenantId): int
    {
        $expiredConsents = BiometricConsent::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('is_active', false)
            ->where(function ($q) {
                $q->whereNotNull('revoked_at')
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('expires_at')
                            ->whereDate('expires_at', '<', now());
                    });
            })
            ->whereRaw("julianday('now') - julianday(consented_at) > retention_days")
            ->get();

        $purged = 0;
        foreach ($expiredConsents as $consent) {
            $consent->delete();
            $purged++;
        }

        return $purged;
    }

    private function tenantIdFor(User $user, ?int $tenantId): int
    {
        return (int) ($tenantId ?? $user->current_tenant_id ?? $user->tenant_id);
    }
}
