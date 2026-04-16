<?php

namespace Tests\Feature\Console;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

class CheckExpiredQuotesTest extends TestCase
{
    public function test_check_expired_quotes_command_updates_status_and_logs_audit(): void
    {
        $tenant = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $seller = User::factory()->create(['tenant_id' => $tenant->id]);

        $expiredQuote = Quote::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'seller_id' => $seller->id,
            'status' => Quote::STATUS_SENT,
            'valid_until' => today()->subDay(),
            'quote_number' => 'ORC-EXP-001',
        ]);

        $validQuote = Quote::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'seller_id' => $seller->id,
            'status' => Quote::STATUS_SENT,
            'valid_until' => today()->addDay(),
            'quote_number' => 'ORC-VAL-001',
        ]);

        $approvedQuote = Quote::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'seller_id' => $seller->id,
            'status' => Quote::STATUS_APPROVED,
            'valid_until' => today()->subDay(),
            'quote_number' => 'ORC-APP-001',
        ]);

        $internallyApprovedExpiredQuote = Quote::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'seller_id' => $seller->id,
            'status' => Quote::STATUS_INTERNALLY_APPROVED,
            'valid_until' => today()->subDay(),
            'quote_number' => 'ORC-INT-001',
        ]);

        $todayQuote = Quote::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'seller_id' => $seller->id,
            'status' => Quote::STATUS_SENT,
            'valid_until' => today(),
            'quote_number' => 'ORC-TODAY-001',
        ]);

        $this->artisan('quotes:check-expired')
            ->expectsOutput('Marked 2 quote(s) as expired.')
            ->assertExitCode(0);

        $this->assertEquals(Quote::STATUS_EXPIRED, $expiredQuote->fresh()->status->value ?? $expiredQuote->fresh()->status);
        $this->assertEquals(Quote::STATUS_SENT, $validQuote->fresh()->status->value ?? $validQuote->fresh()->status);
        $this->assertEquals(Quote::STATUS_APPROVED, $approvedQuote->fresh()->status->value ?? $approvedQuote->fresh()->status);
        $this->assertEquals(Quote::STATUS_EXPIRED, $internallyApprovedExpiredQuote->fresh()->status->value ?? $internallyApprovedExpiredQuote->fresh()->status);
        $this->assertEquals(Quote::STATUS_SENT, $todayQuote->fresh()->status->value ?? $todayQuote->fresh()->status);

        $expiredAudit = AuditLog::query()
            ->where('tenant_id', $tenant->id)
            ->where('auditable_type', Quote::class)
            ->where('auditable_id', $expiredQuote->id)
            ->where('action', 'status_changed')
            ->latest('created_at')
            ->first();

        $internalExpiredAudit = AuditLog::query()
            ->where('tenant_id', $tenant->id)
            ->where('auditable_type', Quote::class)
            ->where('auditable_id', $internallyApprovedExpiredQuote->id)
            ->where('action', 'status_changed')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($expiredAudit);
        $this->assertStringContainsString('ORC-EXP-001', $expiredAudit->description);
        $this->assertStringContainsString('expirado automaticamente', $expiredAudit->description);

        $this->assertNotNull($internalExpiredAudit);
        $this->assertStringContainsString('ORC-INT-001', $internalExpiredAudit->description);
        $this->assertStringContainsString('expirado automaticamente', $internalExpiredAudit->description);
    }
}
