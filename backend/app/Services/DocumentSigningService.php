<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Str;

/**
 * Document Signing Service for Portaria 671/2021 compliance.
 * Uses HMAC-SHA256 with tenant-specific signing key.
 */
class DocumentSigningService
{
    /**
     * Sign content with tenant's HMAC key.
     */
    public function sign(string $content, int $tenantId): string
    {
        $key = $this->getOrCreateKey($tenantId);

        return hash_hmac('sha256', $content, $key);
    }

    /**
     * Verify a signature against content.
     */
    public function verify(string $content, string $signature, int $tenantId): bool
    {
        $expected = $this->sign($content, $tenantId);

        return hash_equals($expected, $signature);
    }

    /**
     * Get signing key for tenant, auto-generating if needed.
     */
    private function getOrCreateKey(int $tenantId): string
    {
        $tenant = Tenant::findOrFail($tenantId);

        if (empty($tenant->signing_key)) {
            $key = hash('sha256', Str::random(64).$tenantId.config('app.key'));
            $tenant->update(['signing_key' => $key]);

            return $key;
        }

        return $tenant->signing_key;
    }
}
