<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 */
class OnboardingChecklist extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'user_id', 'onboarding_template_id',
        'started_at', 'completed_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];

    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(OnboardingTemplate::class, 'onboarding_template_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OnboardingChecklistItem::class)->orderBy('order');
    }

    public function getProgressAttribute(): int
    {
        $total = $this->items()->count();
        if ($total === 0) {
            return 0;
        }
        $done = $this->items()->where('is_completed', true)->count();

        return (int) round(($done / $total) * 100);
    }
}
