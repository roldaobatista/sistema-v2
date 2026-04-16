<?php

namespace Tests\Unit\Services\RepairSeal;

use App\Models\InmetroSeal;
use App\Models\RepairSealAlert;
use App\Models\Tenant;
use App\Models\User;
use App\Services\HolidayService;
use App\Services\RepairSealDeadlineService;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RepairSealDeadlineServiceTest extends TestCase
{
    private Tenant $tenant;

    private User $technician;

    private RepairSealDeadlineService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        $this->tenant = Tenant::factory()->create();
        $this->technician = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->service = app(RepairSealDeadlineService::class);
    }

    public function test_calculate_deadline_returns_5_business_days(): void
    {
        $usedAt = now()->startOfDay();
        $deadline = $this->service->calculateDeadline($usedAt);

        $this->assertTrue($deadline->gt($usedAt));

        // Count business days between
        $holidayService = app(HolidayService::class);
        $days = 0;
        $current = $usedAt->copy();
        while ($current->lt($deadline)) {
            $current->addDay();
            if ($holidayService->isBusinessDay($current)) {
                $days++;
            }
        }

        $this->assertEquals(5, $days);
    }

    public function test_check_deadlines_creates_warning_at_3_days(): void
    {
        $seal = InmetroSeal::factory()->seloReparo()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->technician->id,
            'status' => InmetroSeal::STATUS_PENDING_PSEI,
            'psei_status' => InmetroSeal::PSEI_PENDING,
            'used_at' => now()->subWeekdays(3),
            'deadline_at' => now()->addWeekdays(2),
            'photo_path' => 'seals/test.jpg',
        ]);

        $stats = $this->service->checkAllDeadlines();

        $this->assertGreaterThanOrEqual(1, $stats['warnings']);

        $this->assertDatabaseHas('repair_seal_alerts', [
            'seal_id' => $seal->id,
            'technician_id' => $this->technician->id,
            'alert_type' => RepairSealAlert::TYPE_WARNING_3D,
            'severity' => RepairSealAlert::SEVERITY_WARNING,
        ]);
    }

    public function test_check_deadlines_creates_escalation_at_4_days(): void
    {
        $seal = InmetroSeal::factory()->seloReparo()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->technician->id,
            'status' => InmetroSeal::STATUS_PENDING_PSEI,
            'psei_status' => InmetroSeal::PSEI_PENDING,
            'used_at' => now()->subWeekdays(4),
            'deadline_at' => now()->addWeekday(),
            'photo_path' => 'seals/test.jpg',
        ]);

        $this->service->checkAllDeadlines();

        $this->assertDatabaseHas('repair_seal_alerts', [
            'seal_id' => $seal->id,
            'alert_type' => RepairSealAlert::TYPE_CRITICAL_4D,
            'severity' => RepairSealAlert::SEVERITY_CRITICAL,
        ]);

        $seal->refresh();
        $this->assertEquals(InmetroSeal::DEADLINE_CRITICAL, $seal->deadline_status);
    }

    public function test_check_deadlines_creates_overdue_at_5_days(): void
    {
        $seal = InmetroSeal::factory()->seloReparo()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->technician->id,
            'status' => InmetroSeal::STATUS_PENDING_PSEI,
            'psei_status' => InmetroSeal::PSEI_PENDING,
            'used_at' => now()->subWeekdays(6),
            'deadline_at' => now()->subWeekday(),
            'photo_path' => 'seals/test.jpg',
        ]);

        $this->service->checkAllDeadlines();

        $this->assertDatabaseHas('repair_seal_alerts', [
            'seal_id' => $seal->id,
            'alert_type' => RepairSealAlert::TYPE_OVERDUE_5D,
        ]);

        $seal->refresh();
        $this->assertEquals(InmetroSeal::DEADLINE_OVERDUE, $seal->deadline_status);
    }

    public function test_does_not_duplicate_alerts(): void
    {
        $seal = InmetroSeal::factory()->seloReparo()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->technician->id,
            'status' => InmetroSeal::STATUS_PENDING_PSEI,
            'psei_status' => InmetroSeal::PSEI_PENDING,
            'used_at' => now()->subWeekdays(3),
            'deadline_at' => now()->addWeekdays(2),
            'photo_path' => 'seals/test.jpg',
        ]);

        $this->service->checkAllDeadlines();
        $this->service->checkAllDeadlines();

        $alertCount = RepairSealAlert::where('seal_id', $seal->id)
            ->where('alert_type', RepairSealAlert::TYPE_WARNING_3D)
            ->count();

        $this->assertEquals(1, $alertCount);
    }

    public function test_resolve_deadline_marks_alerts_resolved(): void
    {
        $seal = InmetroSeal::factory()->seloReparo()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->technician->id,
            'status' => InmetroSeal::STATUS_PENDING_PSEI,
            'deadline_status' => InmetroSeal::DEADLINE_WARNING,
        ]);

        RepairSealAlert::factory()->warning3d()->create([
            'tenant_id' => $this->tenant->id,
            'seal_id' => $seal->id,
            'technician_id' => $this->technician->id,
        ]);

        $this->service->resolveDeadline($seal, $this->technician->id);

        $seal->refresh();
        $this->assertEquals(InmetroSeal::DEADLINE_RESOLVED, $seal->deadline_status);

        $this->assertDatabaseMissing('repair_seal_alerts', [
            'seal_id' => $seal->id,
            'resolved_at' => null,
        ]);
    }

    public function test_skips_confirmed_psei_seals(): void
    {
        InmetroSeal::factory()->seloReparo()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->technician->id,
            'status' => InmetroSeal::STATUS_REGISTERED,
            'psei_status' => InmetroSeal::PSEI_CONFIRMED,
            'used_at' => now()->subWeekdays(10),
            'photo_path' => 'seals/test.jpg',
        ]);

        $stats = $this->service->checkAllDeadlines();

        $this->assertEquals(0, $stats['warnings'] + $stats['escalations'] + $stats['overdue']);
    }
}
