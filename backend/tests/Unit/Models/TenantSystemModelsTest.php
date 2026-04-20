<?php

namespace Tests\Unit\Models;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Import;
use App\Models\NumberingSequence;
use App\Models\PushSubscription;
use App\Models\RecurringContract;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TenantSystemModelsTest extends TestCase
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
        $this->actingAs($this->user);
    }

    // ── Tenant ──

    public function test_tenant_has_many_users(): void
    {
        $this->assertGreaterThanOrEqual(1, $this->tenant->users()->count());
    }

    public function test_tenant_has_many_settings(): void
    {
        TenantSetting::create(['tenant_id' => $this->tenant->id, 'key' => 'setting1', 'value_json' => ['v' => 1]]);
        TenantSetting::create(['tenant_id' => $this->tenant->id, 'key' => 'setting2', 'value_json' => ['v' => 2]]);
        $this->assertGreaterThanOrEqual(2, $this->tenant->settings()->count());
    }

    public function test_tenant_fillable_fields(): void
    {
        $tenant = new Tenant;
        $this->assertContains('name', $tenant->getFillable());
    }

    // ── TenantSetting ──

    public function test_tenant_setting_belongs_to_tenant(): void
    {
        $setting = TenantSetting::create([
            'tenant_id' => $this->tenant->id,
            'key' => 'test_key',
            'value_json' => ['value' => 'test_value'],
        ]);

        $this->assertEquals($this->tenant->id, $setting->tenant_id);
    }

    // ── User ──

    public function test_user_belongs_to_many_tenants(): void
    {
        $this->assertGreaterThanOrEqual(1, $this->user->tenants()->count());
    }

    public function test_user_has_current_tenant(): void
    {
        $this->assertNotNull($this->user->current_tenant_id);
    }

    public function test_user_fillable_fields(): void
    {
        $user = new User;
        $fillable = $user->getFillable();
        $this->assertContains('name', $fillable);
        $this->assertContains('email', $fillable);
    }

    // ── SystemSetting ──

    public function test_system_setting_creation(): void
    {
        $setting = SystemSetting::create([
            'tenant_id' => $this->tenant->id,
            'key' => 'company_name',
            'value' => 'Kalibrium',
        ]);

        $this->assertEquals('Kalibrium', $setting->value);
    }

    // ── AuditLog ──

    public function test_audit_log_belongs_to_user(): void
    {
        // sec-16: tenant_id/user_id saíram de $fillable; uso legítimo via log().
        $log = AuditLog::log(
            'created',
            'Customer created',
            Customer::factory()->create(['tenant_id' => $this->tenant->id])
        );

        $this->assertInstanceOf(User::class, $log->user);
        $this->assertSame($this->user->id, $log->user_id);
    }

    public function test_audit_log_fillable_fields(): void
    {
        // sec-16: $fillable restrito a action/description/auditable/payload.
        // tenant_id/user_id/ip_address/user_agent/created_at não são mass-assignable.
        $fillable = (new AuditLog)->getFillable();
        $this->assertContains('action', $fillable);
        $this->assertContains('description', $fillable);
        $this->assertNotContains('user_id', $fillable, 'sec-16: user_id saiu de $fillable');
        $this->assertNotContains('tenant_id', $fillable, 'sec-16: tenant_id saiu de $fillable');
    }

    // ── NumberingSequence ──

    public function test_numbering_sequence_belongs_to_tenant(): void
    {
        $seq = NumberingSequence::create([
            'tenant_id' => $this->tenant->id,
            'entity' => 'work_order',
            'prefix' => 'OS-',
            'next_number' => 1,
            'padding' => 6,
        ]);

        $this->assertEquals($this->tenant->id, $seq->tenant_id);
    }

    // ── RecurringContract ──

    public function test_recurring_contract_belongs_to_customer(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $contract = RecurringContract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);

        $this->assertInstanceOf(Customer::class, $contract->customer);
    }

    public function test_recurring_contract_has_many_items(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $contract = RecurringContract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);

        $this->assertInstanceOf(HasMany::class, $contract->items());
    }

    public function test_recurring_contract_soft_delete(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $contract = RecurringContract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);

        $contract->delete();
        $this->assertNull(RecurringContract::find($contract->id));
        $this->assertNotNull(RecurringContract::withTrashed()->find($contract->id));
    }

    // ── Import ──

    public function test_import_belongs_to_user(): void
    {
        $import = Import::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $this->assertInstanceOf(User::class, $import->user);
    }

    public function test_import_belongs_to_tenant(): void
    {
        $import = Import::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $this->assertEquals($this->tenant->id, $import->tenant_id);
    }

    // ── PushSubscription ──

    public function test_push_subscription_belongs_to_user(): void
    {
        $sub = PushSubscription::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test',
            'p256dh_key' => 'test_p256dh',
            'auth_key' => 'test_auth',
        ]);

        $this->assertInstanceOf(User::class, $sub->user);
    }
}
