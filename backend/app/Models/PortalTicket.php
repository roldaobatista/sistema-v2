<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $resolved_at
 */
class PortalTicket extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'customer_id', 'created_by', 'equipment_id',
        'ticket_number', 'subject', 'description', 'priority', 'status',
        'category', 'source', 'assigned_to', 'resolved_at', 'qr_code',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];

    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function comments()
    {
        return $this->hasMany(PortalTicketComment::class);
    }
}
