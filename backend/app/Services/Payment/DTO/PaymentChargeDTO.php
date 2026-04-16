<?php

namespace App\Services\Payment\DTO;

/**
 * DTO for creating payment charges (Pix or Boleto).
 */
class PaymentChargeDTO
{
    public function __construct(
        public readonly float $amount,
        public readonly string $description,
        public readonly string $customerName,
        public readonly string $customerDocument,
        public readonly ?string $customerEmail = null,
        public readonly ?string $dueDate = null,
        public readonly ?array $metadata = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            amount: (float) ($data['amount'] ?? 0),
            description: $data['description'] ?? '',
            customerName: $data['customer_name'] ?? '',
            customerDocument: $data['customer_document'] ?? '',
            customerEmail: $data['customer_email'] ?? null,
            dueDate: $data['due_date'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'amount' => $this->amount,
            'description' => $this->description,
            'customer_name' => $this->customerName,
            'customer_document' => $this->customerDocument,
            'customer_email' => $this->customerEmail,
            'due_date' => $this->dueDate,
            'metadata' => $this->metadata,
        ], fn ($v) => $v !== null);
    }
}
