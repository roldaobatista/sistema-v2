<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property bool|null $is_completed
 * @property Carbon|null $completed_at
 * @property int|null $order
 */
class OnboardingChecklistItem extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'onboarding_checklist_id', 'title', 'description',
        'responsible_id', 'is_completed', 'completed_at',
        'completed_by', 'order',
    ];

    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
            'order' => 'integer',
        ];

    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(OnboardingChecklist::class, 'onboarding_checklist_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
