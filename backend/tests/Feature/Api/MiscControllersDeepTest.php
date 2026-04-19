<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\CheckPermission;
use App\Models\AgendaItem;
use App\Models\Customer;
use App\Models\EmailLog;
use App\Models\FiscalInvoice;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MiscControllersDeepTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->user->assignRole('admin');

    }

    // ── Agenda ──

    public function test_agenda_index(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/agenda');
        $response->assertOk();
    }

    public function test_agenda_store(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/agenda', [
            'title' => 'Reunião com cliente',
            'type' => 'reuniao',
            'data_hora' => now()->addDays(1)->format('Y-m-d H:i:s'),
        ]);
        $response->assertCreated();
    }

    public function test_agenda_update(): void
    {
        $ai = AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);
        $response = $this->actingAs($this->user)->putJson("/api/v1/agenda/{$ai->id}", [
            'title' => 'Atualizado',
        ]);
        $response->assertOk();
    }

    public function test_agenda_destroy(): void
    {
        $ai = AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/agenda/{$ai->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('central_items', ['id' => $ai->id]);
    }

    public function test_agenda_complete(): void
    {
        $ai = AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'completed' => false,
        ]);
        $response = $this->actingAs($this->user)->putJson("/api/v1/agenda/{$ai->id}/complete");
        $response->assertOk();
    }

    // ── Notifications ──

    public function test_notifications_index(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/notifications');
        $response->assertOk();
    }

    public function test_notifications_mark_read(): void
    {
        $n = Notification::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);
        $response = $this->actingAs($this->user)->putJson("/api/v1/notifications/{$n->id}/read");
        $response->assertOk();
    }

    public function test_notifications_mark_all_read(): void
    {
        $response = $this->actingAs($this->user)->putJson('/api/v1/notifications/read-all');
        $response->assertOk();
    }

    // ── Fiscal Invoices ──

    public function test_fiscal_invoices_index(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/fiscal-invoices');
        $response->assertOk();
    }

    public function test_fiscal_invoices_store(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $response = $this->actingAs($this->user)->postJson('/api/v1/fiscal-invoices', [
            'customer_id' => $customer->id,
            'type' => 'nfse',
            'amount' => '5000.00',
        ]);
        $response->assertSuccessful(); // UNMASKED: expects success, was [201, 422]
    }

    public function test_fiscal_invoices_show(): void
    {
        $fi = FiscalInvoice::factory()->create(['tenant_id' => $this->tenant->id]);
        $response = $this->actingAs($this->user)->getJson("/api/v1/fiscal-invoices/{$fi->id}");
        $response->assertOk();
    }

    // ── Email Logs ──

    public function test_email_logs_index(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/email-logs');
        $response->assertOk();
    }

    public function test_email_logs_show(): void
    {
        $el = EmailLog::factory()->create(['tenant_id' => $this->tenant->id]);
        $response = $this->actingAs($this->user)->getJson("/api/v1/email-logs/{$el->id}");
        $response->assertOk();
    }

    // ── Profile ──

    public function test_profile_show(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/profile');
        $response->assertOk();
    }

    public function test_profile_update(): void
    {
        $response = $this->actingAs($this->user)->putJson('/api/v1/profile', [
            'name' => 'Nome Atualizado',
        ]);
        $response->assertOk();
    }

    // ── Tenant Switch ──

    public function test_tenant_switch(): void
    {
        $t2 = Tenant::factory()->create();
        $this->user->tenants()->attach($t2->id, ['is_default' => false]);
        $response = $this->actingAs($this->user)->postJson('/api/v1/tenant/switch', [
            'tenant_id' => $t2->id,
        ]);
        $response->assertOk();
    }

    // ── Unauthenticated ──

    public function test_agenda_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/agenda');
        $response->assertUnauthorized();
    }

    public function test_notifications_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/notifications');
        $response->assertUnauthorized();
    }
}
