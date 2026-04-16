<?php

namespace Tests\Feature\Jobs;

use App\Jobs\QuoteExpirationAlertJob;
use App\Jobs\QuoteFollowUpJob;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

class QuoteAutomationJobsTest extends TestCase
{
    public function test_quote_expiration_alert_job_considers_current_expirable_statuses_and_date_granularity(): void
    {
        $tenant = Tenant::factory()->create();
        app()->instance('current_tenant_id', $tenant->id);

        $seller = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $internallyApproved = Quote::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'seller_id' => $seller->id,
            'quote_number' => 'ORC-ALERT-JOB-001',
            'status' => Quote::STATUS_INTERNALLY_APPROVED,
            'valid_until' => today()->addDays(2),
        ]);

        $validToday = Quote::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'seller_id' => $seller->id,
            'quote_number' => 'ORC-ALERT-JOB-002',
            'status' => Quote::STATUS_SENT,
            'valid_until' => today(),
        ]);

        $outsideWindow = Quote::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'seller_id' => $seller->id,
            'quote_number' => 'ORC-ALERT-JOB-003',
            'status' => Quote::STATUS_SENT,
            'valid_until' => today()->addDays(7),
        ]);

        (new QuoteExpirationAlertJob)->handle();

        $internalAlertAudit = AuditLog::query()
            ->where('tenant_id', $tenant->id)
            ->where('auditable_type', Quote::class)
            ->where('auditable_id', $internallyApproved->id)
            ->where('action', 'expiration_alert')
            ->latest('created_at')
            ->first();

        $todayAlertAudit = AuditLog::query()
            ->where('tenant_id', $tenant->id)
            ->where('auditable_type', Quote::class)
            ->where('auditable_id', $validToday->id)
            ->where('action', 'expiration_alert')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($internalAlertAudit);
        $this->assertStringContainsString('ORC-ALERT-JOB-001', $internalAlertAudit->description);
        $this->assertStringContainsString('2 dia(s)', $internalAlertAudit->description);

        $this->assertNotNull($todayAlertAudit);
        $this->assertStringContainsString('ORC-ALERT-JOB-002', $todayAlertAudit->description);
        $this->assertStringContainsString('0 dia(s)', $todayAlertAudit->description);

        $this->assertDatabaseMissing('audit_logs', [
            'tenant_id' => $tenant->id,
            'auditable_type' => Quote::class,
            'auditable_id' => $outsideWindow->id,
            'action' => 'expiration_alert',
        ]);
    }

    public function test_quote_follow_up_job_increments_counter_and_uses_specific_audit_action(): void
    {
        $tenant = Tenant::factory()->create();
        app()->instance('current_tenant_id', $tenant->id);

        $seller = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'seller_id' => $seller->id,
            'quote_number' => 'ORC-FUP-JOB-001',
            'status' => Quote::STATUS_SENT,
            'sent_at' => now()->subDays(4),
            'followup_count' => 0,
            'last_followup_at' => null,
        ]);

        (new QuoteFollowUpJob)->handle();

        $quote->refresh();

        $this->assertSame(1, $quote->followup_count);
        $this->assertNotNull($quote->last_followup_at);

        $auditLog = AuditLog::query()
            ->where('tenant_id', $tenant->id)
            ->where('auditable_type', Quote::class)
            ->where('auditable_id', $quote->id)
            ->where('action', 'followup_reminder')
            ->latest('id')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame('followup_reminder', $auditLog->action->value);
        $this->assertStringContainsString('Follow-up #1', $auditLog->description);
        $this->assertStringContainsString('ORC-FUP-JOB-001', $auditLog->description);
    }
}
