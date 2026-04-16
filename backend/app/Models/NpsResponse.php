<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NpsResponse extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'work_order_id',
        'customer_id',
        'score',
        'comment',
    ];

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
