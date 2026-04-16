<?php

namespace App\Console\Commands;

use App\Models\EmployeeDocument;
use App\Notifications\DocumentExpiryNotification;
use Illuminate\Console\Command;

class CheckExpiringDocuments extends Command
{
    protected $signature = 'hr:check-expiring-documents';

    protected $description = 'Notifica sobre documentos de funcionários prestes a vencer (30, 15 e 7 dias)';

    public function handle(): int
    {
        $thresholds = [30, 15, 7];
        $notified = 0;

        foreach ($thresholds as $days) {
            $targetDate = now()->addDays($days)->toDateString();

            $documents = EmployeeDocument::with('user')
                ->where('expiry_date', $targetDate)
                ->whereIn('status', ['valid', 'expiring'])
                ->get();

            foreach ($documents as $document) {
                if ($document->user) {
                    $document->user->notify(new DocumentExpiryNotification($document, $days));
                    $notified++;
                }

                if ($days <= 15) {
                    $document->update(['status' => 'expiring']);
                }
            }
        }

        // Marcar expirados
        EmployeeDocument::where('expiry_date', '<', now()->toDateString())
            ->where('status', '!=', 'expired')
            ->update(['status' => 'expired']);

        $this->info("Notificados: {$notified} documentos.");

        return self::SUCCESS;
    }
}
