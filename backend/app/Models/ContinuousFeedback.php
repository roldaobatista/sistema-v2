<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property bool|null $is_anonymous
 */
class ContinuousFeedback extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'continuous_feedback';

    protected $fillable = [
        'tenant_id',
        'from_user_id',
        'to_user_id',
        'type',
        'content',
        'attachment_path',
        'is_anonymous',
        'visibility',
    ];

    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean',
        ];

    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
