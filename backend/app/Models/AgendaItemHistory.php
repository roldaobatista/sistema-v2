<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgendaItemHistory extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'central_item_history';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function item(): BelongsTo
    {
        return $this->belongsTo(AgendaItem::class, 'agenda_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
