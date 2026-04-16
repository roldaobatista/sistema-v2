<?php

namespace App\Events;

use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkOrderCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public WorkOrder $workOrder,
        public User $user,
        public string $fromStatus = '',
    ) {}
}
