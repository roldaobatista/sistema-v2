<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $skill_id
 * @property int|null $required_level
 * @property-read Skill|null $skill
 */
class SkillRequirement extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'position_id',
        'skill_id',
        'required_level',
    ];

    protected function casts(): array
    {
        return [
            'required_level' => 'integer',
        ];

    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * @return BelongsTo<Skill, $this>
     */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
