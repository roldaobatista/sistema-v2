<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HourBankExpiring
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;

    public $hours;

    public $expiryDate;

    public function __construct(User $user, float $hours, string $expiryDate)
    {
        $this->user = $user;
        $this->hours = $hours;
        $this->expiryDate = $expiryDate;
    }
}
