<?php

namespace App\Events;

use App\Models\ServiceCall;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServiceCallCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ServiceCall $serviceCall,
        public readonly ?User $user = null,
    ) {}
}
