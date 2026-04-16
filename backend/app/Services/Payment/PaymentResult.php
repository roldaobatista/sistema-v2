<?php

namespace App\Services\Payment;

/**
 * Result object returned by all payment gateway operations.
 */
class PaymentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $externalId = null,
        public readonly ?string $status = null,
        public readonly ?string $qrCode = null,
        public readonly ?string $qrCodeBase64 = null,
        public readonly ?string $pixCopyPaste = null,
        public readonly ?string $boletoUrl = null,
        public readonly ?string $boletoBarcode = null,
        public readonly ?string $dueDate = null,
        public readonly ?string $errorMessage = null,
        public readonly ?array $rawResponse = null,
    ) {}

    public static function ok(array $data = []): self
    {
        return new self(
            success: true,
            externalId: $data['external_id'] ?? null,
            status: $data['status'] ?? 'pending',
            qrCode: $data['qr_code'] ?? null,
            qrCodeBase64: $data['qr_code_base64'] ?? null,
            pixCopyPaste: $data['pix_copy_paste'] ?? null,
            boletoUrl: $data['boleto_url'] ?? null,
            boletoBarcode: $data['boleto_barcode'] ?? null,
            dueDate: $data['due_date'] ?? null,
            rawResponse: $data['raw'] ?? null,
        );
    }

    public static function fail(string $message, ?array $raw = null): self
    {
        return new self(
            success: false,
            errorMessage: $message,
            rawResponse: $raw,
        );
    }
}
