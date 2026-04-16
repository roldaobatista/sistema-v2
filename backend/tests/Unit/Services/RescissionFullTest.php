<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Models\TimeClockAdjustment;
use App\Models\TimeClockEntry;
use App\Models\User;
use App\Services\RescissionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RescissionFullTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private RescissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'salary' => 3000,
            'admission_date' => now()->subYear(),
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);

        $this->service = app(RescissionService::class);
    }

    public function test_rescission_blocked_by_pending_adjustments(): void
    {
        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'regular',
            'clock_method' => 'selfie',
            'ip_address' => '127.0.0.1',
        ]);

        TimeClockAdjustment::create([
            'tenant_id' => $this->tenant->id,
            'time_clock_entry_id' => $entry->id,
            'requested_by' => $this->user->id,
            'original_clock_in' => now()->subHour(),
            'adjusted_clock_in' => now(),
            'reason' => 'test adjustment',
            'status' => 'pending',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('ajustes de ponto pendentes');

        $this->service->calculate(
            $this->user,
            'sem_justa_causa',
            now()
        );
    }

    public function test_justa_causa_no_notice_value(): void
    {
        $rescission = $this->service->calculate(
            $this->user,
            'justa_causa',
            now(),
            'indemnified'
        );

        // Justa causa = no notice payment
        $this->assertEquals(0, $rescission->notice_value);
    }

    public function test_sem_justa_causa_calculates_full_notice(): void
    {
        $rescission = $this->service->calculate(
            $this->user,
            'sem_justa_causa',
            now(),
            'indemnified'
        );

        // sem_justa_causa with indemnified notice should have notice_value > 0
        $this->assertGreaterThan(0, $rescission->notice_value);
        $this->assertNotNull($rescission->salary_balance_value);
        $this->assertNotNull($rescission->vacation_proportional_value);
        $this->assertNotNull($rescission->thirteenth_proportional_value);
    }

    public function test_acordo_mutuo_notice_is_half(): void
    {
        $rescission = $this->service->calculate(
            $this->user,
            'acordo_mutuo',
            now(),
            'indemnified'
        );

        // Acordo mutuo = 50% of full notice value
        // Service calculates: round(dailyRate * noticeDays * 0.5, 2)
        $salary = (float) $this->user->salary;
        $dailyRate = $salary / 30;
        $admissionDate = Carbon::parse($this->user->admission_date);
        $yearsOfService = $admissionDate->diffInYears(now());
        $noticeDays = min(30 + ($yearsOfService * 3), 90);
        $expectedHalfNotice = round($dailyRate * $noticeDays * 0.5, 2);

        $this->assertEqualsWithDelta($expectedHalfNotice, (float) $rescission->notice_value, 0.01);
    }

    public function test_pedido_demissao_no_notice_payment(): void
    {
        $rescission = $this->service->calculate(
            $this->user,
            'pedido_demissao',
            now(),
            'indemnified'
        );

        // Pedido de demissao = employee resigned, no notice payment
        $this->assertEquals(0, $rescission->notice_value);
    }

    public function test_rescission_calculates_salary_balance(): void
    {
        $terminationDate = Carbon::create(2026, 3, 15);

        $rescission = $this->service->calculate(
            $this->user,
            'sem_justa_causa',
            $terminationDate,
            'indemnified'
        );

        // Service: dailyRate = salary / 30, salaryBalanceDays = terminationDate->day (15)
        $salary = (float) $this->user->salary;
        $dailyRate = $salary / 30;
        $expectedBalance = round($dailyRate * 15, 2);

        $this->assertEquals($expectedBalance, (float) $rescission->salary_balance_value);
    }

    public function test_rescission_calculates_thirteenth_proportional(): void
    {
        $terminationDate = Carbon::create(2026, 6, 30);

        $rescission = $this->service->calculate(
            $this->user,
            'sem_justa_causa',
            $terminationDate,
            'indemnified'
        );

        // Service: thirteenthMonths = terminationDate->month (6), value = (salary / 12) * months
        $salary = (float) $this->user->salary;
        $expectedThirteenth = round(($salary / 12) * 6, 2);

        $this->assertEquals($expectedThirteenth, (float) $rescission->thirteenth_proportional_value);
    }

    public function test_rescission_with_approved_adjustments_proceeds(): void
    {
        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'regular',
            'clock_method' => 'selfie',
            'ip_address' => '127.0.0.1',
        ]);

        // Create an approved adjustment (should NOT block)
        TimeClockAdjustment::create([
            'tenant_id' => $this->tenant->id,
            'time_clock_entry_id' => $entry->id,
            'requested_by' => $this->user->id,
            'original_clock_in' => now()->subHour(),
            'adjusted_clock_in' => now(),
            'reason' => 'approved adjustment',
            'status' => 'approved',
        ]);

        // Should not throw
        $rescission = $this->service->calculate(
            $this->user,
            'sem_justa_causa',
            now()
        );

        $this->assertNotNull($rescission);
        $this->assertNotNull($rescission->id);
    }

    public function test_rescission_creates_record_in_database(): void
    {
        $rescission = $this->service->calculate(
            $this->user,
            'sem_justa_causa',
            now(),
            'indemnified'
        );

        $this->assertDatabaseHas('rescissions', [
            'id' => $rescission->id,
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'sem_justa_causa',
        ]);
    }
}
