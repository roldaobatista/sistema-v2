<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $last_login_at
 * @property bool|null $is_active
 */
class ClientPortalUser extends Authenticatable
{
    use BelongsToTenant, HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'name',
        'email',
        'password',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
        ];

    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function getCurrentTenantIdAttribute(): ?int
    {
        return (int) $this->tenant_id;
    }
}
