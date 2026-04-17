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

    protected $fillable = [
        'tenant_id',
        'gateway',
        'api_key',
        'api_secret',
        'methods',
        'is_active',
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
