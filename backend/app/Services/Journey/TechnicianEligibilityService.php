<?php

namespace App\Services\Journey;

use App\Models\TechnicianCertification;
use App\Models\User;
use Illuminate\Support\Collection;

class TechnicianEligibilityService
{
    public function isEligibleForServiceType(User $user, string $serviceType, ?int $tenantId = null): bool
    {
        $tenantId = $this->tenantIdFor($user, $tenantId);
        $required = $this->getRequiredCertifications($tenantId, $serviceType);

        if ($required->isEmpty()) {
            return true;
        }

        foreach ($required as $certType) {
            $cert = TechnicianCertification::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $user->id)
                ->where('type', $certType)
                ->latest('expires_at')
                ->first();

            if (! $cert || ! $cert->isValid()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return Collection<int, array{type: string, reason: string, message: string, certification_id?: int|null}>
     */
    public function getBlockingCertifications(User $user, string $serviceType, ?int $tenantId = null): Collection
    {
        $tenantId = $this->tenantIdFor($user, $tenantId);
        $required = $this->getRequiredCertifications($tenantId, $serviceType);
        $blocking = collect();

        foreach ($required as $certType) {
            $cert = TechnicianCertification::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $user->id)
                ->where('type', $certType)
                ->latest('expires_at')
                ->first();

            if (! $cert) {
                $blocking->push([
                    'type' => $certType,
                    'reason' => 'missing',
                    'message' => "Certificação '{$certType}' não encontrada.",
                ]);
            } elseif (! $cert->isValid()) {
                $blocking->push([
                    'type' => $certType,
                    'reason' => $cert->isExpired() ? 'expired' : 'revoked',
                    'message' => "Certificação '{$cert->name}' vencida em {$cert->expires_at->format('d/m/Y')}.",
                    'certification_id' => $cert->id,
                ]);
            }
        }

        return $blocking;
    }

    /**
     * @return Collection<int, TechnicianCertification>
     */
    public function getExpiringCertifications(int $tenantId, int $days = 30): Collection
    {
        return TechnicianCertification::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays($days))
            ->whereNotIn('status', ['expired', 'revoked'])
            ->with('user:id,name')
            ->orderBy('expires_at')
            ->get();
    }

    public function refreshAllStatuses(int $tenantId): int
    {
        $certs = TechnicianCertification::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['revoked'])
            ->get();

        $updated = 0;
        foreach ($certs as $cert) {
            $oldStatus = $cert->status;
            $cert->refreshStatus();
            if ($cert->status !== $oldStatus) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * @return Collection<int, string>
     */
    private function getRequiredCertifications(int $tenantId, string $serviceType): Collection
    {
        return TechnicianCertification::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('required_for_service_types')
            ->get()
            ->filter(function ($cert) use ($serviceType) {
                $types = $cert->required_for_service_types ?? [];

                return in_array($serviceType, $types);
            })
            ->pluck('type')
            ->unique()
            ->values();
    }

    private function tenantIdFor(User $user, ?int $tenantId): int
    {
        return (int) ($tenantId ?? $user->current_tenant_id ?? $user->tenant_id);
    }
}
