<?php

namespace App\Services;

use App\Events\RepairSeal\SealPseiSubmitted;
use App\Models\InmetroSeal;
use App\Models\PseiSubmission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PseiSealSubmissionService
{
    private const CACHE_SESSION_KEY = 'psei_session_cookies';

    private const SESSION_TTL_SECONDS = 1800; // 30 minutes

    /**
     * Submeter selo ao PSEI.
     */
    public function submitSeal(InmetroSeal $seal, string $submissionType = PseiSubmission::TYPE_AUTOMATIC, ?int $submittedBy = null): PseiSubmission
    {
        $submission = PseiSubmission::create([
            'tenant_id' => $seal->tenant_id,
            'seal_id' => $seal->id,
            'work_order_id' => $seal->work_order_id,
            'equipment_id' => $seal->equipment_id,
            'submission_type' => $submissionType,
            'status' => PseiSubmission::STATUS_QUEUED,
            'attempt_number' => 1,
            'max_attempts' => 3,
            'request_payload' => $this->buildSubmissionPayload($seal),
            'submitted_by' => $submittedBy,
        ]);

        return $this->processSubmission($submission);
    }

    /**
     * Processar uma submissão (novo ou retry).
     */
    public function processSubmission(PseiSubmission $submission): PseiSubmission
    {
        $submission->markAsSubmitting();

        try {
            if (! $this->authenticate($submission->tenant_id)) {
                $submission->markAsFailed('Falha na autenticação gov.br');

                return $submission;
            }

            $response = $this->sendToPortal($submission);

            if ($response['success']) {
                $protocolNumber = $response['protocol'] ?? 'PSEI-'.now()->format('YmdHis');

                $submission->markAsSuccess($protocolNumber);
                $submission->update(['response_payload' => $response]);

                $seal = $submission->seal;
                $seal->update([
                    'psei_status' => InmetroSeal::PSEI_CONFIRMED,
                    'psei_submitted_at' => now(),
                    'psei_protocol' => $protocolNumber,
                    'status' => InmetroSeal::STATUS_REGISTERED,
                    'deadline_status' => InmetroSeal::DEADLINE_RESOLVED,
                ]);

                event(new SealPseiSubmitted($seal, $submission));

                Log::info("PSEI submission successful for seal {$seal->number}", [
                    'protocol' => $protocolNumber,
                    'seal_id' => $seal->id,
                ]);
            } else {
                $errorStatus = $response['captcha'] ?? false
                    ? PseiSubmission::STATUS_CAPTCHA_BLOCKED
                    : PseiSubmission::STATUS_FAILED;

                $submission->markAsFailed($response['error'] ?? 'Unknown error', $errorStatus);
                $submission->update(['response_payload' => $response]);

                Log::warning("PSEI submission failed for seal {$submission->seal->number}", [
                    'error' => $response['error'] ?? 'Unknown',
                    'attempt' => $submission->attempt_number,
                    'seal_id' => $submission->seal_id,
                ]);
            }
        } catch (\Throwable $e) {
            $submission->markAsFailed($e->getMessage());

            Log::error("PSEI submission exception for seal_id {$submission->seal_id}", [
                'exception' => $e->getMessage(),
                'attempt' => $submission->attempt_number,
            ]);
        }

        return $submission->fresh();
    }

    /**
     * Retry de uma submissão existente.
     */
    public function retrySubmission(PseiSubmission $submission): PseiSubmission
    {
        if (! $submission->can_retry) {
            throw new \RuntimeException('Submissão não pode ser retentada: máximo de tentativas atingido.');
        }

        $submission->incrementAttempt();
        $submission->update([
            'submission_type' => PseiSubmission::TYPE_RETRY,
            'status' => PseiSubmission::STATUS_QUEUED,
            'error_message' => null,
        ]);

        return $this->processSubmission($submission);
    }

    /**
     * Verificar status de um protocolo.
     */
    public function checkSubmissionStatus(string $protocol): ?string
    {
        // Implementation depends on PSEI portal availability
        Log::info("Checking PSEI protocol status: {$protocol}");

        return null; // To be implemented when PSEI portal details are known
    }

    /**
     * Autenticar no portal PSEI via gov.br.
     */
    private function authenticate(int $tenantId): bool
    {
        $cacheKey = self::CACHE_SESSION_KEY.":{$tenantId}";

        if (Cache::has($cacheKey)) {
            return true;
        }

        try {
            // Gov.br OAuth2 authentication pending:
            // Requires client_id + client_secret from PSEI portal registration.
            // Real implementation will use HTTP client with cookie jar
            // similar to InmetroPsieScraperService pattern.
            // Until credentials are provided, session is simulated for
            // payload validation and submission flow testing.

            Log::info("PSEI authentication attempt for tenant {$tenantId}");

            // Simulated session — replace with actual HTTPs auth when
            // PSEI portal credentials are configured in .env:
            // PSEI_CLIENT_ID, PSEI_CLIENT_SECRET, PSEI_BASE_URL

            Cache::put($cacheKey, true, self::SESSION_TTL_SECONDS);

            return true;
        } catch (\Throwable $e) {
            Log::error("PSEI authentication failed for tenant {$tenantId}: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Enviar dados ao portal PSEI.
     */
    private function sendToPortal(PseiSubmission $submission): array
    {
        $payload = $submission->request_payload;

        try {
            // PSEI form submission pending:
            // Requires authenticated session and portal URL.
            // Implementation steps documented:
            // 1. Navigate to seal registration page
            // 2. Fill form with payload data
            // 3. Submit form
            // 4. Parse response for protocol number
            // 5. Handle CAPTCHA if detected
            // Blocked on: PSEI portal credentials (PSEI_BASE_URL env var)

            Log::info("PSEI portal submission for seal {$submission->seal_id}", [
                'payload_keys' => array_keys($payload ?? []),
            ]);

            // Returns explicit error until portal integration is configured
            return [
                'success' => false,
                'error' => 'PSEI portal integration pending: configure PSEI_BASE_URL, PSEI_CLIENT_ID, PSEI_CLIENT_SECRET in .env',
                'captcha' => false,
            ];
        } catch (\Throwable $e) {
            $isCaptcha = str_contains(strtolower($e->getMessage()), 'captcha');

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'captcha' => $isCaptcha,
            ];
        }
    }

    /**
     * Montar payload para envio ao PSEI.
     */
    private function buildSubmissionPayload(InmetroSeal $seal): array
    {
        $seal->load(['workOrder.customer', 'equipment', 'assignedTo']);

        $equipment = $seal->equipment;
        $workOrder = $seal->workOrder;
        $technician = $seal->assignedTo;

        return [
            'numero_selo' => $seal->number,
            'data_aplicacao' => $seal->used_at?->format('Y-m-d'),
            'numero_os' => $workOrder?->os_number ?? $workOrder?->number,
            'equipamento' => [
                'marca' => $equipment?->brand,
                'modelo' => $equipment?->model,
                'serial' => $equipment?->serial_number,
                'capacidade' => $equipment?->capacity ?? null,
            ],
            'tecnico' => [
                'nome' => $technician?->name,
                'id' => $technician?->id,
            ],
            'cliente' => [
                'nome' => $workOrder?->customer?->name,
                'documento' => $workOrder?->customer?->document,
            ],
        ];
    }
}
