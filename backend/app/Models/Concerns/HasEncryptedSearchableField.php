<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Mantém colunas `*_hash` (HMAC-SHA256 determinístico) sincronizadas com
 * colunas encrypted (`*` com cast `encrypted`), permitindo busca por igualdade
 * sem precisar decryptar todas as linhas.
 *
 * Implementação:
 *
 *  - Sobrescreve `setAttribute()` do Model para interceptar a atribuição de
 *    qualquer campo declarado em `$encryptedSearchableFields` e popular
 *    `*_hash` ANTES do cast `encrypted` ser aplicado. Isso captura o valor
 *    raw, garantindo determinismo (HMAC-SHA256 com APP_KEY).
 *  - Não depende de eventos Eloquent (`saving`/`creating`), portanto continua
 *    funcionando mesmo quando o teste usa `Event::fake()`.
 *
 * Uso:
 *
 *   class Customer extends Model
 *   {
 *       use HasEncryptedSearchableField;
 *
 *       protected array $encryptedSearchableFields = [
 *           'document' => 'document_hash',
 *       ];
 *
 *       protected function casts(): array
 *       {
 *           return ['document' => 'encrypted'];
 *       }
 *   }
 *
 * Para buscar:
 *   Customer::where('document_hash', Customer::hashSearchable('document', $cpf))->first();
 */
trait HasEncryptedSearchableField
{
    /**
     * Campos que devem ter o valor normalizado (apenas dígitos) antes do hash —
     * relevante para CPF/CNPJ que podem chegar com máscara (pontos, traços).
     *
     * Pode ser sobrescrito no model.
     *
     * @var array<int, string>
     */
    protected array $encryptedDigitsOnlyFields = ['document', 'cpf'];

    /**
     * Sobrescreve setAttribute para sincronizar colunas *_hash automaticamente.
     */
    public function setAttribute($key, $value)
    {
        $map = $this->encryptedSearchableFields ?? [];

        if (isset($map[$key])) {
            $hashColumn = $map[$key];
            // Setamos o hash usando o valor normalizado (raw) direto no attributes
            // array, bypassando mutator/cast da coluna *_hash (coluna simples).
            $this->attributes[$hashColumn] = static::computeSearchableHash($key, $value, $this->encryptedDigitsOnlyFields ?? []);
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Calcula o HMAC determinístico para busca por igualdade na coluna `*_hash`.
     */
    public static function hashSearchable(string $field, ?string $value): ?string
    {
        // Usa as configurações default da trait — ok para casos comuns.
        return static::computeSearchableHash($field, $value, ['document', 'cpf']);
    }

    /**
     * Lógica central: normaliza (se aplicável) e calcula HMAC-SHA256 com APP_KEY.
     *
     * @param  array<int, string>  $digitsOnlyFields
     */
    protected static function computeSearchableHash(string $field, mixed $value, array $digitsOnlyFields): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = (string) $value;
        if (in_array($field, $digitsOnlyFields, true)) {
            $value = preg_replace('/\D+/', '', $value) ?? '';
        }

        if ($value === '') {
            return null;
        }

        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
