<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\SetsCreatedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LgpdDataTreatment extends Model
{
    use Auditable, BelongsToTenant, SetsCreatedBy;

    protected $fillable = [
        'tenant_id', 'data_category', 'purpose', 'legal_basis',
        'description', 'data_types', 'retention_period',
        'retention_legal_basis', 'created_by',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
