<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 1B (Encryption Casts) — corrige busca em colunas encrypted.
 *
 * Cast `encrypted` (AES-256-CBC com IV randômico) impede busca por igualdade
 * direta porque cada gravação produz ciphertext diferente. Solução:
 *  - Adicionar coluna `*_hash` (HMAC-SHA256 com APP_KEY) determinística;
 *  - Trocar coluna original (varchar curto) para TEXT, evitando truncamento
 *    do payload encrypted (~200+ chars em base64 com IV+ciphertext).
 *
 * Tabelas afetadas: customers, suppliers, users, employee_dependents.
 *
 * Guards: H3 (Iron Protocol) — toda alteração checa `hasColumn` antes.
 */
return new class extends Migration
{
    /**
     * Mapa: tabela => [coluna_origem, coluna_hash, índice composto, índice nome]
     */
    private array $targets = [
        'customers' => [
            'source' => 'document',
            'hash' => 'document_hash',
            'index' => 'customers_tenant_document_hash_idx',
        ],
        'suppliers' => [
            'source' => 'document',
            'hash' => 'document_hash',
            'index' => 'suppliers_tenant_document_hash_idx',
        ],
        'users' => [
            'source' => 'cpf',
            'hash' => 'cpf_hash',
            'index' => 'users_tenant_cpf_hash_idx',
        ],
        'employee_dependents' => [
            'source' => 'cpf',
            'hash' => 'cpf_hash',
            'index' => 'employee_dependents_tenant_cpf_hash_idx',
        ],
    ];

    public function up(): void
    {
        $driver = DB::getDriverName();

        foreach ($this->targets as $table => $cfg) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            // 1) Adicionar coluna *_hash se ainda não existir.
            if (Schema::hasColumn($table, $cfg['source']) && ! Schema::hasColumn($table, $cfg['hash'])) {
                Schema::table($table, function (Blueprint $blueprint) use ($cfg): void {
                    $blueprint->string($cfg['hash'], 64)->nullable()->after($cfg['source']);
                });
            }

            // 2) Adicionar índice composto (tenant_id, *_hash) se possível.
            //    SQLite reflete via schema dump em testes; em prod é MySQL.
            if (
                Schema::hasColumn($table, 'tenant_id')
                && Schema::hasColumn($table, $cfg['hash'])
                && ! $this->indexExists($table, $cfg['index'])
            ) {
                Schema::table($table, function (Blueprint $blueprint) use ($cfg): void {
                    $blueprint->index(['tenant_id', $cfg['hash']], $cfg['index']);
                });
            }

            // 3) Em MySQL/MariaDB, ampliar a coluna original para TEXT — payload encrypted
            //    excede varchar(11)/varchar(20). Em SQLite, varchar não tem length real,
            //    então é seguro pular o ALTER (driver dinâmico).
            if (in_array($driver, ['mysql', 'mariadb'], true) && Schema::hasColumn($table, $cfg['source'])) {
                DB::statement(sprintf('ALTER TABLE `%s` MODIFY `%s` TEXT NULL', $table, $cfg['source']));
            }

            // 4) Backfill *_hash a partir do valor atual da coluna source.
            //    Pré-deploy do cast encrypted: dados ainda em plain text — hash do valor cru.
            //    Pós-deploy: caso a coluna já contenha payload encrypted, decrypt via app
            //    é responsabilidade de um job dedicado (fora desta migration).
            DB::table($table)
                ->whereNotNull($cfg['source'])
                ->whereNull($cfg['hash'])
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($table, $cfg): void {
                    foreach ($rows as $row) {
                        $raw = (string) $row->{$cfg['source']};
                        if ($raw === '') {
                            continue;
                        }

                        // Heurística simples: ignora payloads claramente encrypted
                        // (Crypt::encryptString gera base64 longo iniciando por "ey").
                        if (strlen($raw) > 100 && preg_match('/^[A-Za-z0-9+\/=]+$/', $raw)) {
                            continue;
                        }

                        DB::table($table)
                            ->where('id', $row->id)
                            ->update([
                                $cfg['hash'] => hash_hmac('sha256', $raw, (string) config('app.key')),
                            ]);
                    }
                });
        }
    }

    public function down(): void
    {
        foreach ($this->targets as $table => $cfg) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (Schema::hasColumn($table, $cfg['hash'])) {
                Schema::table($table, function (Blueprint $blueprint) use ($cfg): void {
                    if ($this->indexExists($blueprint->getTable(), $cfg['index'])) {
                        $blueprint->dropIndex($cfg['index']);
                    }
                    $blueprint->dropColumn($cfg['hash']);
                });
            }
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        try {
            $indexes = collect(Schema::getConnection()
                ->getSchemaBuilder()
                ->getIndexes($table));

            return $indexes->contains(fn ($idx) => ($idx['name'] ?? null) === $index);
        } catch (Throwable) {
            return false;
        }
    }
};
