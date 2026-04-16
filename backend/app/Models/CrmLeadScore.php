<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property array<int|string, mixed>|null $score_breakdown
 * @property int|null $total_score
 * @property Carbon|null $calculated_at
 */
class CrmLeadScore extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'crm_lead_scores';

    protected $fillable = [
        'tenant_id', 'customer_id', 'total_score',
        'score_breakdown', 'grade', 'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'score_breakdown' => 'array',
            'total_score' => 'integer',
            'calculated_at' => 'datetime',
        ];
    }

    public const GRADES = [
        'A' => ['label' => 'Quente', 'min' => 80, 'color' => 'red'],
        'B' => ['label' => 'Morno', 'min' => 50, 'color' => 'orange'],
        'C' => ['label' => 'Frio', 'min' => 20, 'color' => 'blue'],
        'D' => ['label' => 'Gelado', 'min' => 0, 'color' => 'gray'],
    ];

    // ─── Methods ────────────────────────────────────────

    public static function calculateGrade(int $score): string
    {
        foreach (self::GRADES as $grade => $config) {
            if ($score >= $config['min']) {
                return $grade;
            }
        }

        return 'D';
    }

    // ─── Relationships ──────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
