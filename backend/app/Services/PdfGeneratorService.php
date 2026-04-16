<?php

namespace App\Services;

use App\Enums\QuoteStatus;
use App\Models\EquipmentCalibration;
use App\Models\PaymentReceipt;
use App\Models\Quote;
use App\Models\StandardWeight;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\WorkOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Log;

class PdfGeneratorService
{
    /**
     * Retorna o path absoluto do logo da empresa no disco, ou null se não existir.
     */
    public function getCompanyLogoPath(int $tenantId): ?string
    {
        return $this->getCompanySettings($tenantId)['company_logo_path'];
    }

    public function getCompanySettings(int $tenantId): array
    {
        $logoUrl = SystemSetting::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('key', 'company_logo_url')
            ->value('value');

        $tagline = SystemSetting::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('key', 'company_tagline')
            ->value('value');

        $logoPath = null;
        if ($logoUrl) {
            $relative = str_replace('/storage/', '', $logoUrl);
            $full = storage_path('app/public/'.$relative);
            $realFull = realpath($full);
            $allowedBase = realpath(storage_path('app/public'));
            if ($realFull && $allowedBase && str_starts_with($realFull, $allowedBase.DIRECTORY_SEPARATOR)) {
                $logoPath = $realFull;
            } else {
                Log::warning('PdfGeneratorService: company logo not found on disk', [
                    'tenant_id' => $tenantId,
                    'logo_url' => $logoUrl,
                    'expected_path' => $full,
                ]);
            }
        }

        return [
            'company_logo_path' => $logoPath,
            'company_tagline' => $tagline ?: '',
        ];
    }

    /**
     * Resolve watermark text based on quote status.
     * Returns null for statuses that should not have a watermark.
     */
    public function resolveWatermarkText(Quote $quote): ?string
    {
        $status = $quote->status instanceof QuoteStatus ? $quote->status : QuoteStatus::tryFrom($quote->status);

        return match ($status) {
            QuoteStatus::DRAFT => 'RASCUNHO',
            QuoteStatus::PENDING_INTERNAL_APPROVAL => 'PENDENTE',
            QuoteStatus::REJECTED => 'REJEITADO',
            QuoteStatus::EXPIRED => 'EXPIRADO',
            QuoteStatus::RENEGOTIATION => 'RENEGOCIAÇÃO',
            default => null,
        };
    }

    /**
     * Generate a QR code as base64 PNG for the quote's public approval URL.
     */
    public function generateQrCodeBase64(Quote $quote): ?string
    {
        if (! $quote->magic_token) {
            return null;
        }

        $approvalUrl = $this->resolveQuotePublicUrl($quote);
        if (! $approvalUrl) {
            return null;
        }

        try {
            $result = Builder::create()
                ->writer(new PngWriter)
                ->data($approvalUrl)
                ->encoding(new Encoding('UTF-8'))
                ->errorCorrectionLevel(ErrorCorrectionLevel::Medium)
                ->size(200)
                ->margin(5)
                ->build();

            return base64_encode($result->getString());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Resolve the public URL for a quote (used for QR code).
     */
    private function resolveQuotePublicUrl(Quote $quote): ?string
    {
        if (! $quote->magic_token) {
            return null;
        }

        $frontendUrl = rtrim(config('app.frontend_url', config('app.url', '')), '/');

        return $frontendUrl.'/quotes/proposal/'.$quote->magic_token;
    }

    public function generateWorkOrderPdf(WorkOrder $wo): string
    {
        $wo->load(['customer', 'items', 'assignee', 'branch', 'equipment', 'seller', 'creator']);

        $tenant = Tenant::find($wo->tenant_id);
        $companySettings = $this->getCompanySettings($wo->tenant_id);
        $pdf = Pdf::loadView('pdf.work-order', ['workOrder' => $wo, 'tenant' => $tenant, ...$companySettings]);
        $filename = "OS-{$wo->business_number}.pdf";
        $path = storage_path("app/temp/{$filename}");

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $pdf->save($path);

        return $path;
    }

    /**
     * Prepara, renderiza e retorna o objeto DOMPDF do orçamento.
     * Pode ser utilizado com ->stream(), ->download(), ou ->output() consoante o caso.
     *
     * @return \Barryvdh\DomPDF\PDF
     */
    public function renderQuotePdf(Quote $quote)
    {
        $quote->loadMissing([
            'customer',
            'seller',
            'equipments.equipment',
            'equipments.items.product',
            'equipments.items.service',
            'equipments.photos', // Load fotos de equipamento pro-ativamente se existirem
        ]);

        $tenant = Tenant::find($quote->tenant_id);
        $companySettings = $this->getCompanySettings($quote->tenant_id);

        $viewData = compact('quote', 'tenant') + $companySettings + [
            'watermark_text' => $this->resolveWatermarkText($quote),
            'qr_code_base64' => $this->generateQrCodeBase64($quote),
        ];

        /* @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView('pdf.quote', $viewData);
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('isRemoteEnabled', false); // Garantia de que configs de companySettings path funcionam local
        $pdf->setOption('isPhpEnabled', true);
        $pdf->setOption('defaultFont', 'DejaVu Sans');

        return $pdf;
    }

    public function generateReceiptPdf(PaymentReceipt $receipt): string
    {
        $receipt->load(['customer']);

        $customer = $receipt->customer;
        $tenant = Tenant::find($receipt->tenant_id);
        $items = $receipt->items ?? [];

        $companySettings = $this->getCompanySettings($receipt->tenant_id);
        $pdf = Pdf::loadView('pdf.payment-receipt', compact('receipt', 'customer', 'tenant', 'items') + $companySettings);
        $filename = "RECIBO-{$receipt->receipt_number}.pdf";
        $path = storage_path("app/temp/{$filename}");

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $pdf->save($path);

        return $path;
    }

    public function generateCalibrationPdf(EquipmentCalibration $calibration): string
    {
        $calibration->load(['equipment', 'equipment.customer', 'performer', 'approver', 'readings', 'excentricityTests']);

        $equipment = $calibration->equipment;
        $tenant = Tenant::find($equipment?->tenant_id ?? $calibration->tenant_id);

        $standardWeights = collect();
        if ($calibration->work_order_id) {
            $workOrder = WorkOrder::find($calibration->work_order_id);
        } else {
            $workOrder = null;
        }

        $weightIds = $calibration->standard_weight_ids ?? [];
        if (! empty($weightIds)) {
            $standardWeights = StandardWeight::whereIn('id', $weightIds)->get();
        }

        $companySettings = $this->getCompanySettings($equipment?->tenant_id ?? $calibration->tenant_id);
        $pdf = Pdf::loadView('pdf.calibration-certificate', compact(
            'calibration', 'equipment', 'tenant', 'standardWeights', 'workOrder'
        ) + $companySettings);
        $filename = "CERT-{$calibration->certificate_number}.pdf";
        $path = storage_path("app/temp/{$filename}");

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $pdf->save($path);

        return $path;
    }
}
