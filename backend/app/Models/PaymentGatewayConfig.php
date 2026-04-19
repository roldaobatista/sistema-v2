<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Configuração de gateway de pagamento por tenant (Asaas, Pagar.me, Mercado Pago, etc.).
 *
 * SEC-001 (Audit Camada 1): `api_key` e `api_secret` são credenciais de gateway —
 * cast `encrypted` aplicado para encryption-at-rest (AES-256-CBC).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $gateway
 * @property string|null $api_key
 * @property string|null $api_secret
 * @property array<int, string>|string|null $methods
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PaymentGatewayConfig extends Model
{
    use BelongsToTenant, HasFactory;

    /**
     * PROD-015 (Wave 1D): `tenant_id` NÃO entra em `$fillable` — é atribuído
     * automaticamente pelo trait `BelongsToTenant` (override de `save()`).
     * Permitir mass-assignment de tenant_id permitiria forjar tenant via
     * payload — viola H1 do Iron Protocol.
     */
    protected $fillable = [
        'gateway',
        'api_key',
        'api_secret',
        'methods',
        'is_active',
    ];

    /**
     * qa-07 (Re-auditoria Camada 1): credenciais de gateway nao podem vazar
     * em serializacao JSON mesmo apos decriptagem.
     */
    protected $hidden = [
        'api_key',
        'api_secret',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'api_key' => 'encrypted',
            'api_secret' => 'encrypted',
        ];
    }
}
