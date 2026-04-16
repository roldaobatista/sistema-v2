<?php

namespace App\Events;

use App\Models\Expense;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExpenseApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Expense $expense,
    ) {}
}
