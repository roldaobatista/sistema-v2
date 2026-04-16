<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<int|string, mixed>|null $details
 */
class EmailActivity extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'email_id',
        'user_id',
        'type',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
        ];

    }

    public function email()
    {
        return $this->belongsTo(Email::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
