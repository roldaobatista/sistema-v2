<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\DebtRenegotiation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RenegotiationApprovedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public DebtRenegotiation $renegotiation,
        public Customer $customer,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Renegociação Aprovada — Novas Parcelas Geradas',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.renegotiation-approved',
            with: [
                'customerName' => $this->customer->name,
                'originalDebt' => number_format((float) $this->renegotiation->original_total, 2, ',', '.'),
                'newAmount' => number_format((float) $this->renegotiation->negotiated_total, 2, ',', '.'),
                'installments' => $this->renegotiation->new_installments,
            ],
        );
    }
}
