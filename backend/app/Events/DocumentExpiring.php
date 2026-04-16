<?php

namespace App\Events;

use App\Models\EmployeeDocument;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentExpiring
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public EmployeeDocument $document,
        public int $daysUntilExpiry,
    ) {}
}
