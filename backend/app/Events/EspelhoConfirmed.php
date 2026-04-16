<?php

namespace App\Events;

use App\Models\EspelhoConfirmation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EspelhoConfirmed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public EspelhoConfirmation $confirmation
    ) {}
}
