<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Models\TimeClockEntry;
use App\Models\User;
use App\Services\AFDExportService;
use App\Services\HashChainService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AFDExportServiceTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private AFDExportService $service;

    private HashChainService $hashService;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create([
            'document' => '12345678000190',
            'name' => 'Empresa Teste LTDA',
        ]);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'name' => 'João Silva',
            'pis_number' => '12345678901',
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);

        $this->service = new AFDExportService;
        $this->hashService = new HashChainService;
    }

    private function createHashedEntry(string $clockIn, ?string $clockOut = null): TimeClockEntry
    {
        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::parse($clockIn),
            'clock_out' => $clockOut ? Carbon::parse($clockOut) : null,
            'type' => 'regular',
            'approval_status' => 'approved',
            'clock_method' => 'selfie',
            'ip_address' => '127.0.0.1',
            'latitude_in' => -23.5505199,
            'longitude_in' => -46.6333094,
        ]);
        $this->hashService->applyHash($entry);
        if ($clockOut) {
            $entry->refresh();
            $this->hashService->reapplyHash($entry);
        }

        return $entry->refresh();
    }

    public function test_export_contains_header_type_1(): void
    {
        $this->createHashedEntry('2026-03-18 08:00', '2026-03-18 17:00');

        $content = $this->service->export(
            $this->tenant->id,
            Carbon::parse('2026-03-18'),
            Carbon::parse('2026-03-18')
        );

        $lines = explode("\r\n", $content);
        $this->assertStringStartsWith('1', $lines[0]);
    }

    public function test_export_contains_company_type_2(): void
    {
        $this->createHashedEntry('2026-03-18 08:00', '2026-03-18 17:00');

        $content = $this->service->export(
            $this->tenant->id,
            Carbon::parse('2026-03-18'),
            Carbon::parse('2026-03-18')
        );

        $lines = explode("\r\n", $content);
        $this->assertStringStartsWith('2', $lines[1]);
    }

    public function test_export_contains_clock_entries_type_3(): void
    {
        $this->createHashedEntry('2026-03-18 08:00', '2026-03-18 17:00');

        $content = $this->service->export(
            $this->tenant->id,
            Carbon::parse('2026-03-18'),
            Carbon::parse('2026-03-18')
        );

        $lines = explode("\r\n", $content);
        $type3Lines = array_filter($lines, fn ($l) => str_starts_with($l, '3'));
        $this->assertGreaterThanOrEqual(2, count($type3Lines), 'Should have at least clock_in + clock_out lines');
    }

    public function test_export_contains_trailer_type_9(): void
    {
        $this->createHashedEntry('2026-03-18 08:00', '2026-03-18 17:00');

        $content = $this->service->export(
            $this->tenant->id,
            Carbon::parse('2026-03-18'),
            Carbon::parse('2026-03-18')
        );

        $lines = explode("\r\n", $content);
        $lastLine = end($lines);
        $this->assertStringStartsWith('9', $lastLine);
    }

    public function test_header_contains_cnpj_14_chars(): void
    {
        $this->createHashedEntry('2026-03-18 08:00', '2026-03-18 17:00');

        $content = $this->service->export(
            $this->tenant->id,
            Carbon::parse('2026-03-18'),
            Carbon::parse('2026-03-18')
        );

        $lines = explode("\r\n", $content);
        $header = $lines[0];
        // pos 4-17: CNPJ 14 chars
        $cnpj = substr($header, 3, 14);
        $this->assertEquals(14, strlen($cnpj));
        $this->assertMatchesRegularExpression('/^\d{14}$/', $cnpj);
    }

    public function test_header_contains_company_name_50_chars(): void
    {
        $this->createHashedEntry('2026-03-18 08:00', '2026-03-18 17:00');

        $content = $this->service->export(
            $this->tenant->id,
            Carbon::parse('2026-03-18'),
            Carbon::parse('2026-03-18')
        );

        $lines = explode("\r\n", $content);
        $header = $lines[0];
        // pos 18-67: company name (50 chars)
        $name = substr($header, 17, 50);
        $this->assertEquals(50, strlen($name));
        $this->assertStringContainsString('Empresa Teste', $name);
    }

    public function test_header_contains_nsr_range_and_dates(): void
    {
        $this->createHashedEntry('2026-03-18 08:00', '2026-03-18 17:00');

        $content = $this->service->export(
            $this->tenant->id,
            Carbon::parse('2026-03-18'),
            Carbon::parse('2026-03-18')
        );

        $lines = explode("\r\n", $content);
        $header = $lines[0];
        // pos 68-76: first NSR (9 chars), pos 77-85: last NSR (9 chars)
        $firstNsr = substr($header, 67, 9);
        $lastNsr = substr($header, 76, 9);
        $this->assertMatchesRegularExpression('/^\d{9}$/', $firstNsr);
        $this->assertMatchesRegularExpression('/^\d{9}$/', $lastNsr);
        $this->assertGreaterThanOrEqual((int) $firstNsr, (int) $lastNsr);

        // pos 86-93: start date ddMMyyyy, pos 94-101: end date ddMMyyyy
        $startDate = substr($header, 85, 8);
        $endDate = substr($header, 93, 8);
        $this->assertEquals('18032026', $startDate);
        $this->assertEquals('18032026', $endDate);
    }

    public function test_company_line_has_correct_structure(): void
    {
        $this->createHashedEntry('2026-03-18 08:00', '2026-03-18 17:00');

        $content = $this->service->export(
            $this->tenant->id,
            Carbon::parse('2026-03-18'),
            Carbon::parse('2026-03-18')
        );

        $lines = explode("\r\n", $content);
        $company = $lines[1];

        // pos 1: type '2'
        $this->assertEquals('2', $company[0]);
        // pos 2-3: id_type '01' (CNPJ)
        $this->assertEquals('01', substr($company, 1, 2));
        // pos 4-17: CNPJ (14 chars)
        $cnpj = substr($company, 3, 14);
        $this->assertEquals('12345678000190', $cnpj);
        // pos 18-29: CEI (12 chars)
        $cei = substr($company, 17, 12);
        $this->assertEquals(12, strlen($cei));
        // pos 30-79: razão social (50 chars)
        $name = substr($company, 29, 50);
        $this->assertEquals(50, strlen($name));
        $this->assertStringContainsString('Empresa Teste', $name);
    }

    public function test_clock_line_has_correct_fixed_width_structure(): void
    {
        $this->createHashedEntry('2026-03-18 08:00', '2026-03-18 17:00');

        $content = $this->service->export(
            $this->tenant->id,
            Carbon::parse('2026-03-18'),
            Carbon::parse('2026-03-18')
        );

        $lines = explode("\r\n", $content);
        $type3Lines = array_values(array_filter($lines, fn ($l) => str_starts_with($l, '3')));
        $firstClockLine = $type3Lines[0];

        // Type 3: pos 1=type, 2-10=NSR(9), 11-12=event(2), 13-20=date(8), 21-24=time(4)
        $this->assertEquals('3', $firstClockLine[0]);
        $nsr = substr($firstClockLine, 1, 9);
        $this->assertMatchesRegularExpression('/^\d{9}$/', $nsr);

        // pos 11-12: event type — '01' for clock_in
        $eventType = substr($firstClockLine, 10, 2);
        $this->assertEquals('01', $eventType);

        // pos 13-20: date ddMMyyyy (8 chars)
        $date = substr($firstClockLine, 12, 8);
        $this->assertEquals('18032026', $date);

        // pos 21-24: time HHmm (4 chars) — exported in tenant timezone
        $time = substr($firstClockLine, 20, 4);
        $this->assertMatchesRegularExpression('/^\d{4}$/', $time);

        // pos 25-35: PIS (11 chars, zero-padded)
        $pis = substr($firstClockLine, 24, 11);
        $this->assertEquals(11, strlen($pis));
        $this->assertMatchesRegularExpression('/^\d{11}$/', $pis);

        // pos 36-85: employee name (50 chars)
        $name = substr($firstClockLine, 35, 50);
        $this->assertEquals(50, strlen($name));
        $this->assertStringContainsString('João Silva', $name);
    }

    public function test_clock_out_line_has_event_type_02(): void
    {
        $this->createHashedEntry('2026-03-18 08:00', '2026-03-18 17:00');

        $content = $this->service->export(
            $this->tenant->id,
            Carbon::parse('2026-03-18'),
            Carbon::parse('2026-03-18')
        );

        $lines = explode("\r\n", $content);
        $type3Lines = array_values(array_filter($lines, fn ($l) => str_starts_with($l, '3')));

        // Second line should be clock_out with event type '02'
        $clockOutLine = $type3Lines[1];
        $eventType = substr($clockOutLine, 10, 2);
        $this->assertEquals('02', $eventType);

        // time should be 4 digit HHmm format (tenant timezone applied)
        $time = substr($clockOutLine, 20, 4);
        $this->assertMatchesRegularExpression('/^\d{4}$/', $time);
    }

    public function test_clock_line_contains_lat_lng(): void
    {
        $this->createHashedEntry('2026-03-18 08:00', '2026-03-18 17:00');

        $content = $this->service->export(
            $this->tenant->id,
            Carbon::parse('2026-03-18'),
            Carbon::parse('2026-03-18')
        );

        $lines = explode("\r\n", $content);
        $type3Lines = array_values(array_filter($lines, fn ($l) => str_starts_with($l, '3')));
        $firstLine = $type3Lines[0];

        // After name (pos 36-85), lat (pos 86-96, 11 chars) and lng (pos 97-107, 11 chars)
        $lat = substr($firstLine, 85, 11);
        $lng = substr($firstLine, 96, 11);
        $this->assertEquals(11, strlen($lat));
        $this->assertEquals(11, strlen($lng));
        $this->assertStringContainsString('-23.55', $lat);
        $this->assertStringContainsString('-46.63', $lng);
    }

    public function test_export_throws_on_tampered_chain(): void
    {
        $entry = $this->createHashedEntry('2026-03-18 08:00', '2026-03-18 17:00');

        TimeClockEntry::withoutGlobalScopes()->where('id', $entry->id)
            ->update(['record_hash' => 'tampered_hash']);

        $this->expectException(\DomainException::class);

        $this->service->export(
            $this->tenant->id,
            Carbon::parse('2026-03-18'),
            Carbon::parse('2026-03-18')
        );
    }

    public function test_trailer_counts_match_entries(): void
    {
        $this->createHashedEntry('2026-03-18 08:00', '2026-03-18 17:00');
        $this->createHashedEntry('2026-03-19 08:00', '2026-03-19 17:00');

        $content = $this->service->export(
            $this->tenant->id,
            Carbon::parse('2026-03-18'),
            Carbon::parse('2026-03-19')
        );

        $lines = explode("\r\n", $content);
        $trailer = end($lines);

        // Trailer pos 2-10 = total type 3 records
        $totalType3 = (int) substr($trailer, 1, 9);
        $type3Lines = array_filter($lines, fn ($l) => str_starts_with($l, '3'));
        $this->assertEquals(count($type3Lines), $totalType3);
    }

    public function test_trailer_contains_type4_count_and_file_hash(): void
    {
        $this->createHashedEntry('2026-03-18 08:00', '2026-03-18 17:00');

        $content = $this->service->export(
            $this->tenant->id,
            Carbon::parse('2026-03-18'),
            Carbon::parse('2026-03-18')
        );

        $lines = explode("\r\n", $content);
        $trailer = end($lines);

        // pos 1: type '9'
        $this->assertEquals('9', $trailer[0]);
        // pos 2-10: type 3 count (9 chars)
        $type3Count = substr($trailer, 1, 9);
        $this->assertMatchesRegularExpression('/^\d{9}$/', $type3Count);
        // pos 11-19: type 4 count (9 chars)
        $type4Count = substr($trailer, 10, 9);
        $this->assertMatchesRegularExpression('/^\d{9}$/', $type4Count);
        // pos 20+: SHA-256 file hash (64 chars)
        $fileHash = substr($trailer, 19, 64);
        $this->assertEquals(64, strlen($fileHash));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $fileHash);
    }

    public function test_export_with_multiple_entries_generates_correct_line_count(): void
    {
        $this->createHashedEntry('2026-03-18 08:00', '2026-03-18 12:00');
        $this->createHashedEntry('2026-03-18 13:00', '2026-03-18 17:00');

        $content = $this->service->export(
            $this->tenant->id,
            Carbon::parse('2026-03-18'),
            Carbon::parse('2026-03-18')
        );

        $lines = explode("\r\n", $content);
        $type3Lines = array_filter($lines, fn ($l) => str_starts_with($l, '3'));
        // 2 entries × 2 events (clock_in + clock_out) = 4 type-3 lines
        $this->assertEquals(4, count($type3Lines));
    }

    public function test_empty_export_still_has_header_company_trailer(): void
    {
        // Create entry and hash it so chain is valid, but query for different date range
        $entry = $this->createHashedEntry('2026-03-18 08:00', '2026-03-18 17:00');

        $content = $this->service->export(
            $this->tenant->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-01')
        );

        $lines = array_filter(explode("\r\n", $content), fn ($l) => $l !== '');
        // Should have at least header (1) + company (2) + trailer (9)
        $this->assertGreaterThanOrEqual(3, count($lines));
        $this->assertStringStartsWith('1', $lines[array_key_first($lines)]);
        $this->assertStringStartsWith('9', end($lines));
    }
}
