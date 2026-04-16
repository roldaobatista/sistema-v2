<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckDocumentVersionExpiry implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function handle(): void
    {
        $thresholds = [30, 15, 7];

        foreach ($thresholds as $days) {
            $documents = DB::table('document_versions')
                ->whereNotNull('review_date')
                ->whereDate('review_date', now()->addDays($days)->toDateString())
                ->where('status', '!=', 'obsolete')
                ->whereNull('deleted_at')
                ->get();

            foreach ($documents as $document) {
                try {
                    app()->instance('current_tenant_id', $document->tenant_id);

                    DB::table('notifications')->insert([
                        'tenant_id' => $document->tenant_id,
                        'user_id' => $document->created_by ?? $document->approved_by ?? 0,
                        'type' => 'document_expiring',
                        'title' => "Documento expirando em {$days} dias",
                        'message' => "O documento \"{$document->title}\" (código {$document->document_code}) tem revisão prevista para ".$document->review_date.'. Providencie a revisão antes do vencimento.',
                        'icon' => 'file-warning',
                        'color' => $days <= 7 ? 'red' : ($days <= 15 ? 'orange' : 'yellow'),
                        'link' => "/quality/documents/{$document->id}",
                        'notifiable_type' => 'App\\Models\\DocumentVersion',
                        'notifiable_id' => $document->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    Log::info("CheckDocumentVersionExpiry: alerta criado para documento #{$document->id} ({$days} dias)", [
                        'document_code' => $document->document_code,
                        'review_date' => $document->review_date,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning("CheckDocumentVersionExpiry: falha para document #{$document->id}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('CheckDocumentVersionExpiry job failed', ['error' => $e->getMessage()]);
    }
}
