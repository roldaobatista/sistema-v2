<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Models\TimeClockEntry;
use App\Models\User;
use App\Services\ClockComprovanteService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ClockComprovanteServiceTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private ClockComprovanteService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'name' => 'João da Silva',
            'cpf' => '12345678901',
            'pis_number' => '12345678901',
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);

        $this->service = app(ClockComprovanteService::class);
    }

    // ═══════════════════════════════════════════════════════════════
    // generateComprovante
    // ═══════════════════════════════════════════════════════════════

    public function test_generate_comprovante_returns_all_required_fields(): void
    {
        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::parse('2026-03-18 08:00:00'),
            'clock_out' => Carbon::parse('2026-03-18 17:00:00'),
            'type' => 'regular',
            'nsr' => 12345,
            'record_hash' => 'abc123hash',
            'clock_method' => 'gps',
            'latitude_in' => -23.550520,
            'longitude_in' => -46.633308,
        ]);

        $result = $this->service->generateComprovante($entry);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('employee_name', $result);
        $this->assertArrayHasKey('pis', $result);
        $this->assertArrayHasKey('cpf', $result);
        $this->assertArrayHasKey('date', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('time', $result);
        $this->assertArrayHasKey('clock_out_time', $result);
        $this->assertArrayHasKey('location', $result);
        $this->assertArrayHasKey('nsr', $result);
        $this->assertArrayHasKey('hash', $result);
        $this->assertArrayHasKey('clock_method', $result);
        $this->assertArrayHasKey('duration_hours', $result);
    }

    public function test_generate_comprovante_returns_correct_employee_data(): void
    {
        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::parse('2026-03-18 08:00:00'),
            'clock_out' => Carbon::parse('2026-03-18 17:00:00'),
            'type' => 'regular',
        ]);

        $result = $this->service->generateComprovante($entry);

        $this->assertEquals('João da Silva', $result['employee_name']);
        $this->assertEquals('12345678901', $result['pis']);
    }

    public function test_generate_comprovante_masks_cpf_correctly(): void
    {
        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::parse('2026-03-18 08:00:00'),
            'clock_out' => Carbon::parse('2026-03-18 17:00:00'),
            'type' => 'regular',
        ]);

        $result = $this->service->generateComprovante($entry);

        // CPF 12345678901 should be masked as ***.456.789-**
        $this->assertEquals('***.456.789-**', $result['cpf']);
    }

    public function test_generate_comprovante_with_complete_journey_returns_jornada_completa(): void
    {
        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::parse('2026-03-18 08:00:00'),
            'clock_out' => Carbon::parse('2026-03-18 17:00:00'),
            'type' => 'regular',
        ]);

        $result = $this->service->generateComprovante($entry);

        $this->assertEquals('Jornada Completa', $result['type']);
    }

    public function test_generate_comprovante_with_only_clock_in_returns_entrada(): void
    {
        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::parse('2026-03-18 08:00:00'),
            'clock_out' => null,
            'break_start' => null,
            'break_end' => null,
            'type' => 'regular',
        ]);

        $result = $this->service->generateComprovante($entry);

        $this->assertEquals('Entrada', $result['type']);
        $this->assertNull($result['clock_out_time']);
    }

    public function test_generate_comprovante_formats_date_and_time_correctly(): void
    {
        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::parse('2026-03-18 08:30:00'),
            'clock_out' => Carbon::parse('2026-03-18 17:45:00'),
            'type' => 'regular',
        ]);

        $result = $this->service->generateComprovante($entry);

        $this->assertEquals('18/03/2026', $result['date']);
        $this->assertEquals('08:30:00', $result['time']);
        $this->assertEquals('17:45:00', $result['clock_out_time']);
    }

    public function test_generate_comprovante_includes_break_times(): void
    {
        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::parse('2026-03-18 08:00:00'),
            'clock_out' => Carbon::parse('2026-03-18 17:00:00'),
            'break_start' => Carbon::parse('2026-03-18 12:00:00'),
            'break_end' => Carbon::parse('2026-03-18 13:00:00'),
            'type' => 'regular',
        ]);

        $result = $this->service->generateComprovante($entry);

        $this->assertEquals('12:00:00', $result['break_start_time']);
        $this->assertEquals('13:00:00', $result['break_end_time']);
    }

    public function test_generate_comprovante_without_geofence_shows_coordinates(): void
    {
        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::parse('2026-03-18 08:00:00'),
            'clock_out' => Carbon::parse('2026-03-18 17:00:00'),
            'type' => 'regular',
            'latitude_in' => -23.550520,
            'longitude_in' => -46.633308,
            'geofence_location_id' => null,
        ]);

        $result = $this->service->generateComprovante($entry);

        $this->assertStringContainsString('-23.55052', $result['location']);
        $this->assertStringContainsString('-46.633308', $result['location']);
    }

    public function test_generate_comprovante_with_null_cpf(): void
    {
        $userNoCpf = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'cpf' => null,
        ]);
        $userNoCpf->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $userNoCpf->id,
            'clock_in' => Carbon::parse('2026-03-18 08:00:00'),
            'clock_out' => Carbon::parse('2026-03-18 17:00:00'),
            'type' => 'regular',
        ]);

        $result = $this->service->generateComprovante($entry);

        $this->assertNull($result['cpf']);
    }

    // ═══════════════════════════════════════════════════════════════
    // generateEspelho
    // ═══════════════════════════════════════════════════════════════

    public function test_generate_espelho_returns_correct_structure(): void
    {
        $result = $this->service->generateEspelho(
            $this->user->id,
            2026,
            3,
            $this->tenant->id
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('employee', $result);
        $this->assertArrayHasKey('period', $result);
        $this->assertArrayHasKey('days', $result);
        $this->assertArrayHasKey('summary', $result);
    }

    public function test_generate_espelho_returns_correct_employee_data(): void
    {
        $result = $this->service->generateEspelho(
            $this->user->id,
            2026,
            3,
            $this->tenant->id
        );

        $this->assertEquals($this->user->id, $result['employee']['id']);
        $this->assertEquals('João da Silva', $result['employee']['name']);
        $this->assertEquals('12345678901', $result['employee']['pis']);
        $this->assertEquals('***.456.789-**', $result['employee']['cpf']);
    }

    public function test_generate_espelho_returns_correct_period(): void
    {
        $result = $this->service->generateEspelho(
            $this->user->id,
            2026,
            3,
            $this->tenant->id
        );

        $this->assertEquals(2026, $result['period']['year']);
        $this->assertEquals(3, $result['period']['month']);
        $this->assertEquals('Março', $result['period']['month_name']);
        $this->assertEquals('01/03/2026', $result['period']['start_date']);
        $this->assertEquals('31/03/2026', $result['period']['end_date']);
    }

    public function test_generate_espelho_returns_all_days_of_month(): void
    {
        $result = $this->service->generateEspelho(
            $this->user->id,
            2026,
            3,
            $this->tenant->id
        );

        // March has 31 days
        $this->assertCount(31, $result['days']);
    }

    public function test_generate_espelho_includes_entries_for_worked_days(): void
    {
        TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::parse('2026-03-10 08:00:00'),
            'clock_out' => Carbon::parse('2026-03-10 17:00:00'),
            'type' => 'regular',
        ]);

        $result = $this->service->generateEspelho(
            $this->user->id,
            2026,
            3,
            $this->tenant->id
        );

        // Day 10 is index 9 (0-based)
        $day10 = $result['days'][9];
        $this->assertEquals('10/03/2026', $day10['date']);
        $this->assertNotEmpty($day10['entries']);
        $this->assertCount(1, $day10['entries']);
        $this->assertEquals('08:00', $day10['entries'][0]['clock_in']);
        $this->assertEquals('17:00', $day10['entries'][0]['clock_out']);
    }

    public function test_generate_espelho_calculates_worked_minutes_correctly(): void
    {
        TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::parse('2026-03-10 08:00:00'),
            'clock_out' => Carbon::parse('2026-03-10 17:00:00'),
            'type' => 'regular',
        ]);

        $result = $this->service->generateEspelho(
            $this->user->id,
            2026,
            3,
            $this->tenant->id
        );

        $day10 = $result['days'][9];
        // 08:00 to 17:00 = 540 minutes = 9 hours, no break
        $this->assertEquals(540, $day10['entries'][0]['worked_minutes']);
        $this->assertEquals(9.0, $day10['total_hours']);
    }

    public function test_generate_espelho_subtracts_break_from_worked_minutes(): void
    {
        TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::parse('2026-03-10 08:00:00'),
            'clock_out' => Carbon::parse('2026-03-10 17:00:00'),
            'break_start' => Carbon::parse('2026-03-10 12:00:00'),
            'break_end' => Carbon::parse('2026-03-10 13:00:00'),
            'type' => 'regular',
        ]);

        $result = $this->service->generateEspelho(
            $this->user->id,
            2026,
            3,
            $this->tenant->id
        );

        $day10 = $result['days'][9];
        // 08:00-17:00 = 540min, break 60min, worked = 480min = 8h
        $this->assertEquals(480, $day10['entries'][0]['worked_minutes']);
        $this->assertEquals(60, $day10['entries'][0]['break_minutes']);
        $this->assertEquals(8.0, $day10['total_hours']);
        $this->assertEquals(60, $day10['total_break_minutes']);
    }

    public function test_generate_espelho_summary_totals_are_correct(): void
    {
        // Create 2 work days
        TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::parse('2026-03-10 08:00:00'),
            'clock_out' => Carbon::parse('2026-03-10 17:00:00'),
            'type' => 'regular',
        ]);

        TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::parse('2026-03-11 08:00:00'),
            'clock_out' => Carbon::parse('2026-03-11 17:00:00'),
            'type' => 'regular',
        ]);

        $result = $this->service->generateEspelho(
            $this->user->id,
            2026,
            3,
            $this->tenant->id
        );

        $summary = $result['summary'];
        $this->assertEquals(2, $summary['total_work_days']);
        $this->assertEquals(1080, $summary['total_minutes']); // 540 * 2
        $this->assertEquals(18.0, $summary['total_hours']); // 9h * 2
        $this->assertEquals(9.0, $summary['average_hours_per_day']);
    }

    public function test_generate_espelho_with_no_entries_returns_zero_summary(): void
    {
        $result = $this->service->generateEspelho(
            $this->user->id,
            2026,
            3,
            $this->tenant->id
        );

        $summary = $result['summary'];
        $this->assertEquals(0, $summary['total_work_days']);
        $this->assertEquals(0, $summary['total_minutes']);
        $this->assertEquals(0, $summary['total_hours']);
        $this->assertEquals(0, $summary['average_hours_per_day']);
    }

    public function test_generate_espelho_entry_without_clock_out_has_null_worked_minutes(): void
    {
        TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::parse('2026-03-10 08:00:00'),
            'clock_out' => null,
            'type' => 'regular',
        ]);

        $result = $this->service->generateEspelho(
            $this->user->id,
            2026,
            3,
            $this->tenant->id
        );

        $day10 = $result['days'][9];
        $this->assertNull($day10['entries'][0]['worked_minutes']);
        $this->assertEquals(0, $day10['entries'][0]['break_minutes']);
    }

    public function test_generate_espelho_shows_day_of_week_in_portuguese(): void
    {
        $result = $this->service->generateEspelho(
            $this->user->id,
            2026,
            3,
            $this->tenant->id
        );

        // March 1, 2026 is a Sunday
        $this->assertEquals('Dom', $result['days'][0]['day_of_week']);
        // March 2 is Monday
        $this->assertEquals('Seg', $result['days'][1]['day_of_week']);
    }
}
