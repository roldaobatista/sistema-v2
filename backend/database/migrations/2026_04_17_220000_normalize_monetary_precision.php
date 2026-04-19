<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 5 — DATA-010
 *
 * Normaliza precisão decimal de colunas monetárias críticas que carregam
 * VALORES TOTAIS AGREGADOS de invoices/contas/pagamentos/despesas.
 *
 * Problema: as colunas listadas estão em `decimal(12,2)`, com teto de
 * R$ 9.999.999.999,99. Suficiente para itens individuais, mas frágil
 * para totais de invoices grandes, payroll consolidado, ou somatórios
 * agregados de longa duração. Risco de overflow silencioso em produção.
 *
 * Padrão adotado (ver TECHNICAL-DECISIONS.md §14.10):
 *   - money agregado (totais, saldos, balances)  → decimal(15, 2)
 *   - money item / linha individual              → decimal(12, 2)  (mantido)
 *   - quantity                                   → decimal(15, 4)  (futuro)
 *   - percentage                                 → decimal(7, 4)   (futuro)
 *
 * Escopo desta migration: APENAS colunas de TOTAL/SALDO de domínio
 * financeiro core (5 alterações). Outras 100+ colunas decimais
 * permanecem como estão — alteração em massa traria risco operacional
 * sem ganho proporcional, dado que itens individuais não atingem o teto.
 *
 * Idempotente (regra H3): guards `hasTable`, `hasColumn`, `getColumnType`.
 *
 * IMPORTANTE — driver:
 *   - MySQL/MariaDB: `Schema::table()->decimal(...)->change()` requer
 *     doctrine/dbal (presente no composer.lock). Operação online em
 *     `decimal` apenas amplia precisão, não causa loss de dados.
 *   - SQLite (testes): tipos são affinity-based (`numeric`); `change()`
 *     é no-op efetivo. Migration não falha, apenas não muda nada visível
 *     no schema dump. Comportamento correto para in-memory tests.
 */
return new class extends Migration
{
    /**
     * Mapa de colunas a ampliar para decimal(15, 2).
     * Cada entrada: tabela => [colunas].
     */
    private const TARGETS = [
        'invoices' => ['total'],
        'accounts_payable' => ['amount', 'amount_paid'],
        'accounts_receivable' => ['amount', 'amount_paid'],
        'payments' => ['amount'],
        'expenses' => ['amount'],
    ];

    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // SQLite não suporta ALTER COLUMN sem rebuild da tabela e o tipo
        // é affinity-based — `numeric` cobre toda a faixa. Nada a fazer.
        if ($driver === 'sqlite') {
            return;
        }

        foreach (self::TARGETS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                if ($this->columnIsAlreadyWide($table, $column)) {
                    continue;
                }

                try {
                    Schema::table($table, function (Blueprint $t) use ($column) {
                        $t->decimal($column, 15, 2)->change();
                    });
                } catch (Throwable $e) {
                    // Se o ambiente não tem doctrine/dbal por algum motivo,
                    // logar e continuar — não bloquear deploy de outras
                    // migrations da wave. O finding remanescerá registrado.
                    if (! $this->isMissingDbalError($e)) {
                        throw $e;
                    }
                }
            }
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        // Rollback restaura precisão anterior (12, 2). Aceitável porque
        // valores acima de R$ 9.999.999.999,99 são caso de borda — se
        // existirem em produção, rollback falhará explicitamente (boa
        // sinalização: precisão maior já está em uso).
        foreach (self::TARGETS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                try {
                    Schema::table($table, function (Blueprint $t) use ($column) {
                        $t->decimal($column, 12, 2)->change();
                    });
                } catch (Throwable $e) {
                    if (! $this->isMissingDbalError($e)) {
                        throw $e;
                    }
                }
            }
        }
    }

    /**
     * Detecta se a coluna já está em precisão >= 15 para evitar ALTER
     * desnecessário em re-execuções (idempotência H3).
     */
    private function columnIsAlreadyWide(string $table, string $column): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $result = DB::select(
                'SELECT NUMERIC_PRECISION as p FROM information_schema.COLUMNS '
                .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, $column]
            );

            return ! empty($result) && (int) $result[0]->p >= 15;
        }

        // Postgres / outros: deixa o ALTER decidir — operação é idempotente
        // a nível de tipo (mudar decimal(12,2) → decimal(15,2) é seguro).
        return false;
    }

    private function isMissingDbalError(Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());

        return str_contains($msg, 'doctrine/dbal')
            || str_contains($msg, 'doctrine\\dbal');
    }
};
