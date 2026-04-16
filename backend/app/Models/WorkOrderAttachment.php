<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderAttachment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'work_order_id', 'uploaded_by', 'file_name', 'file_path',
        'file_type', 'file_size', 'description', 'category',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
