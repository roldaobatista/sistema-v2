<?php

namespace App\Events;

use App\Models\CommissionEvent as Commission;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommissionGenerated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Commission $commission,
    ) {}
}
