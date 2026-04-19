<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indices compostos para polimorficos sem morphs() helper + indices hot em
 * webhook_logs e notifications. Fecha data-idx-01..06 e data-idx-08 da
 * re-auditoria Camada 1 2026-04-18.
 *
 * Idempotente (H3): checa hasIndex antes de criar; tolera ausencia de tabela.
 */
return new class extends Migration
{
    /**
     * @var array<int, array{table: string, columns: array<int, string>, index: string}>
     */
    private array $indexes = [
        // Polimorficos compostos — padrao Laravel morphs(): (type, id)
        ['table' => 'bank_statement_entries', 'columns' => ['matched_type', 'matched_id'],  'index' => 'bank_stmt_entries_matched_morph_idx'],
        ['table' => 'email_logs',             'columns' => ['related_type', 'related_id'],  'index' => 'email_logs_related_morph_idx'],
        ['table' => 'whatsapp_messages',      'columns' => ['related_type', 'related_id'],  'index' => 'whatsapp_msgs_related_morph_idx'],
        ['table' => 'sync_queue_items',       'columns' => ['entity_type', 'entity_id'],    'index' => 'sync_queue_items_entity_morph_idx'],
        ['table' => 'chat_messages',          'columns' => ['sender_type', 'sender_id'],    'index' => 'chat_messages_sender_morph_idx'],
        ['table' => 'mobile_notifications',   'columns' => ['entity_type', 'entity_id'],    'index' => 'mobile_notifs_entity_morph_idx'],
        ['table' => 'print_jobs',             'columns' => ['document_type', 'document_id'], 'index' => 'print_jobs_document_morph_idx'],
        ['table' => 'reconciliation_rules',   'columns' => ['target_type', 'target_id'],    'index' => 'recon_rules_target_morph_idx'],
        ['table' => 'sync_queue',             'columns' => ['entity_type', 'entity_id'],    'index' => 'sync_queue_entity_morph_idx'],

        // webhook_logs hot — dashboard de falhas + retry
        ['table' => 'webhook_logs', 'columns' => ['status'],                  'index' => 'webhook_logs_status_idx'],
        ['table' => 'webhook_logs', 'columns' => ['event'],                   'index' => 'webhook_logs_event_idx'],
        ['table' => 'webhook_logs', 'columns' => ['webhook_id', 'created_at'], 'index' => 'webhook_logs_webhook_created_idx'],

        // notifications por tipo
        ['table' => 'notifications', 'columns' => ['tenant_id', 'type'], 'index' => 'notifications_tenant_type_idx'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $spec) {
            $this->createIndexIfMissing($spec['table'], $spec['columns'], $spec['index']);
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $spec) {
            $this->dropIndexIfExists($spec['table'], $spec['index']);
        }
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function createIndexIfMissing(string $table, array $columns, string $indexName): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        if ($this->hasIndex($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($columns, $indexName) {
            $t->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (! $this->hasIndex($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($indexName) {
            $t->dropIndex($indexName);
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $row = DB::selectOne(
                "SELECT name FROM sqlite_master WHERE type='index' AND tbl_name = ? AND name = ?",
                [$table, $indexName]
            );

            return $row !== null;
        }

        $schema = $connection->getDatabaseName();
        $row = DB::selectOne(
            'SELECT INDEX_NAME FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$schema, $table, $indexName]
        );

        return $row !== null;
    }
};
