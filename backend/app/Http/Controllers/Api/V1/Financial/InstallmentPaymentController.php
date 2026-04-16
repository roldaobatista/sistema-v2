<?php

namespace App\Http\Controllers\Api\V1\Financial;

use App\Http\Controllers\Controller;
use App\Models\AccountReceivable;
use App\Models\AccountReceivableInstallment;
use App\Services\Payment\DTO\PaymentChargeDTO;
use App\Services\Payment\PaymentGatewayService;
use App\Support\ApiResponse;
use App\Support\Decimal;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InstallmentPaymentController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(
        private readonly PaymentGatewayService $paymentGateway,
    ) {}

    /**
     * POST /api/v1/financial/receivables/{installment}/generate-boleto
     */
    public function generateBoleto(Request $request, AccountReceivableInstallment $installment): JsonResponse
    {
        $this->authorize('create', AccountReceivable::class);

        if ($error = $this->ensureTenantOwnership($installment, 'Parcela')) {
            return $error;
        }

        $validation = $this->validateInstallmentForCharge($installment);
        if ($validation !== null) {
            return $validation;
        }

        $receivable = $installment->accountReceivable()->with('customer')->first();

        if (! $receivable || ! $receivable->customer) {
            return ApiResponse::message('Título ou cliente não encontrado para esta parcela.', 422);
        }

        $customer = $receivable->customer;

        $dto = new PaymentChargeDTO(
            amount: (float) $installment->amount,
            description: "Parcela {$installment->installment_number} — {$receivable->description}",
            customerName: $customer->name ?? 'Cliente',
            customerDocument: $customer->document ?? '',
            customerEmail: $customer->email ?? null,
            dueDate: $installment->due_date?->format('Y-m-d'),
            metadata: [
                'payable_id' => $receivable->id,
                'payable_type' => 'AccountReceivable',
                'installment_id' => $installment->id,
                'tenant_id' => $this->tenantId(),
            ],
        );

        try {
            $result = $this->paymentGateway->createBoletoCharge(
                $dto,
                $this->tenantId(),
                $request->user()->id,
            );

            if (! $result->success) {
                Log::warning('Boleto generation failed', [
                    'installment_id' => $installment->id,
                    'error' => $result->errorMessage,
                ]);

                return ApiResponse::message($result->errorMessage ?? 'Erro ao gerar boleto.', 422);
            }

            $installment->update([
                'psp_external_id' => $result->externalId,
                'psp_status' => $result->status,
                'psp_boleto_url' => $result->boletoUrl,
                'psp_boleto_barcode' => $result->boletoBarcode,
            ]);

            return response()->json([
                'data' => [
                    'external_id' => $result->externalId,
                    'status' => $result->status,
                    'boleto_url' => $result->boletoUrl,
                    'boleto_barcode' => $result->boletoBarcode,
                    'due_date' => $result->dueDate,
                    'installment_id' => $installment->id,
                ],
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Boleto generation exception', [
                'installment_id' => $installment->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro interno ao gerar boleto.', 500);
        }
    }

    /**
     * POST /api/v1/financial/receivables/{installment}/generate-pix
     */
    public function generatePix(Request $request, AccountReceivableInstallment $installment): JsonResponse
    {
        $this->authorize('create', AccountReceivable::class);

        if ($error = $this->ensureTenantOwnership($installment, 'Parcela')) {
            return $error;
        }

        $validation = $this->validateInstallmentForCharge($installment);
        if ($validation !== null) {
            return $validation;
        }

        $receivable = $installment->accountReceivable()->with('customer')->first();

        if (! $receivable || ! $receivable->customer) {
            return ApiResponse::message('Título ou cliente não encontrado para esta parcela.', 422);
        }

        $customer = $receivable->customer;

        $dto = new PaymentChargeDTO(
            amount: (float) $installment->amount,
            description: "Parcela {$installment->installment_number} — {$receivable->description}",
            customerName: $customer->name ?? 'Cliente',
            customerDocument: $customer->document ?? '',
            customerEmail: $customer->email ?? null,
            dueDate: $installment->due_date?->format('Y-m-d'),
            metadata: [
                'payable_id' => $receivable->id,
                'payable_type' => 'AccountReceivable',
                'installment_id' => $installment->id,
                'tenant_id' => $this->tenantId(),
            ],
        );

        try {
            $result = $this->paymentGateway->createPixCharge(
                $dto,
                $this->tenantId(),
                $request->user()->id,
            );

            if (! $result->success) {
                Log::warning('PIX generation failed', [
                    'installment_id' => $installment->id,
                    'error' => $result->errorMessage,
                ]);

                return ApiResponse::message($result->errorMessage ?? 'Erro ao gerar PIX.', 422);
            }

            $installment->update([
                'psp_external_id' => $result->externalId,
                'psp_status' => $result->status,
                'psp_pix_qr_code' => $result->qrCode,
                'psp_pix_copy_paste' => $result->pixCopyPaste,
            ]);

            return response()->json([
                'data' => [
                    'external_id' => $result->externalId,
                    'status' => $result->status,
                    'qr_code' => $result->qrCode,
                    'qr_code_base64' => $result->qrCodeBase64,
                    'pix_copy_paste' => $result->pixCopyPaste,
                    'due_date' => $result->dueDate,
                    'installment_id' => $installment->id,
                ],
            ], 201);
        } catch (\Throwable $e) {
            Log::error('PIX generation exception', [
                'installment_id' => $installment->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro interno ao gerar PIX.', 500);
        }
    }

    /**
     * GET /api/v1/financial/receivables/{installment}/payment-status
     */
    public function checkStatus(Request $request, AccountReceivableInstallment $installment): JsonResponse
    {
        $this->authorize('view', AccountReceivable::class);

        if ($error = $this->ensureTenantOwnership($installment, 'Parcela')) {
            return $error;
        }

        $externalId = $installment->psp_external_id;

        if (! $externalId) {
            return ApiResponse::message('Nenhuma cobrança PSP vinculada a esta parcela.', 404);
        }

        try {
            $result = $this->paymentGateway->checkStatus($externalId);

            if ($result->success) {
                $installment->update(['psp_status' => $result->status]);
            }

            return response()->json([
                'data' => [
                    'external_id' => $externalId,
                    'status' => $result->status,
                    'success' => $result->success,
                    'installment_id' => $installment->id,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Payment status check failed', [
                'installment_id' => $installment->id,
                'external_id' => $externalId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao consultar status do pagamento.', 500);
        }
    }

    /**
     * Validate that the installment can receive a new charge.
     */
    private function validateInstallmentForCharge(AccountReceivableInstallment $installment): ?JsonResponse
    {
        if ($installment->status === 'paid') {
            return ApiResponse::message('Esta parcela já está paga.', 422);
        }

        if ($installment->status === 'cancelled') {
            return ApiResponse::message('Esta parcela está cancelada.', 422);
        }

        if (bccomp(Decimal::string($installment->amount), '0', 2) <= 0) {
            return ApiResponse::message('Valor da parcela inválido.', 422);
        }

        return null;
    }
}
