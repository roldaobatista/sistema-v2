<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 2B-fix — Reverte tenant_id para NULLABLE em tabelas cujo caminho
 * de inserção bypassa o auto-fill do trait `BelongsToTenant`.
 *
 * CAUSA RAIZ:
 * Wave 2B (migration 2026_04_17_150000) definiu tenant_id como NOT NULL
 * em 66 tabelas. Porém, algumas tabelas têm caminhos de inserção que
 * NÃO passam pelo boot() do Eloquent Model:
 *
 *   1. Pivots M2M via belongsToMany::attach() / sync() / detach()
 *      Eloquent usa Query Builder direto (newPivotStatement()->insert()).
 *      O trait BelongsToTenant aplica-se ao creating event do Model,
 *      que NÃO dispara nessas operações.
 *
 *   2. Inserts DB::table()->insert([...]) em controllers/services
 *      (ex: StockAdvancedController faz DB::table('purchase_quotation_items')
 *      ->insert() sem tenant_id).
 *
 *   3. Seeders que criam linhas de catálogo/exemplo (ex: DatabaseSeeder
 *      populando cameras sem tenant).
 *
 *   4. Factories que passam tenant_id = null explicitamente
 *      (ex: InmetroInstrumentFactory em cenários de scraping).
 *
 * ISOLAMENTO DE TENANT PRESERVADO:
 * Em pivots M2M, o isolamento é garantido via global scope do parent
 * (ex: Quote::with('tags') aplica WHERE quotes.tenant_id = X antes do
 * JOIN com quote_quote_tag). A coluna tenant_id no pivot é redundante
 * para isolamento — serve apenas para queries analíticas diretas.
 *
 * Fix arquitetural (override de newPivot() / QueryBuilder listener) fica
 * documentado em TECHNICAL-DECISIONS.md §14.5 como dívida (SEC-015).
 */
return new class extends Migration
{
    /**
     * Tabelas cujas inserções atuais bypassam o auto-fill do trait.
     * Identificadas empiricamente via suite Pest (Wave 2B-fix, 2026-04-17).
     */
    private array $tables = [
        // Pivots M2M (attach/sync/detach)
        'work_order_technicians',
        'work_order_equipments',
        'equipment_model_product',
        'email_email_tag',
        'quote_quote_tag',
        'service_call_equipments',
        'service_skills',
        'calibration_standard_weight',
        // Tabelas com inserções bypass (DB::table()->insert / seeder / factory legada)
        'purchase_quotation_items',
        'cameras',
        'inmetro_instruments',
        'inventory_items',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t): void {
                $t->unsignedBigInteger('tenant_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Reversão: voltar a NOT NULL. Guardado com hasTable/hasColumn.
        // NÃO executar em ambientes com dados NULL — rodar backfill antes.
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t): void {
                $t->unsignedBigInteger('tenant_id')->nullable(false)->change();
            });
        }
    }
};
