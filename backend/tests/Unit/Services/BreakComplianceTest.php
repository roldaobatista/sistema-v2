<?php

namespace Tests\Unit\Services;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\JourneyEntry;
use App\Models\Tenant;
use App\Models\User;
use App\Services\JourneyCalculationService;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class BreakComplianceTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private JourneyCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        $this->service = new JourneyCalculationService;
    }

    private function createJourneyEntry(array $overrides = []): JourneyEntry
    {
        return JourneyEntry::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'date' => now()->toDateString(),
            'scheduled_hours' => 8,
            'worked_hours' => 8,
            'overtime_hours_50' => 0,
            'overtime_hours_100' => 0,
            'night_hours' => 0,
            'absence_hours' => 0,
            'hour_bank_balance' => 0,
            'status' => 'calculated',
        ], $overrides));
    }

    public function test_compliant_break_for_8h_shift(): void
    {
        $entry = $this->createJourneyEntry(['worked_hours' => 8]);

        $result = $this->service->enforceBreakCompliance($entry, 8.0, 60.0);

        $this->assertEquals('compliant', $result);
    }

    public function test_short_break_for_8h_shift(): void
    {
        $entry = $this->createJourneyEntry(['worked_hours' => 8]);

        $result = $this->service->enforceBreakCompliance($entry, 8.0, 30.0);

        $this->assertEquals('short_break', $result);
    }

    public function test_missing_break_for_8h_shift(): void
    {
        $entry = $this->createJourneyEntry(['worked_hours' => 8]);

        $result = $this->service->enforceBreakCompliance($entry, 8.0, null);

        $this->assertEquals('missing_break', $result);
    }

    public function test_short_shift_no_break_required(): void
    {
        $entry = $this->createJourneyEntry(['worked_hours' => 3]);

        $result = $this->service->enforceBreakCompliance($entry, 3.0, null);

        $this->assertEquals('compliant', $result);
    }
}
