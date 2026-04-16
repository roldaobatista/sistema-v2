<?php

namespace App\Services\Integration;

use App\Models\EmailAccount;
use Illuminate\Support\Facades\Cache;

/**
 * Monitors the health of all external integrations via their Circuit Breakers.
 */
class IntegrationHealthService
{
    /**
     * All known integration circuits with metadata.
     *
     * @var array<string, array{label: string, category: string, critical: bool}>
     */
    private const CIRCUITS = [
        'auvo_api' => [
            'label' => 'Auvo API',
            'category' => 'field_service',
            'critical' => false,
        ],
        'focusnfe' => [
            'label' => 'FocusNFe (Notas Fiscais)',
            'category' => 'fiscal',
            'critical' => true,
        ],
        'external_api:brasilapi.com.br' => [
            'label' => 'BrasilAPI (CNPJ)',
            'category' => 'data',
            'critical' => false,
        ],
        'external_api:publica.cnpj.ws' => [
            'label' => 'CNPJ.ws',
            'category' => 'data',
            'critical' => false,
        ],
        'external_api:open.cnpja.com' => [
            'label' => 'OpenCNPJ',
            'category' => 'data',
            'critical' => false,
        ],
    ];

    /**
     * Get health status of all known integrations.
     *
     * @return array{integrations: array, summary: array}
     */
    public function getHealthStatus(): array
    {
        $integrations = [];
        $summary = ['healthy' => 0, 'degraded' => 0, 'down' => 0];

        foreach (self::CIRCUITS as $circuitKey => $meta) {
            $cb = CircuitBreaker::for($circuitKey);
            $state = $cb->getState();
            $failures = $cb->getFailureCount();

            $status = match ($state) {
                'closed' => 'healthy',
                'half_open' => 'degraded',
                'open' => 'down',
                default => 'unknown',
            };

            $summary[$status] = ($summary[$status] ?? 0) + 1;

            $integrations[] = [
                'key' => $circuitKey,
                'label' => $meta['label'],
                'category' => $meta['category'],
                'critical' => $meta['critical'],
                'status' => $status,
                'state' => $state,
                'failures' => $failures,
            ];
        }

        // Also check dynamic IMAP circuits
        $imapCircuits = $this->getImapCircuits();
        foreach ($imapCircuits as $imap) {
            $integrations[] = $imap;
            $summary[$imap['status']] = ($summary[$imap['status']] ?? 0) + 1;
        }

        return [
            'integrations' => $integrations,
            'summary' => $summary,
            'overall' => $summary['down'] > 0 ? 'degraded' : ($summary['degraded'] > 0 ? 'warning' : 'healthy'),
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get circuits that are currently NOT healthy (open or half-open).
     *
     * @return array<int, array>
     */
    public function getDegradedIntegrations(): array
    {
        $health = $this->getHealthStatus();

        return array_values(array_filter(
            $health['integrations'],
            fn (array $i) => $i['status'] !== 'healthy'
        ));
    }

    /**
     * Check if any critical integration is down.
     */
    public function hasCriticalFailure(): bool
    {
        foreach (self::CIRCUITS as $circuitKey => $meta) {
            if ($meta['critical'] && CircuitBreaker::for($circuitKey)->isOpen()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Dynamically discover IMAP circuit breakers from cache keys.
     *
     * @return array<int, array>
     */
    private function getImapCircuits(): array
    {
        $results = [];

        // Check for IMAP circuits by scanning known email accounts
        $accountIds = EmailAccount::where('is_active', true)->pluck('id');

        foreach ($accountIds as $accountId) {
            $circuitKey = "imap_{$accountId}";
            $cb = CircuitBreaker::for($circuitKey);
            $state = $cb->getState();

            if ($state === 'closed' && $cb->getFailureCount() === 0) {
                continue; // Skip healthy IMAP accounts (too many to list)
            }

            $status = match ($state) {
                'closed' => 'healthy',
                'half_open' => 'degraded',
                'open' => 'down',
                default => 'unknown',
            };

            $results[] = [
                'key' => $circuitKey,
                'label' => "IMAP Account #{$accountId}",
                'category' => 'email',
                'critical' => false,
                'status' => $status,
                'state' => $state,
                'failures' => $cb->getFailureCount(),
            ];
        }

        return $results;
    }
}
