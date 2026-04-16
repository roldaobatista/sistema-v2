<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property array<int|string, mixed>|null $payload
 * @property Carbon|null $processed_at
 * @property int|null $priority
 * @property int|null $attempts
 */
class SyncQueueItem extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'sync_queue_items';

    public const STATUSES = ['pending', 'processing', 'completed', 'failed'];

    public const ACTIONS = ['create', 'update', 'delete'];

    protected $fillable = [
        'tenant_id',
        'user_id',
        'entity_type',
        'entity_id',
        'action',
        'payload',
        'status',
        'priority',
        'attempts',
        'error_message',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
            'priority' => 'integer',
            'attempts' => 'integer',
        ];

    }

    // ── Relationships ──

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // ── Scopes ──

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ── Helpers ──

    public function markProcessing(): self
    {
        $this->update([
            'status' => 'processing',
            'attempts' => $this->attempts + 1,
        ]);

        return $this;
    }

    public function markCompleted(): self
    {
        $this->update([
            'status' => 'completed',
            'processed_at' => now(),
        ]);

        return $this;
    }

    public function markFailed(string $error): self
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
        ]);

        return $this;
    }
}
