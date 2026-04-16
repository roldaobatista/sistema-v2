<?php

namespace App\Modules\Metrologia\Listeners;

use App\Events\FiscalNoteAuthorized;
use App\Mail\CalibrationCertificateMail;
use App\Models\EquipmentCalibration;
use App\Models\WorkOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Envia por e-mail os certificados de calibração quando a NF-e da OS é autorizada.
 * Roda em background (ShouldQueue). Só envia se existirem PDFs já gerados no storage.
 */
class SendCalibrationCertificateNotification implements ShouldQueue
{
    public function handle(FiscalNoteAuthorized $event): void
    {
        $note = $event->fiscalNote;
        if (! $note->work_order_id) {
            return;
        }

        $workOrder = WorkOrder::with('customer')->find($note->work_order_id);
        if (! $workOrder) {
            return;
        }

        $calibrations = EquipmentCalibration::where('work_order_id', $workOrder->id)
            ->whereNotNull('certificate_pdf_path')
            ->get();

        $pdfPaths = [];
        foreach ($calibrations as $calibration) {
            $path = $calibration->certificate_pdf_path;
            if (! $path) {
                continue;
            }
            $relativePath = ltrim(str_replace('public/', '', $path), '/');
            if (Storage::disk('public')->exists($relativePath)) {
                $pdfPaths[] = [
                    'path' => Storage::disk('public')->path($relativePath),
                    'name' => $this->certificateFileName($calibration),
                ];
            }
        }

        if (empty($pdfPaths)) {
            Log::info('SendCalibrationCertificateNotification: nenhum PDF de certificado no storage', [
                'work_order_id' => $workOrder->id,
                'fiscal_note_id' => $note->id,
            ]);

            return;
        }

        $email = $workOrder->customer?->email ?? null;
        if (empty($email)) {
            Log::warning('SendCalibrationCertificateNotification: cliente sem e-mail', [
                'work_order_id' => $workOrder->id,
                'customer_id' => $workOrder->customer_id,
            ]);

            return;
        }

        try {
            Mail::to($email)->send(new CalibrationCertificateMail($workOrder, $note, $pdfPaths));
            $this->appendWorkOrderLog($workOrder, $note, count($pdfPaths));
        } catch (\Throwable $e) {
            Log::error('SendCalibrationCertificateNotification: falha ao enviar e-mail', [
                'work_order_id' => $workOrder->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function certificateFileName(EquipmentCalibration $calibration): string
    {
        $number = $calibration->certificate_number ?? "cal-{$calibration->id}";

        return "Certificado_Calibracao_{$number}.pdf";
    }

    private function appendWorkOrderLog(WorkOrder $workOrder, $note, int $count): void
    {
        $line = '['.now()->format('d/m/Y H:i').'] Certificados de calibração ('.$count.') enviados por e-mail automaticamente após autorização da NF-e (ref: '.($note->reference ?? $note->id).').';
        $separator = "\n";
        $workOrder->update([
            'internal_notes' => trim(($workOrder->internal_notes ?? '').$separator.$line),
        ]);
    }
}
