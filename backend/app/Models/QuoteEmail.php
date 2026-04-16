<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property bool|null $pdf_attached
 * @property Carbon|null $queued_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $failed_at
 */
class QuoteEmail extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'quote_id', 'sent_by', 'recipient_email',
        'recipient_name', 'subject', 'status', 'message_body', 'pdf_attached',
        'queued_at', 'sent_at', 'failed_at', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'pdf_attached' => 'boolean',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
