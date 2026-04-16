<?php

namespace App\Services\ESocial;

use App\Models\ESocialEvent;
use App\Models\JourneyBlock;
use App\Models\JourneyEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @phpstan-type JourneyMonthSummary array{
 *     total_worked_hours: float,
 *     total_overtime_hours: float,
 *     total_night_hours: float,
 *     total_oncall_hours: float,
 *     total_travel_hours: float,
 *     working_days: int,
 *     absence_days: int
 * }
 */
class JourneyESocialGenerator
{
    /**
     * Generate S-1200 (Remuneração) events from closed journey days for a given month.
     *
     * @return Collection<int, ESocialEvent>
     */
    public function generateS1200ForMonth(int $tenantId, string $yearMonth): Collection
    {
        [$year, $month] = explode('-', $yearMonth);
        $startDate = Carbon::create((int) $year, (int) $month, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth()->endOfDay();

        $closedDays = JourneyEntry::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('is_closed', true)
            ->whereBetween('date', [$startDate, $endDate])
            ->with(['user', 'blocks'])
            ->get()
            ->groupBy('user_id');

        $events = collect();

        foreach ($closedDays as $userId => $days) {
            $user = $days->first()->user;
            if (! $user instanceof User) {
                continue;
            }

            $summary = $this->summarizeMonth($days);
            $xml = $this->buildS1200Xml($user, $yearMonth, $summary);

            $event = ESocialEvent::withoutGlobalScope('tenant')->create([
                'tenant_id' => $tenantId,
                'event_type' => 'S-1200',
                'related_type' => User::class,
                'related_id' => $userId,
                'xml_content' => $xml,
                'status' => 'pending',
                'environment' => config('app.env') === 'production' ? 'producao' : 'homologacao',
                'version' => 'S-1.3',
                'retry_count' => 0,
                'max_retries' => 3,
                'batch_id' => "S1200-{$yearMonth}-".Str::random(8),
            ]);

            $events->push($event);
        }

        return $events;
    }

    /**
     * Generate S-2230 (Afastamento) from journey days with absence.
     *
     * @return Collection<int, ESocialEvent>
     */
    public function generateS2230ForAbsences(int $tenantId, string $yearMonth): Collection
    {
        [$year, $month] = explode('-', $yearMonth);
        $startDate = Carbon::create((int) $year, (int) $month, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth()->endOfDay();

        $absenceDays = JourneyEntry::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('is_closed', true)
            ->whereBetween('date', [$startDate, $endDate])
            ->whereHas('blocks', function ($q) {
                $q->whereIn('classification', ['ausencia', 'atestado']);
            })
            ->with(['user', 'blocks'])
            ->get();

        $events = collect();

        foreach ($absenceDays as $day) {
            $absenceBlocks = $day->blocks->filter(
                fn ($b) => in_array($b->classification->value, ['ausencia', 'atestado'])
            );

            if ($absenceBlocks->isEmpty()) {
                continue;
            }

            $user = $day->user;
            if (! $user instanceof User) {
                continue;
            }

            $xml = $this->buildS2230Xml($user, $day, $absenceBlocks);

            $event = ESocialEvent::withoutGlobalScope('tenant')->create([
                'tenant_id' => $tenantId,
                'event_type' => 'S-2230',
                'related_type' => User::class,
                'related_id' => $day->user_id,
                'xml_content' => $xml,
                'status' => 'pending',
                'environment' => config('app.env') === 'production' ? 'producao' : 'homologacao',
                'version' => 'S-1.3',
                'retry_count' => 0,
                'max_retries' => 3,
                'batch_id' => "S2230-{$yearMonth}-".Str::random(8),
            ]);

            $events->push($event);
        }

        return $events;
    }

    /**
     * @param  Collection<int, JourneyEntry>  $days
     * @return JourneyMonthSummary
     */
    private function summarizeMonth(Collection $days): array
    {
        return [
            'total_worked_hours' => round($days->sum('total_minutes_worked') / 60, 2),
            'total_overtime_hours' => round($days->sum('total_minutes_overtime') / 60, 2),
            'total_night_hours' => round(
                $days->sum(fn ($d) => $d->blocks->where('classification', 'adicional_noturno')->sum('duration_minutes') ?? 0) / 60, 2
            ),
            'total_oncall_hours' => round($days->sum('total_minutes_oncall') / 60, 2),
            'total_travel_hours' => round($days->sum('total_minutes_travel') / 60, 2),
            'working_days' => $days->count(),
            'absence_days' => $days->filter(fn ($d) => $d->blocks->whereIn('classification', ['ausencia', 'atestado'])->isNotEmpty())->count(),
        ];
    }

    /**
     * @param  JourneyMonthSummary  $summary
     */
    private function buildS1200Xml(User $user, string $yearMonth, array $summary): string
    {
        $perApur = str_replace('-', '-', $yearMonth);
        $cpf = preg_replace('/\D/', '', $user->cpf ?? '00000000000');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtRemun/v_S_01_03_00">
  <evtRemun Id="ID1{$cpf}{$perApur}">
    <ideEvento>
      <indRetif>1</indRetif>
      <perApur>{$perApur}</perApur>
      <tpAmb>2</tpAmb>
      <procEmi>1</procEmi>
      <verProc>kalibrium-1.0</verProc>
    </ideEvento>
    <ideEmpregador>
      <tpInsc>1</tpInsc>
      <nrInsc></nrInsc>
    </ideEmpregador>
    <ideTrabalhador>
      <cpfTrab>{$cpf}</cpfTrab>
    </ideTrabalhador>
    <dmDev>
      <ideDmDev>D001</ideDmDev>
      <infoPerApur>
        <ideEstabLot>
          <detVerbas>
            <codRubr>HN</codRubr>
            <ideTabRubr>kalibrium</ideTabRubr>
            <qtdRubr>{$summary['total_worked_hours']}</qtdRubr>
            <vrRubr>0.00</vrRubr>
          </detVerbas>
          <detVerbas>
            <codRubr>HE50</codRubr>
            <ideTabRubr>kalibrium</ideTabRubr>
            <qtdRubr>{$summary['total_overtime_hours']}</qtdRubr>
            <vrRubr>0.00</vrRubr>
          </detVerbas>
          <detVerbas>
            <codRubr>AN</codRubr>
            <ideTabRubr>kalibrium</ideTabRubr>
            <qtdRubr>{$summary['total_night_hours']}</qtdRubr>
            <vrRubr>0.00</vrRubr>
          </detVerbas>
        </ideEstabLot>
      </infoPerApur>
    </dmDev>
  </evtRemun>
</eSocial>
XML;
    }

    /**
     * @param  Collection<int, JourneyBlock>  $absenceBlocks
     */
    private function buildS2230Xml(User $user, JourneyEntry $day, Collection $absenceBlocks): string
    {
        $cpf = preg_replace('/\D/', '', $user->cpf ?? '00000000000');
        $dtIni = $day->date->format('Y-m-d');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtAfastTemp/v_S_01_03_00">
  <evtAfastTemp Id="ID1{$cpf}{$dtIni}">
    <ideEvento>
      <indRetif>1</indRetif>
      <tpAmb>2</tpAmb>
      <procEmi>1</procEmi>
      <verProc>kalibrium-1.0</verProc>
    </ideEvento>
    <ideEmpregador>
      <tpInsc>1</tpInsc>
      <nrInsc></nrInsc>
    </ideEmpregador>
    <ideVinculo>
      <cpfTrab>{$cpf}</cpfTrab>
    </ideVinculo>
    <infoAfastamento>
      <iniAfastamento>
        <dtIniAfast>{$dtIni}</dtIniAfast>
        <codMotAfast>01</codMotAfast>
      </iniAfastamento>
    </infoAfastamento>
  </evtAfastTemp>
</eSocial>
XML;
    }
}
