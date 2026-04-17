<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'webhook_id', 'event', 'payload', 'response_status',
        'response_body', 'duration_ms', 'status',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response_status' => 'integer',
            'duration_ms' => 'integer',
        ];

    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}
