<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $job_posting_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $resume_path
 * @property string|null $stage
 * @property string|null $notes
 * @property int|null $rating
 * @property string|null $rejected_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read JobPosting|null $jobPosting
 */
class Candidate extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'job_posting_id',
        'name',
        'email',
        'phone',
        'resume_path',
        'stage',
        'notes',
        'rating',
        'rejected_reason',
    ];

    public function jobPosting()
    {
        return $this->belongsTo(JobPosting::class);
    }
}
