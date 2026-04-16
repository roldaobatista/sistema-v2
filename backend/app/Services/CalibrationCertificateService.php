<?php

namespace App\Services;

use App\Models\AccreditationScope;
use App\Models\EquipmentCalibration;
use App\Models\NumberingSequence;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;

class CalibrationCertificateService
{
    /**
     * Generate ISO 17025 calibration certificate PDF.
     *
     * The Blade template uses Eloquent models directly:
     * $calibration, $equipment, $standardWeights, $tenant, $workOrder
     */
    public function generate(EquipmentCalibration $calibration): \Barryvdh\DomPDF\PDF
    {
        $calibration->load([
            'equipment.customer',
            'performer',
            'approver',
            'workOrder.checklist.items',
            'workOrder.checklistResponses',
            'standardWeights',
            'readings',
            'excentricityTests',
            'repeatabilityTests',
            'linearityTests',
            'emissionChecklist',
            'accreditationScope',
        ]);

        // P1.3: Checklist da OS é PRÉ-REQUISITO para gerar certificado
        if ($calibration->workOrder && $calibration->workOrder->checklist_id) {
            $totalItems = $calibration->workOrder->checklist->items()->where('is_required', true)->count();
            $answeredItems = $calibration->workOrder->checklistResponses()->count();
            if ($totalItems > 0 && $answeredItems < $totalItems) {
                throw new \DomainException(
                    "Checklist incompleto ({$answeredItems}/{$totalItems} itens respondidos). Todos os itens obrigatórios devem ser preenchidos antes de gerar o certificado."
                );
            }
        }

        // ISO 17025 §7.8.6: regras não-simples DEVEM ter sido avaliadas (ConformityAssessmentService)
        $effectiveRule = $calibration->decision_rule
            ?? ($calibration->workOrder->decision_rule_agreed ?? null)
            ?? 'simple';
        if (in_array($effectiveRule, ['guard_band', 'shared_risk'], true)
            && $calibration->decision_result === null) {
            throw new \DomainException(
                "Regra de decisão '{$effectiveRule}' não foi avaliada. Execute 'Avaliar Conformidade' antes de emitir o certificado (ISO 17025 §7.8.6)."
            );
        }

        // Gate: CertificateEmissionChecklist deve estar aprovado (ISO 17025)
        $emissionChecklist = $calibration->emissionChecklist;
        if (! $emissionChecklist || ! $emissionChecklist->approved) {
            $pendingItems = [];
            if ($emissionChecklist) {
                $checkFields = [
                    'equipment_identified' => 'Equipamento identificado',
                    'scope_defined' => 'Escopo definido',
                    'critical_analysis_done' => 'Análise crítica realizada',
                    'procedure_defined' => 'Procedimento definido',
                    'standards_traceable' => 'Padrões rastreáveis',
                    'raw_data_recorded' => 'Dados brutos registrados',
                    'uncertainty_calculated' => 'Incerteza calculada',
                    'adjustment_documented' => 'Ajuste documentado',
                    'no_undue_interval' => 'Sem intervalo indevido',
                    'conformity_declaration_valid' => 'Declaração de conformidade válida',
                    'accreditation_mark_correct' => 'Marca de acreditação correta',
                ];
                foreach ($checkFields as $field => $label) {
                    if (! $emissionChecklist->{$field}) {
                        $pendingItems[] = $label;
                    }
                }
            }

            $message = 'O checklist de emissão do certificado deve ser preenchido e aprovado antes de gerar o PDF.';
            if (! empty($pendingItems)) {
                $message .= ' Itens pendentes: '.implode(', ', $pendingItems).'.';
            }

            throw new \DomainException($message);
        }

        // Gate: Bloquear emissão com padrão de referência vencido
        $expiredWeights = $calibration->standardWeights
            ->filter(fn ($w) => $w->certificate_expiry && $w->certificate_expiry->isPast());
        if ($expiredWeights->isNotEmpty()) {
            throw new \DomainException(
                'Padrões com certificado vencido: '.$expiredWeights->pluck('code')->join(', ').'. Atualize a validade antes de emitir.'
            );
        }

        // Determinar acreditação condicional (RBC/Cgcre)
        $category = $calibration->equipment?->equipmentModel?->category;
        $accreditationScope = $category
            ? AccreditationScope::where('tenant_id', $calibration->tenant_id)
                ->active()
                ->forCategory($category)
                ->first()
            : null;
        $isAccredited = $accreditationScope !== null && $accreditationScope->isValid();
        $calibration->update(['accreditation_scope_id' => $accreditationScope?->id]);

        $tenant = Tenant::find($calibration->tenant_id);

        if (empty($calibration->approved_by) && $calibration->performed_by) {
            $calibration->approved_by = $calibration->performed_by;
            $calibration->save();
            $calibration->load('approver');
        }
        if (empty(trim($calibration->laboratory ?? ''))) {
            $calibration->laboratory = $tenant?->name ?? 'Laboratório de Calibração';
            $calibration->save();
        }

        // Auto-generate certificate number if empty
        if (empty($calibration->certificate_number)) {
            $calibration->certificate_number = $this->generateCertificateNumber($calibration->tenant_id);
            $calibration->save();
        }

        // Auto-set issued_date to now if not set (ISO 17025 §7.8.2.1)
        if (empty($calibration->issued_date)) {
            $calibration->issued_date = now();
            $calibration->save();
        }

        $companySettings = app(PdfGeneratorService::class)
            ->getCompanySettings($tenant?->id ?? $calibration->tenant_id);

        $pdf = Pdf::loadView('pdf.calibration-certificate', [
            'calibration' => $calibration,
            'equipment' => $calibration->equipment,
            'standardWeights' => $calibration->standardWeights,
            'tenant' => $tenant,
            'workOrder' => $calibration->workOrder,
            'is_accredited' => $isAccredited,
            'accreditation' => $isAccredited ? [
                'number' => $accreditationScope->accreditation_number,
                'body' => $accreditationScope->accrediting_body,
                'scope' => $accreditationScope->scope_description,
            ] : null,
        ] + $companySettings);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('isRemoteEnabled', false);
        $pdf->setOption('defaultFont', 'DejaVu Sans');

        return $pdf;
    }

    /**
     * Generate and save PDF to storage, return relative path.
     */
    public function generateAndStore(EquipmentCalibration $calibration): string
    {
        // Idempotency guard: skip if certificate already generated and exists on disk
        if ($calibration->certificate_pdf_path) {
            $existingPath = storage_path('app/'.$calibration->certificate_pdf_path);
            if (File::exists($existingPath)) {
                // Return the relative path (strip "public/" prefix) for consistency
                return str_replace('public/', '', $calibration->certificate_pdf_path);
            }
        }

        $pdf = $this->generate($calibration);
        $fileName = "certificates/calibration_{$calibration->id}_{$calibration->certificate_number}.pdf";
        $path = storage_path("app/public/{$fileName}");

        if (! File::isDirectory(dirname($path))) {
            File::makeDirectory(dirname($path), 0755, true);
        }

        File::put($path, $pdf->output());

        $calibration->update(['certificate_pdf_path' => "public/{$fileName}"]);

        return $fileName;
    }

    /**
     * Send calibration certificate PDF by email.
     *
     * Uses a simple raw mail with attachment instead of CalibrationCertificateMail
     * (which requires WorkOrder + FiscalNote context from NF-e flow).
     */
    public function sendByEmail(
        EquipmentCalibration $calibration,
        string $email,
        ?string $subject = null,
        ?string $message = null,
    ): void {
        if (empty($calibration->certificate_pdf_path)) {
            throw new \DomainException('O certificado ainda não foi gerado. Gere o PDF antes de enviar por e-mail.');
        }

        $pdfPath = storage_path('app/'.$calibration->certificate_pdf_path);

        if (! File::exists($pdfPath)) {
            throw new \DomainException('Arquivo do certificado não encontrado no servidor. Gere o certificado novamente.');
        }

        $calibration->loadMissing(['equipment']);

        $equipmentName = $calibration->equipment?->name ?? 'Equipamento';
        $certNumber = $calibration->certificate_number ?? 'S/N';

        $resolvedSubject = $subject ?: "Certificado de Calibração {$certNumber} - {$equipmentName}";
        $resolvedMessage = $message ?: "Segue em anexo o certificado de calibração nº {$certNumber} referente ao equipamento {$equipmentName}.";

        Mail::raw($resolvedMessage, function (Message $mail) use ($email, $resolvedSubject, $pdfPath, $certNumber) {
            $mail->to($email)
                ->subject($resolvedSubject)
                ->attach($pdfPath, [
                    'as' => "Certificado_Calibracao_{$certNumber}.pdf",
                    'mime' => 'application/pdf',
                ]);
        });
    }

    private function generateCertificateNumber(int $tenantId): string
    {
        return DB::transaction(function () use ($tenantId) {
            $sequence = NumberingSequence::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('entity', 'calibration_certificate')
                ->lockForUpdate()
                ->first();

            if ($sequence) {
                // generateNext() uses its own transaction with lockForUpdate,
                // but we already hold the lock, so it will use a savepoint
                return $sequence->generateNext();
            }

            // Fallback: lock all calibrations for this tenant to prevent duplicate numbers
            $prefix = 'CERT-';

            $lastNumber = EquipmentCalibration::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->whereNotNull('certificate_number')
                ->where('certificate_number', 'like', "{$prefix}%")
                ->lockForUpdate()
                ->pluck('certificate_number')
                ->map(fn ($num) => (int) str_replace($prefix, '', $num))
                ->max() ?? 0;

            $next = $lastNumber + 1;

            return $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        });
    }
}
