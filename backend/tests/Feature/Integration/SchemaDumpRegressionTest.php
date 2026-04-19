<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * qa-02: regressão schema dump vs migrations.
 *
 * Valida que o dump SQLite canônico (`database/schema/sqlite-schema.sql`,
 * regenerado via `php generate_sqlite_schema.php`) contém os objetos
 * essenciais que as migrations declaram. Se uma migration nova for
 * adicionada sem regenerar o dump, algum dos checks abaixo falha.
 */
uses()->group('integration', 'schema-regression');

it('tabelas centrais existem no schema dump', function () {
    $centrais = [
        'tenants', 'users', 'customers', 'suppliers',
        'work_orders', 'schedules', 'quotes', 'expenses',
        'accounts_payable', 'accounts_receivable',
        'audit_logs', 'equipment_calibrations', 'payment_gateway_configs',
    ];

    foreach ($centrais as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Tabela central `{$table}` ausente no schema dump — regenerar via `php generate_sqlite_schema.php`.");
    }
});

it('tenants tem UNIQUE em slug e document (data-03 nao pode voltar)', function () {
    $indexes = array_map(
        fn ($row) => $row->name,
        DB()->select("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='tenants'")
    );

    expect($indexes)->toContain('tenants_document_unique');
    expect($indexes)->toContain('tenants_slug_unique');
});

it('audit_logs.tenant_id e NOT NULL (data-05 nao pode voltar)', function () {
    $info = DB()->select('PRAGMA table_info(audit_logs)');
    $col = collect($info)->firstWhere('name', 'tenant_id');

    expect($col)->not->toBeNull()
        ->and($col->notnull)->toBe(1, 'FK data-05: audit_logs.tenant_id voltou a ser NULLABLE — bypass do global scope possivel.');
});

it('schema dump tem indices nao-uniques (data-01 nao pode voltar)', function () {
    $count = (int) DB()->selectOne(
        "SELECT COUNT(*) AS total FROM sqlite_master WHERE type='index' AND sql LIKE 'CREATE INDEX%'"
    )->total;

    expect($count)->toBeGreaterThan(1000, 'FK data-01: dump com menos de 1000 CREATE INDEX sugere que o gerador voltou a descartar KEY MySQL.');
});

it('polimórficos com índice composto (data-idx-02..06)', function () {
    // Nomes finais refletem o prefixo da tabela aplicado pelo gerador de dump
    // (`{table}_{originalName}` quando originalName nao comeca com o nome da tabela).
    $polimorficos = [
        ['bank_statement_entries',   'bank_statement_entries_bank_stmt_entries_matched_morph_idx'],
        ['email_logs',               'email_logs_related_morph_idx'],
        ['whatsapp_messages',        'whatsapp_messages_whatsapp_msgs_related_morph_idx'],
        ['sync_queue_items',         'sync_queue_items_entity_morph_idx'],
        ['chat_messages',            'chat_messages_sender_morph_idx'],
        ['mobile_notifications',     'mobile_notifications_mobile_notifs_entity_morph_idx'],
        ['print_jobs',               'print_jobs_document_morph_idx'],
        ['reconciliation_rules',     'reconciliation_rules_recon_rules_target_morph_idx'],
        ['sync_queue',               'sync_queue_entity_morph_idx'],
    ];

    foreach ($polimorficos as [$table, $indexName]) {
        $row = DB()->selectOne(
            "SELECT name FROM sqlite_master WHERE type='index' AND tbl_name = ? AND name = ?",
            [$table, $indexName]
        );
        expect($row)->not->toBeNull();
    }
});

/**
 * Helper curto para acessar facade DB em escopo de teste.
 */
function DB()
{
    return DB::connection();
}
