<?php

namespace Tests\Unit\Services;

use App\Models\HourBankTransaction;
use App\Models\JourneyEntry;
use App\Models\JourneyRule;
use App\Models\Tenant;
use App\Models\User;
use App\Services\HourBankExpiryService;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class HourBankExpiryServiceTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private JourneyRule $rule;

    private HourBankExpiryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);

        $this->rule = JourneyRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_default' => true,
            'uses_hour_bank' => true,
            'hour_bank_expiry_months' => 6,
            'agreement_type' => 'individual',
        ]);

        $this->service = app(HourBankExpiryService::class);
    }

    private function createJourneyEntry(string $date, float $hourBankBalance): JourneyEntry
    {
        return JourneyEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'date' => $date,
            'journey_rule_id' => $this->rule->id,
            'scheduled_hours' => 8.0,
            'worked_hours' => 8.0,
            'overtime_hours_50' => max(0, $hourBankBalance),
            'overtime_hours_100' => 0,
            'night_hours' => 0,
            'absence_hours' => 0,
            'hour_bank_balance' => $hourBankBalance,
            'status' => 'calculated',
        ]);
    }

    public function test_individual_agreement_7_months_expires(): void
    {
        // 7 months ago: positive balance of 10h → should expire (> 6 months)
        $this->createJourneyEntry(now()->subMonths(7)->toDateString(), 10.0);

        $result = $this->service->processExpiry($this->user->id, $this->tenant->id);

        $this->assertTrue($result['processed']);
        $this->assertGreaterThan(0, $result['expired_hours']);
    }

    public function test_individual_agreement_5_months_does_not_expire(): void
    {
        // 5 months ago: positive balance → should NOT expire (< 6 months)
        $this->createJourneyEntry(now()->subMonths(5)->toDateString(), 10.0);

        $result = $this->service->processExpiry($this->user->id, $this->tenant->id);

        $this->assertTrue($result['processed']);
        $this->assertEquals(0, $result['expired_hours']);
    }

    public function test_collective_agreement_13_months_expires(): void
    {
        $this->rule->update(['agreement_type' => 'collective', 'hour_bank_expiry_months' => 12]);

        // 13 months ago → should expire (> 12 months)
        $this->createJourneyEntry(now()->subMonths(13)->toDateString(), 8.0);

        $result = $this->service->processExpiry($this->user->id, $this->tenant->id);

        $this->assertTrue($result['processed']);
        $this->assertGreaterThan(0, $result['expired_hours']);
    }

    public function test_collective_agreement_11_months_does_not_expire(): void
    {
        $this->rule->update(['agreement_type' => 'collective', 'hour_bank_expiry_months' => 12]);

        // 11 months ago → should NOT expire (< 12 months)
        $this->createJourneyEntry(now()->subMonths(11)->toDateString(), 8.0);

        $result = $this->service->processExpiry($this->user->id, $this->tenant->id);

        $this->assertTrue($result['processed']);
        $this->assertEquals(0, $result['expired_hours']);
    }

    public function test_negative_balance_does_not_expire(): void
    {
        // Negative balance (employee owes hours) → company absorbs, never expires
        $this->createJourneyEntry(now()->subMonths(7)->toDateString(), -5.0);

        $result = $this->service->processExpiry($this->user->id, $this->tenant->id);

        $this->assertTrue($result['processed']);
        $this->assertEquals(0, $result['expired_hours']);
        $this->assertEquals('Negative balance does not expire', $result['reason']);
    }

    public function test_hour_bank_disabled_skips(): void
    {
        $this->rule->update(['uses_hour_bank' => false]);

        $result = $this->service->processExpiry($this->user->id, $this->tenant->id);

        $this->assertFalse($result['processed']);
    }

    public function test_expiry_creates_transaction(): void
    {
        $this->createJourneyEntry(now()->subMonths(7)->toDateString(), 10.0);

        $result = $this->service->processExpiry($this->user->id, $this->tenant->id);

        $this->assertDatabaseHas('hour_bank_transactions', [
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'expiry',
        ]);

        $transaction = HourBankTransaction::where('user_id', $this->user->id)
            ->where('type', 'expiry')
            ->first();

        $this->assertNotNull($transaction);
        $this->assertLessThan(0, (float) $transaction->hours, 'Expiry transaction should have negative hours');
    }

    public function test_monthly_agreement_expires_after_1_month(): void
    {
        $this->rule->update(['agreement_type' => 'monthly']);

        // 2 months ago → should expire for monthly agreement
        $this->createJourneyEntry(now()->subMonths(2)->toDateString(), 5.0);

        $result = $this->service->processExpiry($this->user->id, $this->tenant->id);

        $this->assertTrue($result['processed']);
        $this->assertGreaterThan(0, $result['expired_hours']);
    }

    public function test_expiry_date_individual_max_6_months(): void
    {
        $this->rule->update([
            'agreement_type' => 'individual',
            'hour_bank_expiry_months' => 12, // tries to set 12, but individual max is 6
        ]);

        $cutoff = $this->service->getExpiryDate($this->rule);

        // Should cap at 6 months, not 12
        $expected = now()->subMonths(6)->startOfDay();
        $this->assertEquals($expected->toDateString(), $cutoff->toDateString(),
            'Individual agreement expiry should cap at 6 months');
    }

    public function test_expiry_date_collective_max_12_months(): void
    {
        $this->rule->update([
            'agreement_type' => 'collective',
            'hour_bank_expiry_months' => 18, // tries to set 18, but collective max is 12
        ]);

        $cutoff = $this->service->getExpiryDate($this->rule);

        $expected = now()->subMonths(12)->startOfDay();
        $this->assertEquals($expected->toDateString(), $cutoff->toDateString(),
            'Collective agreement expiry should cap at 12 months');
    }
}
