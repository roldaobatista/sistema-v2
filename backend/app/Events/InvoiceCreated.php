<?php

namespace App\Events;

use App\Models\Invoice;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Invoice $invoice;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }
}
