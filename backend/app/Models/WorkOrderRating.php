<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Avaliação INTERNA da OS (preenchida pelo técnico/admin após conclusão).
 * Mede qualidade, pontualidade e satisfação geral do serviço prestado.
 *
 * Diferente de SatisfactionSurvey, que é a avaliação EXTERNA feita pelo cliente
 * via portal/email (NPS + ratings de serviço/técnico/pontualidade).
 *
 * @see SatisfactionSurvey Avaliação externa do cliente (NPS)
 *
 * @property int|null $overall_rating
 * @property int|null $quality_rating
 * @property int|null $punctuality_rating
 */
class WorkOrderRating extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'work_order_id', 'customer_id', 'overall_rating', 'quality_rating',
        'punctuality_rating', 'comment', 'channel',
    ];

    protected function casts(): array
    {
        return [
            'overall_rating' => 'integer',
            'quality_rating' => 'integer',
            'punctuality_rating' => 'integer',
        ];

    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
