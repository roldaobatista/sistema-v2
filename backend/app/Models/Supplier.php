<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasEncryptedSearchableField;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property bool|null $is_active
 */
class Supplier extends Model
{
    use Auditable, BelongsToTenant, HasEncryptedSearchableField, \Illuminate\Database\Eloquent\Factories\HasFactory, SoftDeletes;

    /**
     * Campos encrypted que precisam de coluna *_hash para busca determinística.
     *
     * @var array<string, string>
     */
    protected array $encryptedSearchableFields = [
        'document' => 'document_hash',
    ];

    protected $fillable = [
        'tenant_id', 'type', 'name', 'document', 'document_hash', 'trade_name',
        'email', 'phone', 'phone2',
        'address_zip', 'address_street', 'address_number',
        'address_complement', 'address_neighborhood',
        'address_city', 'address_state',
        'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'document' => 'encrypted',
        ];
    }

    public function accountsPayable(): HasMany
    {
        return $this->hasMany(AccountPayable::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(SupplierContract::class);
    }

    public function defaultProducts(): HasMany
    {
        return $this->hasMany(Product::class, 'default_supplier_id');
    }

    // ─── Import Support ─────────────────────────────────────

    public static function getImportFields(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nome / Razão Social', 'required' => true],
            ['key' => 'document', 'label' => 'CPF/CNPJ', 'required' => true],
            ['key' => 'type', 'label' => 'Tipo (PF/PJ)', 'required' => false],
            ['key' => 'trade_name', 'label' => 'Nome Fantasia', 'required' => false],
            ['key' => 'email', 'label' => 'E-mail', 'required' => false],
            ['key' => 'phone', 'label' => 'Telefone', 'required' => false],
            ['key' => 'phone2', 'label' => 'Telefone 2', 'required' => false],
            ['key' => 'address_zip', 'label' => 'CEP', 'required' => false],
            ['key' => 'address_street', 'label' => 'Rua', 'required' => false],
            ['key' => 'address_number', 'label' => 'Número', 'required' => false],
            ['key' => 'address_complement', 'label' => 'Complemento', 'required' => false],
            ['key' => 'address_neighborhood', 'label' => 'Bairro', 'required' => false],
            ['key' => 'address_city', 'label' => 'Cidade', 'required' => false],
            ['key' => 'address_state', 'label' => 'UF', 'required' => false],
            ['key' => 'notes', 'label' => 'Observações', 'required' => false],
        ];
    }
}
