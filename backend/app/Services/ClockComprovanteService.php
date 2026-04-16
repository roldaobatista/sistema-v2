<?php

namespace App\Services;

use App\Models\TimeClockEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class ClockComprovanteService
{
    /**
     * Generate comprovante data for a clock entry.
     */
    public function generateComprovante(TimeClockEntry $entry): array
    {
        $user = $entry->user;
        $geofence = $entry->geofenceLocation;

        return [
            'employee_name' => $user->name,
            'pis' => $user->pis_number,
            'cpf' => $user->cpf ? $this->maskCpf($user->cpf) : null,
            'nsr' => $entry->nsr,
            'date' => $entry->clock_in->format('d/m/Y'),
            'type' => $this->getTypeName($entry),
            'time' => $entry->clock_in->format('H:i:s'),
            'clock_in' => $entry->clock_in->toIso8601String(),
            'clock_out' => $entry->clock_out?->toIso8601String(),
            'clock_out_time' => $entry->clock_out?->format('H:i:s'),
            'break_start' => $entry->break_start?->toIso8601String(),
            'break_start_time' => $entry->break_start?->format('H:i:s'),
            'break_end' => $entry->break_end?->toIso8601String(),
            'break_end_time' => $entry->break_end?->format('H:i:s'),
            'latitude_in' => $entry->latitude_in,
            'longitude_in' => $entry->longitude_in,
            'latitude_out' => $entry->latitude_out,
            'longitude_out' => $entry->longitude_out,
            'address_in' => $entry->address_in,
            'address_out' => $entry->address_out,
            'location' => $geofence
                ? $geofence->name
                : ($entry->address_in ?? "Lat: {$entry->latitude_in}, Lng: {$entry->longitude_in}"),
            'record_hash' => $entry->record_hash,
            'hash' => $entry->record_hash,
            'employee_confirmation_hash' => $entry->employee_confirmation_hash,
            'confirmed_at' => $entry->confirmed_at?->toIso8601String(),
            'approval_status' => $entry->approval_status,
            'clock_method' => $entry->clock_method,
            'duration_hours' => $entry->duration_hours,
        ];
    }

    /**
     * Generate HTML comprovante file and return storage path.
     */
    public function generatePDF(TimeClockEntry $entry): string
    {
        $data = $this->generateComprovante($entry);
        $filename = "comprovante_{$entry->id}_{$entry->clock_in->format('Y-m-d_His')}.html";
        $path = "comprovantes/{$filename}";

        $html = $this->renderComprovanteHtml($data);
        Storage::put($path, $html);

        return $path;
    }

    /**
     * Generate espelho de ponto (monthly time sheet) for a user.
     */
    public function generateEspelho(int $userId, int $year, int $month, int $tenantId): array
    {
        $user = User::findOrFail($userId);

        $startDate = Carbon::create($year, $month, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth()->endOfDay();

        $entries = TimeClockEntry::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereBetween('clock_in', [$startDate, $endDate])
            ->orderBy('clock_in')
            ->get();

        $days = [];
        $totalMinutes = 0;
        $totalBreakMinutes = 0;
        $workDays = 0;

        // Group entries by date
        $grouped = $entries->groupBy(fn ($entry) => $entry->clock_in->format('Y-m-d'));

        // Iterate through each day of the month
        $current = $startDate->copy();
        while ($current->lte($endDate)) {
            $dateKey = $current->format('Y-m-d');
            $dayEntries = $grouped->get($dateKey, collect());

            $dayData = [
                'date' => $current->format('d/m/Y'),
                'day_of_week' => $this->getDayOfWeekPt($current->dayOfWeek),
                'entries' => [],
                'total_hours' => 0,
                'total_break_minutes' => 0,
            ];

            $dayTotalMinutes = 0;
            $dayBreakMinutes = 0;

            foreach ($dayEntries as $entry) {
                $entryData = [
                    'id' => $entry->id,
                    'clock_in' => $entry->clock_in->format('H:i'),
                    'clock_out' => $entry->clock_out?->format('H:i'),
                    'break_start' => $entry->break_start?->format('H:i'),
                    'break_end' => $entry->break_end?->format('H:i'),
                    'clock_method' => $entry->clock_method,
                    'approval_status' => $entry->approval_status,
                ];

                // Calculate worked minutes
                if ($entry->clock_out) {
                    $worked = $entry->clock_in->diffInMinutes($entry->clock_out);

                    // Subtract break time
                    $breakMins = 0;
                    if ($entry->break_start && $entry->break_end) {
                        $breakMins = $entry->break_start->diffInMinutes($entry->break_end);
                        $dayBreakMinutes += $breakMins;
                    }

                    $entryData['worked_minutes'] = $worked - $breakMins;
                    $entryData['break_minutes'] = $breakMins;
                    $dayTotalMinutes += $entryData['worked_minutes'];
                } else {
                    $entryData['worked_minutes'] = null;
                    $entryData['break_minutes'] = 0;
                }

                $dayData['entries'][] = $entryData;
            }

            $dayData['total_hours'] = round($dayTotalMinutes / 60, 2);
            $dayData['total_break_minutes'] = $dayBreakMinutes;

            if ($dayTotalMinutes > 0) {
                $workDays++;
                $totalMinutes += $dayTotalMinutes;
                $totalBreakMinutes += $dayBreakMinutes;
            }

            $days[] = $dayData;
            $current->addDay();
        }

        return [
            'employee' => [
                'id' => $user->id,
                'name' => $user->name,
                'pis' => $user->pis_number,
                'cpf' => $user->cpf ? $this->maskCpf($user->cpf) : null,
                'admission_date' => $user->admission_date?->format('d/m/Y'),
                'work_shift' => $user->work_shift,
                'cbo_code' => $user->cbo_code,
            ],
            'period' => [
                'year' => $year,
                'month' => $month,
                'month_name' => $this->getMonthNamePt($month),
                'start_date' => $startDate->format('d/m/Y'),
                'end_date' => $endDate->format('d/m/Y'),
            ],
            'days' => $days,
            'summary' => [
                'total_work_days' => $workDays,
                'total_hours' => round($totalMinutes / 60, 2),
                'total_minutes' => $totalMinutes,
                'total_break_minutes' => $totalBreakMinutes,
                'average_hours_per_day' => $workDays > 0 ? round(($totalMinutes / 60) / $workDays, 2) : 0,
            ],
        ];
    }

    private function renderComprovanteHtml(array $data): string
    {
        $pis = $data['pis'] ?? 'N/I';
        $cpf = $data['cpf'] ?? 'N/I';
        $nsr = $data['nsr'] ?? 'N/I';
        $hash = $data['hash'] ? substr($data['hash'], 0, 16).'...' : 'N/I';
        $method = $data['clock_method'] ?? 'N/I';
        $location = $data['location'] ?? 'N/I';
        $duration = $data['duration_hours'] ? $data['duration_hours'].'h' : '-';

        $clockOut = $data['clock_out_time'] ?? '-';
        $breakStart = $data['break_start_time'] ?? '-';
        $breakEnd = $data['break_end_time'] ?? '-';

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Comprovante de Registro de Ponto</title>
    <style>
        body { font-family: 'Courier New', monospace; font-size: 12px; max-width: 400px; margin: 20px auto; }
        .header { text-align: center; border-bottom: 2px dashed #000; padding-bottom: 10px; margin-bottom: 10px; }
        .header h2 { margin: 5px 0; font-size: 14px; }
        .field { display: flex; justify-content: space-between; margin: 4px 0; }
        .field .label { font-weight: bold; }
        .separator { border-top: 1px dashed #000; margin: 8px 0; }
        .footer { text-align: center; font-size: 10px; margin-top: 15px; color: #666; }
        .hash { word-break: break-all; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>COMPROVANTE DE REGISTRO DE PONTO</h2>
        <p>Portaria 671/2021 - MTP</p>
    </div>

    <div class="field"><span class="label">Funcionário:</span><span>{$data['employee_name']}</span></div>
    <div class="field"><span class="label">PIS/PASEP:</span><span>{$pis}</span></div>
    <div class="field"><span class="label">CPF:</span><span>{$cpf}</span></div>

    <div class="separator"></div>

    <div class="field"><span class="label">Data:</span><span>{$data['date']}</span></div>
    <div class="field"><span class="label">Entrada:</span><span>{$data['time']}</span></div>
    <div class="field"><span class="label">Início Intervalo:</span><span>{$breakStart}</span></div>
    <div class="field"><span class="label">Fim Intervalo:</span><span>{$breakEnd}</span></div>
    <div class="field"><span class="label">Saída:</span><span>{$clockOut}</span></div>
    <div class="field"><span class="label">Duração:</span><span>{$duration}</span></div>

    <div class="separator"></div>

    <div class="field"><span class="label">Método:</span><span>{$method}</span></div>
    <div class="field"><span class="label">Local:</span><span>{$location}</span></div>
    <div class="field"><span class="label">NSR:</span><span>{$nsr}</span></div>

    <div class="separator"></div>

    <div class="hash"><strong>Hash:</strong> {$hash}</div>

    <div class="footer">
        <p>Documento gerado eletronicamente.<br>Válido conforme Portaria 671/2021 do MTP.</p>
    </div>
</body>
</html>
HTML;
    }

    private function getTypeName(TimeClockEntry $entry): string
    {
        if ($entry->clock_out) {
            return 'Jornada Completa';
        }
        if ($entry->break_end) {
            return 'Retorno de Intervalo';
        }
        if ($entry->break_start) {
            return 'Início de Intervalo';
        }

        return 'Entrada';
    }

    private function maskCpf(string $cpf): string
    {
        if (strlen($cpf) !== 11) {
            return $cpf;
        }

        return '***.'.substr($cpf, 3, 3).'.'.substr($cpf, 6, 3).'-**';
    }

    private function getDayOfWeekPt(int $dayOfWeek): string
    {
        return match ($dayOfWeek) {
            0 => 'Dom',
            1 => 'Seg',
            2 => 'Ter',
            3 => 'Qua',
            4 => 'Qui',
            5 => 'Sex',
            6 => 'Sáb',
            default => '',
        };
    }

    private function getMonthNamePt(int $month): string
    {
        return match ($month) {
            1 => 'Janeiro',
            2 => 'Fevereiro',
            3 => 'Março',
            4 => 'Abril',
            5 => 'Maio',
            6 => 'Junho',
            7 => 'Julho',
            8 => 'Agosto',
            9 => 'Setembro',
            10 => 'Outubro',
            11 => 'Novembro',
            12 => 'Dezembro',
            default => '',
        };
    }
}
