<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'to', 'subject', 'body', 'status', 'sent_at', 'error', 'related_type', 'related_id',
    ];
}
