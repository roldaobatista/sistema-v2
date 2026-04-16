<?php

namespace App\Events\RepairSeal;

use App\Models\InmetroSeal;
use App\Models\PseiSubmission;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SealPseiSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly InmetroSeal $seal,
        public readonly PseiSubmission $submission,
    ) {}
}
