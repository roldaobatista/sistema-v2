<?php

namespace App\Listeners;

use App\Events\VacationDeadlineApproaching;
use App\Models\Notification;
use App\Models\User;
use App\Models\VacationBalance;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendVacationDeadlineAlert implements ShouldQueue
{
    public function handle(VacationDeadlineApproaching $event): void
    {
        $balance = $event->balance;
        $days = $event->daysUntilDeadline;

        app()->instance('current_tenant_id', $balance->tenant_id);

        $title = "Prazo de Férias em {$days} dias";
        $message = "Você possui {$balance->remaining_days} dias de férias restantes. Prazo limite: {$balance->deadline->format('d/m/Y')}";

        try {
            // Notify the employee
            Notification::notify(
                $balance->tenant_id,
                $balance->user_id,
                'vacation_deadline',
                $title,
                [
                    'message' => $message,
                    'icon' => 'umbrella',
                    'color' => $days <= 30 ? 'red' : 'amber',
                    'link' => '/rh/ferias',
                    'notifiable_type' => VacationBalance::class,
                    'notifiable_id' => $balance->id,
                    'data' => [
                        'balance_id' => $balance->id,
                        'remaining_days' => $balance->remaining_days,
                        'days_until_deadline' => $days,
                        'deadline' => $balance->deadline->toDateString(),
                    ],
                ]
            );

            // Notify HR managers
            $hrManagers = User::where('tenant_id', $balance->tenant_id)
                ->where('is_active', true)
                ->permission('hr.document.manage')
                ->where('id', '!=', $balance->user_id)
                ->get();

            $employee = User::find($balance->user_id);
            $employeeName = $employee?->name ?? 'Colaborador';

            foreach ($hrManagers as $manager) {
                Notification::notify(
                    $balance->tenant_id,
                    $manager->id,
                    'vacation_deadline',
                    $title,
                    [
                        'message' => "{$employeeName} possui {$balance->remaining_days} dias de férias restantes. Prazo: {$balance->deadline->format('d/m/Y')}",
                        'icon' => 'umbrella',
                        'color' => $days <= 30 ? 'red' : 'amber',
                        'link' => '/rh/ferias',
                        'notifiable_type' => VacationBalance::class,
                        'notifiable_id' => $balance->id,
                        'data' => [
                            'balance_id' => $balance->id,
                            'employee_id' => $balance->user_id,
                            'employee_name' => $employeeName,
                            'remaining_days' => $balance->remaining_days,
                            'days_until_deadline' => $days,
                        ],
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::warning("SendVacationDeadlineAlert: falha para balance #{$balance->id}", ['error' => $e->getMessage()]);
        }
    }
}
