<?php

namespace App\Mail;

use App\Models\FiscalNote;
use App\Models\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CalibrationCertificateMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{path: string, name: string}>  $pdfAttachments  Caminho absoluto e nome do arquivo para cada PDF
     */
    public function __construct(
        public readonly WorkOrder $workOrder,
        public readonly FiscalNote $fiscalNote,
        public readonly array $pdfAttachments,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Certificados de Calibração - OS {$this->workOrder->business_number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.calibration-certificate',
            with: [
                'workOrder' => $this->workOrder,
                'customerName' => $this->workOrder->customer?->name ?? 'Cliente',
                'count' => count($this->pdfAttachments),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];
        foreach ($this->pdfAttachments as $item) {
            if (is_file($item['path'] ?? '')) {
                $attachments[] = Attachment::fromPath($item['path'])
                    ->as($item['name'] ?? 'certificado.pdf');
            }
        }

        return $attachments;
    }
}
