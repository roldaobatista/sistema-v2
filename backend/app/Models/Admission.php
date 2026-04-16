<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $start_date
 * @property Carbon|null $aso_date
 * @property numeric-string|null $salary
 * @property bool|null $salary_confirmed
 * @property bool|null $documents_completed
 * @property bool|null $email_provisioned
 * @property bool|null $role_assigned
 * @property bool|null $mandatory_trainings_completed
 */
class Admission extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'candidate_id',
        'user_id',
        'status',
        'start_date',
        'salary',
        'salary_confirmed',
        'documents_completed',
        'aso_result',
        'aso_date',
        'esocial_receipt',
        'email_provisioned',
        'role_assigned',
        'mandatory_trainings_completed',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'aso_date' => 'date',
            'salary' => 'decimal:2',
            'salary_confirmed' => 'boolean',
            'documents_completed' => 'boolean',
            'email_provisioned' => 'boolean',
            'role_assigned' => 'boolean',
            'mandatory_trainings_completed' => 'boolean',
        ];

    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
