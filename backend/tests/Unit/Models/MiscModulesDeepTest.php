<?php

namespace Tests\Unit\Models;

use App\Enums\AgendaItemType;
use App\Enums\AuditAction;
use App\Models\AgendaItem;
use App\Models\AgendaTemplate;
use App\Models\AuditLog;
use App\Models\EmailLog;
use App\Models\FiscalInvoice;
use App\Models\FiscalInvoiceItem;
use App\Models\Import;
use App\Models\Notification;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MiscModulesDeepTest extends TestCase
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

    // ── FiscalInvoice ──

    public function test_fiscal_invoice_creation(): void
    {
        $fi = FiscalInvoice::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertNotNull($fi);
    }

    public function test_fiscal_invoice_has_items(): void
    {
        $fi = FiscalInvoice::factory()->create(['tenant_id' => $this->tenant->id]);
        FiscalInvoiceItem::factory()->count(3)->create([
            'fiscal_invoice_id' => $fi->id,
            'tenant_id' => $this->tenant->id,
        ]);
        $this->assertGreaterThanOrEqual(3, $fi->items()->count());
    }

    public function test_fiscal_invoice_status(): void
    {
        $fi = FiscalInvoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);
        $fi->update(['status' => 'issued']);
        $this->assertEquals('issued', $fi->fresh()->status);
    }

    public function test_fiscal_invoice_number_unique(): void
    {
        FiscalInvoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'number' => 'NF-99999',
        ]);
        $this->expectException(QueryException::class);
        FiscalInvoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'number' => 'NF-99999',
        ]);
    }

    // ── EmailLog ──

    public function test_email_log_creation(): void
    {
        $el = EmailLog::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertNotNull($el);
    }

    public function test_email_log_status_sent(): void
    {
        $el = EmailLog::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'sent',
        ]);
        $this->assertEquals('sent', $el->status);
    }

    public function test_email_log_status_failed(): void
    {
        $el = EmailLog::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'failed',
            'error' => 'Connection timeout',
        ]);
        $this->assertEquals('failed', $el->status);
        $this->assertNotNull($el->error);
    }

    // ── Notification ──

    public function test_notification_creation(): void
    {
        $n = Notification::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);
        $this->assertNotNull($n);
    }

    public function test_notification_mark_as_read(): void
    {
        $n = Notification::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'read_at' => null,
        ]);
        $n->update(['read_at' => now()]);
        $this->assertNotNull($n->fresh()->read_at);
    }

    // ── AuditLog ──

    public function test_audit_log_creation(): void
    {
        $al = AuditLog::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'action' => 'created',
            'model_type' => 'Customer',
        ]);
        $this->assertSame(AuditAction::CREATED, $al->action);
    }

    public function test_audit_log_has_old_and_new_values(): void
    {
        $al = AuditLog::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'action' => 'updated',
            'old_values' => ['name' => 'Antigo'],
            'new_values' => ['name' => 'Novo'],
        ]);
        $al->refresh();
        $this->assertIsArray($al->old_values);
        $this->assertIsArray($al->new_values);
    }

    // ── Import ──

    public function test_import_creation(): void
    {
        $import = Import::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'customers',
            'status' => 'processing',
        ]);
        $this->assertEquals('processing', $import->status);
    }

    public function test_import_completion(): void
    {
        $import = Import::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'status' => 'processing',
        ]);
        $import->update([
            'status' => 'completed',
            'rows_processed' => 150,
            'rows_failed' => 3,
        ]);
        $this->assertEquals('completed', $import->fresh()->status);
        $this->assertEquals(150, $import->fresh()->rows_processed);
    }

    // ── SystemSetting ──

    public function test_system_setting_creation(): void
    {
        $ss = SystemSetting::factory()->create([
            'key' => 'app.timezone',
            'value' => 'America/Sao_Paulo',
        ]);
        $this->assertEquals('America/Sao_Paulo', $ss->value);
    }

    // ── AgendaItem ──

    public function test_agenda_item_creation(): void
    {
        $ai = AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'tarefa',
        ]);
        $this->assertSame(AgendaItemType::TAREFA, $ai->type);
    }

    public function test_agenda_item_completion(): void
    {
        $ai = AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'completed' => false,
        ]);
        $ai->update(['completed' => true, 'completed_at' => now()]);
        $this->assertTrue($ai->fresh()->completed);
    }

    public function test_agenda_item_priority(): void
    {
        foreach (['low', 'medium', 'high', 'urgent'] as $priority) {
            $ai = AgendaItem::factory()->create([
                'tenant_id' => $this->tenant->id,
                'user_id' => $this->user->id,
                'priority' => $priority,
            ]);
            $this->assertSame($priority, $ai->priority->value);
        }
    }

    public function test_agenda_item_due_date(): void
    {
        $ai = AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'due_at' => now()->addDays(5),
        ]);
        $this->assertTrue($ai->due_at->isFuture());
    }

    // ── AgendaTemplate ──

    public function test_agenda_template_creation(): void
    {
        $at = AgendaTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertNotNull($at);
    }
}
