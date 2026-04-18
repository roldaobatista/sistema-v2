<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * data-05 / sec-01 / data-idx-07 (S2+S2+S3): audit_logs.tenant_id NULLABLE
 * permitia bypass do global scope BelongsToTenant — logs orfaos sem
 * tenant violavam LGPD (rastreabilidade) e trilhavam dados sensiveis.
 *
 * Backfill defensivo: em producao, rebaixa cada registro NULL para o
 * primeiro tenant existente (operador de plataforma tipico). Se nao ha
 * tenant algum (dev/test), deleta os orfaos — sao incoerentes.
 * Model `AuditLog` ja aplica resolveTenantId em boot; este ALTER apenas
 * cristaliza o contrato no banco.
 *
 * Idempotente (H3): checa nullability atual antes de alterar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $this->backfillNullTenantIds();

        // Drop FK antes do ALTER — FK atual usa ON DELETE SET NULL e bloqueia NOT NULL.
        // Recriamos a FK com RESTRICT (trilha LGPD: nao apagar audit_logs ao deletar tenant).
        $this->dropForeignIfExists('audit_logs', 'audit_logs_tenant_id_foreign');

        if ($this->columnIsNullable('audit_logs', 'tenant_id')) {
            $this->alterToNotNull('audit_logs', 'tenant_id');
        }

        $this->addRestrictForeign('audit_logs', 'tenant_id', 'tenants', 'id', 'audit_logs_tenant_id_foreign');
    }

    public function down(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $this->dropForeignIfExists('audit_logs', 'audit_logs_tenant_id_foreign');

        if (! $this->columnIsNullable('audit_logs', 'tenant_id')) {
            $this->alterToNullable('audit_logs', 'tenant_id');
        }

        // Nao recria a FK antiga (SET NULL) no down — o estado seguro e manter sem FK
        // ate que uma migration explicita restaure o comportamento legado.
    }

    private function backfillNullTenantIds(): void
    {
        $nullCount = (int) DB::table('audit_logs')->whereNull('tenant_id')->count();

        if ($nullCount === 0) {
            return;
        }

        $firstTenantId = DB::table('tenants')->min('id');

        if ($firstTenantId === null) {
            DB::table('audit_logs')->whereNull('tenant_id')->delete();

            return;
        }

        DB::table('audit_logs')
            ->whereNull('tenant_id')
            ->update(['tenant_id' => $firstTenantId]);
    }

    private function columnIsNullable(string $table, string $column): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA table_info(`$table`)");
            foreach ($rows as $row) {
                if ($row->name === $column) {
                    return (int) $row->notnull === 0;
                }
            }

            return true;
        }

        $schema = $connection->getDatabaseName();
        $row = DB::selectOne(
            'SELECT IS_NULLABLE FROM information_schema.columns
             WHERE table_schema = ? AND table_name = ? AND column_name = ? LIMIT 1',
            [$schema, $table, $column]
        );

        return $row === null ? true : strtoupper($row->IS_NULLABLE) === 'YES';
    }

    private function alterToNotNull(string $table, string $column): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite: dump regenerado ja reflete o MySQL; runtime de testes
            // carrega direto do dump e nao precisa de ALTER.
            return;
        }

        DB::statement("ALTER TABLE `$table` MODIFY COLUMN `$column` BIGINT UNSIGNED NOT NULL");
    }

    private function alterToNullable(string $table, string $column): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE `$table` MODIFY COLUMN `$column` BIGINT UNSIGNED NULL");
    }

    private function dropForeignIfExists(string $table, string $fkName): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        $schema = Schema::getConnection()->getDatabaseName();
        $row = DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.table_constraints
             WHERE table_schema = ? AND table_name = ? AND constraint_name = ?
             AND constraint_type = ? LIMIT 1',
            [$schema, $table, $fkName, 'FOREIGN KEY']
        );

        if ($row !== null) {
            DB::statement("ALTER TABLE `$table` DROP FOREIGN KEY `$fkName`");
        }
    }

    private function addRestrictForeign(
        string $table,
        string $column,
        string $refTable,
        string $refColumn,
        string $fkName
    ): void {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        // Idempotencia: se ja existe FK com esse nome, nao recria.
        $schema = Schema::getConnection()->getDatabaseName();
        $row = DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.table_constraints
             WHERE table_schema = ? AND table_name = ? AND constraint_name = ?
             AND constraint_type = ? LIMIT 1',
            [$schema, $table, $fkName, 'FOREIGN KEY']
        );

        if ($row !== null) {
            return;
        }

        DB::statement(
            "ALTER TABLE `$table`
             ADD CONSTRAINT `$fkName` FOREIGN KEY (`$column`)
             REFERENCES `$refTable` (`$refColumn`)
             ON DELETE RESTRICT ON UPDATE CASCADE"
        );
    }
};
