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
 *   // CPF/CNPJ (digitos apenas — normaliza máscara antes do hash):
 *   Customer::where('document_hash', Customer::hashSearchable($cpf, digitsOnly: true))->first();
 *
 *   // Outros campos (sem normalização):
 *   Customer::where('email_hash', Customer::hashSearchable($email))->first();
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
     *
     * SEC-024 (Wave 1D): para sincronização interna usamos a lista
     * `$encryptedDigitsOnlyFields` da própria instância — nomes de campos que
     * representam CPF/CNPJ devem ser declarados ali (default: ['document', 'cpf']).
     */
    public function setAttribute($key, $value)
    {
        $map = $this->encryptedSearchableFields ?? [];

        if (isset($map[$key])) {
            $hashColumn = $map[$key];
            $digitsOnly = in_array($key, $this->encryptedDigitsOnlyFields ?? [], true);
            // Setamos o hash usando o valor normalizado (raw) direto no attributes
            // array, bypassando mutator/cast da coluna *_hash (coluna simples).
            $this->attributes[$hashColumn] = static::computeSearchableHash($value, $digitsOnly);
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Calcula o HMAC determinístico para busca por igualdade na coluna `*_hash`.
     *
     * SEC-024 (Wave 1D): assinatura parametrizada explicitamente por `$digitsOnly`
     * em vez de inferir via lista hardcoded `['document', 'cpf']` no nome do campo.
     * Isso elimina o acoplamento entre o trait genérico e nomes de campos
     * específicos de domínio (CPF/CNPJ) — qualquer model pode opt-in para
     * normalização passando `digitsOnly: true` no caller.
     *
     * Caller deve passar `digitsOnly: true` para CPF/CNPJ (valor pode vir com
     * máscara `.`, `-`, `/`) e `false` (default) para campos onde o valor é
     * armazenado/buscado exatamente como entra (ex: e-mail, código alfanumérico).
     */
    public static function hashSearchable(string $value, bool $digitsOnly = false): ?string
    {
        return static::computeSearchableHash($value, $digitsOnly);
    }

    /**
     * Lógica central: normaliza (se aplicável) e calcula HMAC-SHA256 com APP_KEY.
     */
    protected static function computeSearchableHash(mixed $value, bool $digitsOnly): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = (string) $value;
        if ($digitsOnly) {
            $value = preg_replace('/\D+/', '', $value) ?? '';
        }

        if ($value === '') {
            return null;
        }

        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
