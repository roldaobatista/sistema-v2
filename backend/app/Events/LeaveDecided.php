<?php

namespace App\Events;

use App\Models\LeaveRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeaveDecided
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public LeaveRequest $leave,
        public string $decision,
    ) {}
}
