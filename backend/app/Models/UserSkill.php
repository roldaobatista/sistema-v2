<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int|null $current_level
 * @property Carbon|null $assessed_at
 */
class UserSkill extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'skill_id',
        'current_level',
        'assessed_at',
        'assessed_by',
    ];

    protected function casts(): array
    {
        return [
            'current_level' => 'integer',
            'assessed_at' => 'date',
        ];

    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function skill()
    {
        return $this->belongsTo(Skill::class);
    }

    public function assessor()
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }
}
