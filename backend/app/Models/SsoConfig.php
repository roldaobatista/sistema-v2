<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property bool|null $is_enabled
 * @property bool|null $auto_create_users
 */
class SsoConfig extends Model
{
    use BelongsToTenant;

    protected $table = 'sso_configurations';

    protected $fillable = [
        'tenant_id', 'provider', 'client_id', 'client_secret',
        'redirect_url', 'is_enabled', 'auto_create_users',
        'default_role',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'auto_create_users' => 'boolean',
            'client_secret' => 'encrypted',
        ];

    }

    protected $hidden = ['client_secret'];
}
