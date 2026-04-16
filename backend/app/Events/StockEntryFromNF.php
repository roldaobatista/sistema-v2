<?php

namespace App\Events;

use App\Models\StockMovement;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockEntryFromNF
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public StockMovement $movement,
        public ?string $nfNumber = null,
        public ?int $supplierId = null,
    ) {}
}
