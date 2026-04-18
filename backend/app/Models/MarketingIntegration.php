<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Integração de marketing por tenant (Mailchimp, RD Station, ActiveCampaign, etc.).
 *
 * SEC-003 (Audit Camada 1): `api_key` é credencial de provedor externo —
 * cast `encrypted` aplicado para encryption-at-rest.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $provider
 * @property string $api_key
 * @property bool $sync_contacts
 * @property bool $sync_events
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MarketingIntegration extends Model
{
    use BelongsToTenant, HasFactory;

    /**
     * PROD-015 (Wave 1D): `tenant_id` NÃO entra em `$fillable` — é atribuído
     * automaticamente pelo trait `BelongsToTenant` (override de `save()`).
     * Permitir mass-assignment de tenant_id permitiria forjar tenant via
     * payload — viola H1 do Iron Protocol.
     */
    protected $fillable = [
        'provider',
        'api_key',
        'sync_contacts',
        'sync_events',
    ];

    /**
     * qa-07 (Re-auditoria Camada 1): credencial de provedor externo nao pode
     * aparecer em responses JSON mesmo decriptada — forca via $hidden.
     */
    protected $hidden = [
        'api_key',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'sync_contacts' => 'boolean',
            'sync_events' => 'boolean',
        ];
    }
}
