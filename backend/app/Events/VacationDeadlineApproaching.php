<?php

namespace App\Events;

use App\Models\VacationBalance;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VacationDeadlineApproaching
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public VacationBalance $balance,
        public int $daysUntilDeadline,
    ) {}
}
