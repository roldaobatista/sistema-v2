<?php

namespace App\Notifications;

use App\Models\Payslip;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PayrollReadyNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Payslip $payslip
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $month = Carbon::parse($this->payslip->reference_month.'-01')->format('m/Y');

        return [
            'type' => 'payroll_ready',
            'payslip_id' => $this->payslip->id,
            'reference_month' => $this->payslip->reference_month,
            'message' => "Seu holerite de {$month} está disponível.",
        ];
    }
}
