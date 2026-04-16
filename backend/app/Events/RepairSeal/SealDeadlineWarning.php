<?php

namespace App\Events\RepairSeal;

use App\Models\InmetroSeal;
use App\Models\RepairSealAlert;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SealDeadlineWarning
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly InmetroSeal $seal,
        public readonly RepairSealAlert $alert,
    ) {}
}
