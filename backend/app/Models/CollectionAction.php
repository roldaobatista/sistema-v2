<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $sent_at
 * @property int|null $step_index
 */
class CollectionAction extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'account_receivable_id', 'collection_rule_id',
        'step_index', 'channel', 'status', 'scheduled_at', 'sent_at', 'response',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'step_index' => 'integer',
        ];

    }

    public function accountReceivable(): BelongsTo
    {
        return $this->belongsTo(AccountReceivable::class);
    }

    public function collectionRule(): BelongsTo
    {
        return $this->belongsTo(CollectionRule::class);
    }
}
