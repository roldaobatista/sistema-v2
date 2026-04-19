<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $size
 */
class AgendaAttachment extends Model
{
    use BelongsToTenant;

    protected $table = 'central_attachments';

    protected $fillable = [
        'tenant_id',
        'agenda_item_id',
        'name',
        'path',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];

    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(AgendaItem::class, 'agenda_item_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
