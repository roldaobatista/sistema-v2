<?php

declare(strict_types=1);

namespace Tests\Feature\Data;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regressão data-04 (re-auditoria Camada 1 2026-04-19).
 *
 * Após migração 2026_04_19_500003 restaurar NOT NULL em tenant_id nos 10
 * pivots/line-items com FK segura, tentativas de insert direto via
 * DB::table() sem tenant_id devem falhar com NOT NULL constraint.
 */
class PivotTenantNotNullTest extends TestCase
{
    private const PIVOTS_NOT_NULL = [
        'work_order_technicians',
        'work_order_equipments',
        'equipment_model_product',
        'email_email_tag',
        'quote_quote_tag',
        'service_call_equipments',
        'service_skills',
        'calibration_standard_weight',
        'purchase_quotation_items',
        'inventory_items',
    ];

    public function test_all_pivots_have_tenant_id_column(): void
    {
        foreach (self::PIVOTS_NOT_NULL as $table) {
            $this->assertTrue(
                Schema::hasColumn($table, 'tenant_id'),
                "{$table}.tenant_id ausente"
            );
        }
    }

    public function test_all_pivots_reject_null_tenant_id_on_raw_insert(): void
    {
        // Tenta um insert direto em work_order_technicians SEM tenant_id.
        // Deve falhar com NOT NULL constraint (SQLite: "NOT NULL constraint failed").
        $tenant = Tenant::factory()->create();
        app()->instance('current_tenant_id', $tenant->id);

        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
        ]);

        $this->expectException(QueryException::class);

        // insert sem tenant_id — deve falhar porque coluna é NOT NULL após migration
        DB::table('work_order_technicians')->insert([
            'work_order_id' => $workOrder->id,
            'user_id' => $user->id,
            'role' => 'tecnico',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_pivot_accepts_insert_with_tenant_id(): void
    {
        $tenant = Tenant::factory()->create();
        app()->instance('current_tenant_id', $tenant->id);

        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
        ]);

        $id = DB::table('work_order_technicians')->insertGetId([
            'tenant_id' => $tenant->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $user->id,
            'role' => 'tecnico',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertGreaterThan(0, $id);

        $row = DB::table('work_order_technicians')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame($tenant->id, (int) $row->tenant_id);
    }
}
