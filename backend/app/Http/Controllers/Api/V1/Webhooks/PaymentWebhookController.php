<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Events\PaymentReceived;
use App\Events\PaymentWebhookProcessed;
use App\Http\Controllers\Controller;
use App\Models\AccountReceivable;
use App\Models\AccountReceivableInstallment;
use App\Models\Payment;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives payment webhook callbacks from external gateways.
 *
 * Validates HMAC signature, updates payment status,
 * and dispatches PaymentReceived event.
 */
class PaymentWebhookController extends Controller
{
    /**
     * @var array<string, class-string<Model>>
     */
    private const ALLOWED_PAYABLE_TYPES = [
        'AccountReceivable' => AccountReceivable::class,
    ];

    /**
     * Handle incoming webhook from payment gateway.
     */
    public function handle(Request $request): JsonResponse
    {
        // 1. Validate webhook signature
        if (! $this->validateSignature($request)) {
            Log::warning('PaymentWebhook: invalid signature', [
                'ip' => $request->ip(),
            ]);

            return ApiResponse::message('Assinatura inválida.', 401);
        }

        // 2. Parse payload
        $payload = $request->all();
        $event = $payload['event'] ?? '';
        $paymentData = $payload['payment'] ?? $payload;
        $externalId = $paymentData['id'] ?? null;
        $externalReference = $paymentData['externalReference'] ?? null;

        if (! $externalId) {
            return ApiResponse::message('Payload inválido: ID do pagamento não encontrado.', 422);
        }

        Log::info('PaymentWebhook: received', [
            'external_id' => $externalId,
            'event' => $event,
            'ref' => $externalReference,
        ]);

        // 3. Find the payment
        $expectedTenantId = $this->resolveExpectedTenantId($paymentData);
        $referenceContext = $this->resolveExternalReferenceContext($externalReference);
        $referenceTenantId = $referenceContext['tenant_id'];
        $trustedTenantId = $expectedTenantId ?? $referenceTenantId;
        $payment = Payment::withoutGlobalScopes()->where('external_id', $externalId)->first();
        $paymentExistedBeforeWebhook = $payment !== null;

        // 4. Map status and handle confirmation
        $newStatus = $this->mapEventToStatus($event);
        $isConfirmed = in_array($newStatus, ['confirmed', 'received']);

        // Webhooks confirmados podem ser reenviados pelo gateway sem metadata.
        // Se o pagamento ja foi processado, responder de forma idempotente sem tocar dados.
        if ($paymentExistedBeforeWebhook && $payment->status === 'confirmed' && $isConfirmed) {
            Log::info('PaymentWebhook: already processed (idempotent)', ['external_id' => $externalId]);

            return ApiResponse::data(['status' => 'already_processed']);
        }

        if ($expectedTenantId !== null && $referenceTenantId !== null && $expectedTenantId !== $referenceTenantId) {
            Log::warning('PaymentWebhook: payload tenant does not match external reference tenant', [
                'external_id' => $externalId,
                'payload_tenant_id' => $expectedTenantId,
                'reference_tenant_id' => $referenceTenantId,
            ]);

            return ApiResponse::message('Payload inválido: tenant da referência não confere.', 422);
        }

        if ($payment && $trustedTenantId === null) {
            Log::warning('PaymentWebhook: missing trusted tenant for existing payment', [
                'external_id' => $externalId,
                'payment_id' => $payment->id,
                'payment_tenant_id' => $payment->tenant_id,
            ]);

            return ApiResponse::message('Payload inválido: tenant do pagamento não informado.', 422);
        }

        if ($payment && (int) $payment->tenant_id !== $trustedTenantId) {
            Log::warning('PaymentWebhook: tenant mismatch for existing payment', [
                'external_id' => $externalId,
                'payment_id' => $payment->id,
                'payment_tenant_id' => $payment->tenant_id,
                'trusted_tenant_id' => $trustedTenantId,
            ]);

            return ApiResponse::message('Payload inválido: tenant do pagamento não confere.', 422);
        }

        // 5. If confirmed and payment record doesn't exist, create it (triggers reconciliation hook)
        if ($isConfirmed && ! $payment && $referenceContext['payable']) {
            try {
                $payable = $referenceContext['payable'];
                $payableClass = $referenceContext['payable_type'];
                $payableId = $referenceContext['payable_id'];
                $receiverId = $this->resolveWebhookPaymentReceiver($payable);
                $tenantId = $referenceContext['tenant_id'];

                if ($receiverId !== null && $tenantId !== null) {
                    $payment = Payment::create([
                        'tenant_id' => $tenantId,
                        'payable_type' => $payableClass,
                        'payable_id' => $payableId,
                        'received_by' => $receiverId,
                        'amount' => $paymentData['value'] ?? 0,
                        'payment_method' => strtolower($paymentData['billingType'] ?? 'pix'),
                        'payment_date' => now(),
                        'external_id' => $externalId,
                        'status' => $newStatus,
                        'paid_at' => now(),
                        'gateway_provider' => 'asaas',
                        'gateway_response' => $payload,
                    ]);
                } else {
                    Log::warning('PaymentWebhook: payable missing payment creation attributes', [
                        'external_id' => $externalId,
                        'payable_type' => $payableClass,
                        'payable_id' => $payableId,
                        'has_receiver' => $receiverId !== null,
                        'tenant_id' => $tenantId,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('PaymentWebhook: failed to auto-create payment', ['error' => $e->getMessage()]);
            }
        }

        if (! $payment) {
            Log::warning('PaymentWebhook: payment record not found and could not be created', ['external_id' => $externalId]);

            return ApiResponse::message('Pagamento não processado (registro não encontrado).', 404);
        }

        // 7. Update existing payment status
        $payment->update([
            'status' => $newStatus,
            'paid_at' => $isConfirmed ? now() : $payment->paid_at,
            'gateway_response' => $payload,
        ]);

        // 8. Reconcile linked installment if confirmed
        if ($isConfirmed) {
            $this->reconcileInstallment($payment, $paymentData);
        }

        // 9. Dispatch standard domain event if confirmed
        if ($isConfirmed && $payment->payable instanceof AccountReceivable) {
            event(new PaymentReceived($payment->payable, $payment));
        }

        // 10. Dispatch generic webhook processed event (used by observers/listeners)
        event(new PaymentWebhookProcessed($payment, $event));

        Log::info('PaymentWebhook: processed', [
            'payment_id' => $payment->id,
            'new_status' => $newStatus,
        ]);

        return ApiResponse::data([
            'payment_id' => $payment->id,
            'status' => $newStatus,
        ]);
    }

    /**
     * Validate webhook signature.
     *
     * Supports two styles:
     *  - Asaas: `payment.asaas.webhook_secret` config + `asaas-access-token` header (simple match).
     *  - Generic: `services.payment.webhook_secret` config + `X-Webhook-Signature` header (simple match).
     *
     * The generic variant is used by non-Asaas gateways and by the controller tests.
     */
    private function validateSignature(Request $request): bool
    {
        $asaasSecret = config('payment.asaas.webhook_secret');
        $genericSecret = config('services.payment.webhook_secret');

        // Nenhum secret configurado: permitir em dev/test, bloquear em producao.
        if (empty($asaasSecret) && empty($genericSecret)) {
            if (app()->environment('production')) {
                Log::critical('PaymentWebhook: no webhook secret configured! All webhook requests are being blocked.');

                return false;
            }

            return true;
        }

        $asaasHeader = (string) $request->header('asaas-access-token', '');
        $genericHeader = (string) $request->header('X-Webhook-Signature', '');

        if (! empty($asaasSecret) && $asaasHeader !== '' && hash_equals($asaasSecret, $asaasHeader)) {
            return true;
        }

        $genericSecretHeader = (string) $request->header('X-Webhook-Secret', '');
        if (! empty($genericSecret) && $genericSecretHeader !== '' && hash_equals($genericSecret, $genericSecretHeader)) {
            return true;
        }

        if (! empty($genericSecret) && $genericHeader !== '') {
            $expectedSignature = hash_hmac('sha256', $request->getContent(), $genericSecret);

            if (hash_equals($expectedSignature, $genericHeader)) {
                return true;
            }
        }

        if (! empty($genericSecret) && $genericHeader !== '' && hash_equals($genericSecret, $genericHeader)) {
            return true;
        }

        return false;
    }

    /**
     * Map webhook event type to internal payment status.
     */
    private function mapEventToStatus(string $event): string
    {
        return match (strtoupper($event)) {
            'PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED' => 'confirmed',
            'PAYMENT_OVERDUE' => 'overdue',
            'PAYMENT_REFUNDED', 'PAYMENT_REFUND_IN_PROGRESS' => 'refunded',
            'PAYMENT_DELETED', 'PAYMENT_CANCELLED' => 'cancelled',
            'PAYMENT_CREATED' => 'pending',
            'PAYMENT_UPDATED' => 'pending',
            default => 'pending',
        };
    }

    private function resolveWebhookPaymentReceiver(Model $payable): ?int
    {
        $createdBy = $payable->getAttribute('created_by');

        if ($createdBy) {
            return (int) $createdBy;
        }

        $authenticatedUserId = auth()->id();

        return $authenticatedUserId ? (int) $authenticatedUserId : null;
    }

    private function resolveWebhookPaymentTenantId(Model $payable): ?int
    {
        $tenantId = $payable->getAttribute('tenant_id');

        if (! $tenantId) {
            return null;
        }

        return (int) $tenantId;
    }

    /**
     * Resolve tenant explicitly emitted when the payment reference was created.
     *
     * @param  array<int|string, mixed>  $paymentData
     */
    private function resolveExpectedTenantId(array $paymentData): ?int
    {
        $tenantId = $paymentData['tenant_id']
            ?? $paymentData['tenantId']
            ?? data_get($paymentData, 'metadata.tenant_id')
            ?? data_get($paymentData, 'metadata.tenantId');

        if (! is_numeric($tenantId)) {
            return null;
        }

        $tenantId = (int) $tenantId;

        return $tenantId > 0 ? $tenantId : null;
    }

    /**
     * Resolve the tenant-bound payable identified by the gateway reference.
     *
     * @return array{payable: Model|null, payable_type: class-string<Model>|null, payable_id: int|null, tenant_id: int|null}
     */
    private function resolveExternalReferenceContext(mixed $externalReference): array
    {
        if (! is_string($externalReference) || ! str_contains($externalReference, ':')) {
            return [
                'payable' => null,
                'payable_type' => null,
                'payable_id' => null,
                'tenant_id' => null,
            ];
        }

        [$type, $id] = explode(':', $externalReference, 2);
        $payableClass = self::ALLOWED_PAYABLE_TYPES[$type] ?? null;

        if (! $payableClass || ! is_numeric($id)) {
            return [
                'payable' => null,
                'payable_type' => null,
                'payable_id' => null,
                'tenant_id' => null,
            ];
        }

        $payableId = (int) $id;
        $payable = $payableClass::withoutGlobalScopes()->find($payableId);

        return [
            'payable' => $payable,
            'payable_type' => $payableClass,
            'payable_id' => $payableId,
            'tenant_id' => $payable ? $this->resolveWebhookPaymentTenantId($payable) : null,
        ];
    }

    /**
     * Reconcile the installment linked to a confirmed payment.
     *
     * Resolution order:
     * 1. metadata['installment_id'] (sent by InstallmentPaymentController)
     * 2. Fallback: next pending installment for the AccountReceivable payable
     *
     * @param  array<int|string, mixed>  $paymentData
     */
    private function reconcileInstallment(Payment $payment, array $paymentData): void
    {
        try {
            $metadata = $paymentData['metadata'] ?? [];
            $installmentId = $metadata['installment_id'] ?? null;

            // Fallback: find next pending installment for this receivable
            if (! $installmentId && $payment->payable_type === 'App\\Models\\AccountReceivable' && $payment->payable_id) {
                $installmentId = AccountReceivableInstallment::where('account_receivable_id', $payment->payable_id)
                    ->where('status', '!=', 'paid')
                    ->orderBy('due_date')
                    ->value('id');
            }

            if (! $installmentId) {
                Log::info('PaymentWebhook: no installment to reconcile', [
                    'payment_id' => $payment->id,
                ]);

                return;
            }

            /** @var AccountReceivableInstallment|null $installment */
            $installment = AccountReceivableInstallment::find($installmentId);

            if (! $installment) {
                Log::warning('PaymentWebhook: installment not found for reconciliation', [
                    'installment_id' => $installmentId,
                    'payment_id' => $payment->id,
                ]);

                return;
            }

            // Idempotency: skip if already paid
            if ($installment->status === 'paid') {
                Log::info('PaymentWebhook: installment already paid (idempotent)', [
                    'installment_id' => $installmentId,
                ]);

                return;
            }

            $installment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'paid_amount' => $payment->amount,
                'psp_status' => 'confirmed',
            ]);

            Log::info('PaymentWebhook: installment reconciled', [
                'installment_id' => $installmentId,
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
            ]);
        } catch (\Exception $e) {
            // Best-effort: payment is already recorded, don't block webhook response
            Log::error('PaymentWebhook: failed to reconcile installment', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
