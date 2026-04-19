<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property bool|null $is_active
 */
class SsoConfig extends Model
{
    use BelongsToTenant;

    protected $table = 'sso_configurations';

    protected $fillable = [
        'provider', 'client_id', 'client_secret',
        'tenant_domain', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'client_id' => 'encrypted',
            'client_secret' => 'encrypted',
        ];

    }

    protected $hidden = ['client_id', 'client_secret'];
}
