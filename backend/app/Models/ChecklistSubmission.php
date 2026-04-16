<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property array<int|string, mixed>|null $responses
 * @property Carbon|null $completed_at
 */
class ChecklistSubmission extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'checklist_id',
        'work_order_id',
        'technician_id',
        'responses',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'responses' => 'array',
            'completed_at' => 'datetime',
        ];

    }

    public function checklist()
    {
        return $this->belongsTo(Checklist::class);
    }

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}
