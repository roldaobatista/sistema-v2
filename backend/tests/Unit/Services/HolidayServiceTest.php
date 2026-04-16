<?php

namespace Tests\Unit\Services;

use App\Models\BusinessHour;
use App\Models\Tenant;
use App\Models\TenantHoliday;
use App\Services\BrasilApiService;
use App\Services\HolidayService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class HolidayServiceTest extends TestCase
{
    use RefreshDatabase;

    private HolidayService $service;

    private BrasilApiService $brasilApi;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock BrasilApiService to avoid real HTTP calls in unit tests
        $this->brasilApi = Mockery::mock(BrasilApiService::class);
        $this->service = new HolidayService($this->brasilApi);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── getHolidayDates ────────────────────────────────────────

    public function test_get_holiday_dates_returns_formatted_dates(): void
    {
        $this->brasilApi->shouldReceive('holidays')
            ->once()
            ->with(2025)
            ->andReturn([
                ['date' => '2025-01-01', 'name' => 'Confraternização Universal'],
                ['date' => '2025-04-21', 'name' => 'Tiradentes'],
                ['date' => '2025-12-25', 'name' => 'Natal'],
            ]);

        $dates = $this->service->getHolidayDates(2025);

        $this->assertContains('2025-01-01', $dates);
        $this->assertContains('2025-04-21', $dates);
        $this->assertContains('2025-12-25', $dates);
        $this->assertCount(3, $dates);
    }

    public function test_get_holiday_dates_filters_null_dates(): void
    {
        $this->brasilApi->shouldReceive('holidays')
            ->once()
            ->with(2025)
            ->andReturn([
                ['date' => '2025-01-01', 'name' => 'Confraternização'],
                ['date' => null, 'name' => 'Invalid'],
                ['name' => 'No date key'],
            ]);

        $dates = $this->service->getHolidayDates(2025);

        $this->assertCount(1, $dates);
        $this->assertContains('2025-01-01', $dates);
    }

    // ── isHoliday ──────────────────────────────────────────────

    public function test_is_holiday_returns_true_for_national_holiday(): void
    {
        $this->brasilApi->shouldReceive('holidays')
            ->once()
            ->with(2025)
            ->andReturn([
                ['date' => '2025-12-25', 'name' => 'Natal'],
            ]);

        $christmas = Carbon::parse('2025-12-25');
        $result = $this->service->isHoliday($christmas);

        $this->assertTrue($result);
    }

    public function test_is_holiday_returns_false_for_regular_day(): void
    {
        $this->brasilApi->shouldReceive('holidays')
            ->once()
            ->with(2025)
            ->andReturn([
                ['date' => '2025-12-25', 'name' => 'Natal'],
            ]);

        $regularDay = Carbon::parse('2025-06-15'); // Sunday but not a holiday
        $result = $this->service->isHoliday($regularDay);

        $this->assertFalse($result);
    }

    public function test_is_holiday_includes_tenant_custom_holidays(): void
    {
        $tenant = Tenant::factory()->create();

        // No national holidays
        $this->brasilApi->shouldReceive('holidays')
            ->once()
            ->with(2025)
            ->andReturn([]);

        // Tenant has a custom holiday on a specific date
        TenantHoliday::create([
            'tenant_id' => $tenant->id,
            'date' => '2025-07-10',
            'name' => 'Feriado Municipal',
        ]);

        $customHolidayDate = Carbon::parse('2025-07-10');
        $result = $this->service->isHoliday($customHolidayDate, $tenant->id);

        $this->assertTrue($result);
    }

    public function test_is_holiday_without_tenant_id_ignores_custom_holidays(): void
    {
        $tenant = Tenant::factory()->create();

        $this->brasilApi->shouldReceive('holidays')
            ->once()
            ->with(2025)
            ->andReturn([]);

        TenantHoliday::create([
            'tenant_id' => $tenant->id,
            'date' => '2025-07-10',
            'name' => 'Feriado Municipal',
        ]);

        $customHolidayDate = Carbon::parse('2025-07-10');
        // Called WITHOUT tenant_id — should not see tenant holiday
        $result = $this->service->isHoliday($customHolidayDate);

        $this->assertFalse($result);
    }

    // ── isBusinessDay ──────────────────────────────────────────

    public function test_is_business_day_returns_false_for_saturday(): void
    {
        $this->brasilApi->shouldReceive('holidays')
            ->once()
            ->andReturn([]);

        $saturday = Carbon::parse('2025-06-14'); // known Saturday
        $result = $this->service->isBusinessDay($saturday);

        $this->assertFalse($result);
    }

    public function test_is_business_day_returns_false_for_sunday(): void
    {
        $this->brasilApi->shouldReceive('holidays')
            ->once()
            ->andReturn([]);

        $sunday = Carbon::parse('2025-06-15'); // known Sunday
        $result = $this->service->isBusinessDay($sunday);

        $this->assertFalse($result);
    }

    public function test_is_business_day_returns_true_for_weekday(): void
    {
        $this->brasilApi->shouldReceive('holidays')
            ->once()
            ->andReturn([]);

        $monday = Carbon::parse('2025-06-16'); // known Monday, no holiday
        $result = $this->service->isBusinessDay($monday);

        $this->assertTrue($result);
    }

    public function test_is_business_day_returns_false_for_holiday_on_weekday(): void
    {
        $this->brasilApi->shouldReceive('holidays')
            ->once()
            ->with(2025)
            ->andReturn([
                ['date' => '2025-04-21', 'name' => 'Tiradentes'],
            ]);

        $tiradentes = Carbon::parse('2025-04-21'); // Monday but holiday
        $result = $this->service->isBusinessDay($tiradentes);

        $this->assertFalse($result);
    }

    public function test_is_business_day_respects_tenant_business_hours(): void
    {
        $tenant = Tenant::factory()->create();

        $this->brasilApi->shouldReceive('holidays')
            ->once()
            ->andReturn([]);

        // Tenant has Saturday as inactive (closed)
        $saturday = Carbon::parse('2025-06-14');
        BusinessHour::create([
            'tenant_id' => $tenant->id,
            'day_of_week' => $saturday->dayOfWeek, // 6 = Saturday
            'start_time' => '08:00',
            'end_time' => '12:00',
            'is_active' => false,
        ]);

        $result = $this->service->isBusinessDay($saturday, $tenant->id);

        $this->assertFalse($result);
    }

    // ── addBusinessDays ────────────────────────────────────────

    public function test_add_business_days_skips_weekends(): void
    {
        $this->brasilApi->shouldReceive('holidays')
            ->andReturn([]);

        // Friday June 13, 2025 + 1 business day = Monday June 16
        $friday = Carbon::parse('2025-06-13');
        $result = $this->service->addBusinessDays($friday, 1);

        $this->assertEquals('2025-06-16', $result->format('Y-m-d'));
    }

    public function test_add_business_days_skips_holidays(): void
    {
        $this->brasilApi->shouldReceive('holidays')
            ->andReturn([
                ['date' => '2025-06-16', 'name' => 'Feriado Inventado'],
            ]);

        // Friday June 13 + 1 business day, but June 16 (Monday) is a holiday
        // → should skip to June 17 (Tuesday)
        $friday = Carbon::parse('2025-06-13');
        $result = $this->service->addBusinessDays($friday, 1);

        $this->assertEquals('2025-06-17', $result->format('Y-m-d'));
    }

    public function test_add_business_days_zero_returns_same_date(): void
    {
        $this->brasilApi->shouldReceive('holidays')->never();

        $monday = Carbon::parse('2025-06-16');
        $result = $this->service->addBusinessDays($monday, 0);

        $this->assertEquals('2025-06-16', $result->format('Y-m-d'));
    }

    // ── addBusinessMinutes ─────────────────────────────────────

    public function test_add_business_minutes_within_same_day(): void
    {
        $this->brasilApi->shouldReceive('holidays')
            ->andReturn([]);

        // Start at 8:00 Monday, add 60 minutes
        $start = Carbon::parse('2025-06-16 08:00:00');
        $result = $this->service->addBusinessMinutes($start, 60);

        // Should be within the same business day
        $this->assertEquals('2025-06-16', $result->format('Y-m-d'));
    }

    public function test_add_business_minutes_skips_non_business_days(): void
    {
        $this->brasilApi->shouldReceive('holidays')
            ->andReturn([]);

        // Friday (2025-06-13) + 480 minutes (full business day) should advance past weekend
        $friday = Carbon::parse('2025-06-13 08:00:00');
        $result = $this->service->addBusinessMinutes($friday, 480 + 1); // slightly more than a full day

        // Result should be on Monday or later
        $this->assertGreaterThanOrEqual(
            Carbon::parse('2025-06-16'),
            $result
        );
    }

    public function test_add_business_minutes_zero_returns_same_time(): void
    {
        $this->brasilApi->shouldReceive('holidays')->never();

        $start = Carbon::parse('2025-06-16 10:00:00');
        $result = $this->service->addBusinessMinutes($start, 0);

        $this->assertEquals('2025-06-16 10:00:00', $result->format('Y-m-d H:i:s'));
    }
}
