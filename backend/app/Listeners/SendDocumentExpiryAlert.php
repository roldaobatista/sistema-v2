<?php

namespace App\Listeners;

use App\Events\DocumentExpiring;
use App\Models\EmployeeDocument;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;

class SendDocumentExpiryAlert implements ShouldQueue
{
    public function handle(DocumentExpiring $event): void
    {
        $document = $event->document;
        $days = $event->daysUntilExpiry;

        app()->instance('current_tenant_id', $document->tenant_id);

        $title = "Documento Expirando em {$days} dias";
        $message = "O documento \"{$document->name}\" ({$document->getCategoryLabel()}) vence em {$days} dias ({$document->expiry_date->format('d/m/Y')})";

        try {
            // Notify the employee
            Notification::notify(
                $document->tenant_id,
                $document->user_id,
                'document_expiring',
                $title,
                [
                    'message' => $message,
                    'icon' => 'file-warning',
                    'color' => $days <= 7 ? 'red' : 'amber',
                    'link' => '/rh/documentos',
                    'notifiable_type' => EmployeeDocument::class,
                    'notifiable_id' => $document->id,
                    'data' => [
                        'document_id' => $document->id,
                        'days_until_expiry' => $days,
                        'expiry_date' => $document->expiry_date->toDateString(),
                    ],
                ]
            );

            // Notify HR managers (users with hr.document.manage permission)
            $hrManagers = User::where('tenant_id', $document->tenant_id)
                ->where('is_active', true)
                ->permission('hr.document.manage')
                ->where('id', '!=', $document->user_id)
                ->get();

            $employee = User::find($document->user_id);
            $employeeName = $employee?->name ?? 'Colaborador';

            foreach ($hrManagers as $manager) {
                Notification::notify(
                    $document->tenant_id,
                    $manager->id,
                    'document_expiring',
                    $title,
                    [
                        'message' => "Documento de {$employeeName}: {$message}",
                        'icon' => 'file-warning',
                        'color' => $days <= 7 ? 'red' : 'amber',
                        'link' => '/rh/documentos',
                        'notifiable_type' => EmployeeDocument::class,
                        'notifiable_id' => $document->id,
                        'data' => [
                            'document_id' => $document->id,
                            'employee_id' => $document->user_id,
                            'employee_name' => $employeeName,
                            'days_until_expiry' => $days,
                        ],
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::warning("SendDocumentExpiryAlert: falha para document #{$document->id}", ['error' => $e->getMessage()]);
        }
    }
}
