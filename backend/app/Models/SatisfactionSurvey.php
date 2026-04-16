<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\SatisfactionSurveyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pesquisa de satisfação EXTERNA (preenchida pelo CLIENTE via portal/email).
 * Inclui NPS (0-10) + ratings de serviço, técnico e pontualidade.
 *
 * Diferente de WorkOrderRating, que é avaliação INTERNA feita pelo técnico/admin.
 *
 * @see WorkOrderRating Avaliação interna (técnico/admin)
 *
 * @property int|null $nps_score
 * @property int|null $service_rating
 * @property int|null $technician_rating
 * @property int|null $timeliness_rating
 */
class SatisfactionSurvey extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<SatisfactionSurveyFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'customer_id', 'work_order_id', 'nps_score',
        'service_rating', 'technician_rating', 'timeliness_rating',
        'comment', 'channel',
    ];

    protected function casts(): array
    {
        return [
            'nps_score' => 'integer',
            'service_rating' => 'integer',
            'technician_rating' => 'integer',
            'timeliness_rating' => 'integer',
        ];

    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function getNpsCategoryAttribute(): string
    {
        if ($this->nps_score >= 9) {
            return 'promoter';
        }
        if ($this->nps_score >= 7) {
            return 'passive';
        }

        return 'detractor';
    }
}
