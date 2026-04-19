<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TenantUserDeepTest extends TestCase
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

    public function test_tenant_has_many_customers(): void
    {
        Customer::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
        $this->assertGreaterThanOrEqual(3, $this->tenant->customers()->count());
    }

    public function test_tenant_has_settings(): void
    {
        TenantSetting::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertGreaterThanOrEqual(1, $this->tenant->settings()->count());
    }

    public function test_tenant_name(): void
    {
        $t = Tenant::factory()->create(['name' => 'Kalibrium ERP']);
        $this->assertEquals('Kalibrium ERP', $t->name);
    }

    public function test_tenant_slug(): void
    {
        $t = Tenant::factory()->create(['slug' => 'kalibrium-erp']);
        $this->assertEquals('kalibrium-erp', $t->slug);
    }

    public function test_tenant_is_active(): void
    {
        $t = Tenant::factory()->create(['is_active' => true]);
        $this->assertTrue($t->is_active);
    }

    public function test_tenant_inactive(): void
    {
        $t = Tenant::factory()->create(['is_active' => false]);
        $this->assertFalse($t->is_active);
    }

    public function test_tenant_soft_deletes(): void
    {
        $t = Tenant::factory()->create();
        $t->delete();
        $this->assertNotNull(Tenant::withTrashed()->find($t->id));
    }

    // ── User ──

    public function test_user_belongs_to_tenant(): void
    {
        $this->assertEquals($this->tenant->id, $this->user->tenant_id);
    }

    public function test_user_has_current_tenant(): void
    {
        $this->assertEquals($this->tenant->id, $this->user->current_tenant_id);
    }

    public function test_user_has_many_tenants(): void
    {
        $this->assertGreaterThanOrEqual(1, $this->user->tenants()->count());
    }

    public function test_user_password_hidden(): void
    {
        $arr = $this->user->toArray();
        $this->assertArrayNotHasKey('password', $arr);
    }

    public function test_user_email_unique(): void
    {
        $this->expectException(QueryException::class);
        User::factory()->create(['email' => $this->user->email]);
    }

    public function test_user_password_hashed(): void
    {
        $user = User::factory()->create(['password' => 'plaintext123']);
        $this->assertTrue(Hash::check('plaintext123', $user->password));
    }

    public function test_user_has_roles(): void
    {
        $this->user->assignRole('admin');
        $this->assertTrue($this->user->hasRole('admin'));
    }

    public function test_user_can_switch_tenant(): void
    {
        $t2 = Tenant::factory()->create();
        $this->user->tenants()->attach($t2->id, ['is_default' => false]);
        $this->user->forceFill(['current_tenant_id' => $t2->id])->save();
        $this->assertEquals($t2->id, $this->user->fresh()->current_tenant_id);
    }

    public function test_user_soft_deletes(): void
    {
        $u = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $u->delete();
        $this->assertNotNull(User::withTrashed()->find($u->id));
    }

    public function test_user_full_name(): void
    {
        $u = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'João Silva',
        ]);
        $this->assertEquals('João Silva', $u->name);
    }

    public function test_user_timestamps(): void
    {
        $this->assertInstanceOf(Carbon::class, $this->user->created_at);
        $this->assertInstanceOf(Carbon::class, $this->user->updated_at);
    }

    // ── TenantSetting ──

    public function test_tenant_setting_key_value(): void
    {
        $setting = TenantSetting::factory()->create([
            'tenant_id' => $this->tenant->id,
            'key' => 'timezone',
            'value' => 'America/Sao_Paulo',
        ]);
        $this->assertEquals('timezone', $setting->key);
        $this->assertEquals('America/Sao_Paulo', $setting->value);
    }

    public function test_tenant_setting_belongs_to_tenant(): void
    {
        $setting = TenantSetting::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->assertInstanceOf(Tenant::class, $setting->tenant);
    }

    public function test_tenant_setting_unique_key_per_tenant(): void
    {
        TenantSetting::factory()->create([
            'tenant_id' => $this->tenant->id,
            'key' => 'unique_key_test',
        ]);
        $this->expectException(QueryException::class);
        TenantSetting::factory()->create([
            'tenant_id' => $this->tenant->id,
            'key' => 'unique_key_test',
        ]);
    }
}
