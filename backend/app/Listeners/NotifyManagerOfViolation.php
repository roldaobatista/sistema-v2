<?php

namespace App\Listeners;

use App\Events\CltViolationDetected;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class NotifyManagerOfViolation implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(CltViolationDetected $event): void
    {
        $violation = $event->violation;

        // Log the violation or send email/push notification to HR Manager
        Log::channel('hr')->warning('Manager Notified: CLT Violation detected.', [
            'violation_id' => $violation->id,
            'user_id' => $violation->user_id,
            'type' => $violation->violation_type,
            'severity' => $violation->severity,
        ]);

        // Implement push notification or email sending here
    }
}
