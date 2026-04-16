<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property bool|null $is_shared
 */
class EmailTemplate extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'subject',
        'body',
        'is_shared',
    ];

    protected function casts(): array
    {
        return [
            'is_shared' => 'boolean',
        ];

    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
