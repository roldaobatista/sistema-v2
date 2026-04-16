<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Testes profundos Supplier + User model real.
 */
class SupplierAndUserRealLogicTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole('admin');

        $this->actingAs($this->user);
    }

    // ═══ Supplier ═══

    public function test_supplier_create(): void
    {
        $s = Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fornecedor Teste',
        ]);
        $this->assertDatabaseHas('suppliers', ['id' => $s->id, 'name' => 'Fornecedor Teste']);
    }

    public function test_supplier_is_active_cast(): void
    {
        $s = Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $this->assertTrue($s->is_active);
    }

    public function test_supplier_soft_deletes(): void
    {
        $s = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);
        $s->delete();
        $this->assertSoftDeleted($s);
    }

    public function test_supplier_import_fields(): void
    {
        $fields = Supplier::getImportFields();
        $this->assertIsArray($fields);
        $this->assertGreaterThan(0, count($fields));

        $keys = array_column($fields, 'key');
        $this->assertContains('name', $keys);
        $this->assertContains('document', $keys);
    }

    public function test_supplier_import_fields_required(): void
    {
        $fields = Supplier::getImportFields();
        $required = array_filter($fields, fn ($f) => $f['required'] === true);
        $this->assertGreaterThanOrEqual(2, count($required));
    }

    public function test_supplier_has_accounts_payable(): void
    {
        $s = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertCount(0, $s->accountsPayable);
    }

    // ═══ User model tests ═══

    public function test_user_has_tenant_access(): void
    {
        $this->assertTrue($this->user->hasTenantAccess($this->tenant->id));
    }

    public function test_user_has_no_access_to_other_tenant(): void
    {
        $other = Tenant::factory()->create();
        $this->assertFalse($this->user->hasTenantAccess($other->id));
    }

    public function test_user_has_role(): void
    {
        $this->assertTrue($this->user->hasRole('admin'));
    }

    public function test_user_tenants_relationship(): void
    {
        $this->assertGreaterThanOrEqual(1, $this->user->tenants()->count());
    }

    public function test_user_can_have_multiple_tenants(): void
    {
        $t2 = Tenant::factory()->create();
        $this->user->tenants()->attach($t2->id, ['is_default' => false]);
        $this->assertEquals(2, $this->user->tenants()->count());
    }

    public function test_user_default_tenant(): void
    {
        $defaultTenant = $this->user->tenants()
            ->wherePivot('is_default', true)
            ->first();
        $this->assertNotNull($defaultTenant);
        $this->assertEquals($this->tenant->id, $defaultTenant->id);
    }

    // ═══ Customer import fields ═══

    public function test_customer_import_fields(): void
    {
        $fields = Customer::getImportFields();
        $this->assertIsArray($fields);
        $keys = array_column($fields, 'key');
        $this->assertContains('name', $keys);
    }
}
