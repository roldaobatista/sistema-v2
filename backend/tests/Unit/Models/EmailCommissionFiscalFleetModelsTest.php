<?php

namespace Tests\Unit\Models;

use App\Models\CommissionDispute;
use App\Models\CommissionEvent;
use App\Models\CommissionRule;
use App\Models\CommissionSettlement;
use App\Models\Email;
use App\Models\EmailAccount;
use App\Models\FiscalNote;
use App\Models\FleetVehicle;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EmailCommissionFiscalFleetModelsTest extends TestCase
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

    // ── Email — Relationships ──

    private function createEmailAccount(): EmailAccount
    {
        return EmailAccount::create([
            'tenant_id' => $this->tenant->id,
            'label' => 'Test Account',
            'email_address' => 'test@kalibrium.com',
            'imap_host' => 'imap.kalibrium.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'test@kalibrium.com',
            'imap_password' => 'secret',
        ]);
    }

    private function createEmail(EmailAccount $account, array $overrides = []): Email
    {
        return Email::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'email_account_id' => $account->id,
            'message_id' => '<'.uniqid().'@kalibrium.com>',
            'from_address' => 'test@kalibrium.com',
            'to_addresses' => ['client@example.com'],
            'subject' => 'Test Subject',
            'body_text' => 'Body text',
            'date' => now(),
            'direction' => 'inbound',
        ], $overrides));
    }

    public function test_email_belongs_to_account(): void
    {
        $account = $this->createEmailAccount();
        $email = $this->createEmail($account);

        $this->assertInstanceOf(EmailAccount::class, $email->account);
    }

    public function test_email_has_many_attachments(): void
    {
        $account = $this->createEmailAccount();
        $email = $this->createEmail($account, ['subject' => 'Attachments']);

        $this->assertInstanceOf(HasMany::class, $email->attachments());
    }

    public function test_email_guarded_fields(): void
    {
        $email = new Email;
        $guarded = $email->getGuarded();

        $this->assertContains('id', $guarded);
        $this->assertCount(1, $guarded);
    }

    // ── CommissionRule — Relationships ──

    public function test_commission_rule_belongs_to_tenant(): void
    {
        $rule = CommissionRule::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertEquals($this->tenant->id, $rule->tenant_id);
    }

    public function test_commission_rule_fillable_fields(): void
    {
        $rule = new CommissionRule;
        $this->assertContains('tenant_id', $rule->getFillable());
        $this->assertContains('name', $rule->getFillable());
    }

    // ── CommissionEvent — Relationships ──

    public function test_commission_event_belongs_to_tenant(): void
    {
        $event = CommissionEvent::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertEquals($this->tenant->id, $event->tenant_id);
    }

    public function test_commission_event_belongs_to_user(): void
    {
        $event = CommissionEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $this->assertInstanceOf(User::class, $event->user);
    }

    // ── CommissionSettlement — Relationships ──

    public function test_commission_settlement_belongs_to_tenant(): void
    {
        $settlement = CommissionSettlement::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertEquals($this->tenant->id, $settlement->tenant_id);
    }

    // ── CommissionDispute — Relationships ──

    public function test_commission_dispute_belongs_to_tenant(): void
    {
        $dispute = CommissionDispute::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertEquals($this->tenant->id, $dispute->tenant_id);
    }

    // ── FiscalNote — Relationships ──

    public function test_fiscal_note_belongs_to_tenant(): void
    {
        $fn = FiscalNote::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertEquals($this->tenant->id, $fn->tenant_id);
    }

    public function test_fiscal_note_fillable_fields(): void
    {
        $fn = new FiscalNote;
        $this->assertContains('tenant_id', $fn->getFillable());
        $this->assertContains('status', $fn->getFillable());
    }

    public function test_fiscal_note_soft_delete(): void
    {
        $fn = FiscalNote::factory()->create(['tenant_id' => $this->tenant->id]);
        $fn->delete();

        $this->assertNull(FiscalNote::find($fn->id));
        $this->assertNotNull(FiscalNote::withTrashed()->find($fn->id));
    }

    // ── FleetVehicle — Relationships ──

    public function test_fleet_vehicle_belongs_to_tenant(): void
    {
        $vehicle = FleetVehicle::create([
            'tenant_id' => $this->tenant->id,
            'plate' => 'ABC-1234',
            'brand' => 'VW',
            'model' => 'Saveiro',
            'year' => 2024,
            'status' => 'active',
        ]);

        $this->assertEquals($this->tenant->id, $vehicle->tenant_id);
    }

    public function test_fleet_vehicle_fillable_fields(): void
    {
        $vehicle = new FleetVehicle;
        $fillable = $vehicle->getFillable();
        $this->assertContains('plate', $fillable);
        $this->assertContains('brand', $fillable);
    }

    // ── Notification — Relationships ──

    public function test_notification_belongs_to_user(): void
    {
        $notif = Notification::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'info',
            'title' => 'Test notification',
            'message' => 'This is a test',
        ]);

        $this->assertInstanceOf(User::class, $notif->user);
    }

    public function test_notification_fillable_fields(): void
    {
        $notif = new Notification;
        $fillable = $notif->getFillable();
        $this->assertContains('user_id', $fillable);
        $this->assertContains('title', $fillable);
        $this->assertContains('type', $fillable);
    }
}
