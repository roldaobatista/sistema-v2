<?php

namespace App\Services;

use App\Models\JourneyEntry;
use App\Models\Tenant;
use Carbon\Carbon;

class ACJEFExportService
{
    /**
     * Exporta Arquivo Central de Jornada Eletrônica Fiscal (ACJEF)
     * Formato Portaria MTP 671/2021 - Anexo X
     */
    public function export(int $tenantId, Carbon $startDate, Carbon $endDate): string
    {
        $tenant = Tenant::findOrFail($tenantId);
        $lines = [];

        // 1. Cabeçalho (Tipo 1)
        $lines[] = $this->generateHeader($tenant, $startDate, $endDate);

        // 2. Empresa (Tipo 2)
        $lines[] = $this->generateCompany($tenant);

        // 3. Registros de Jornada Diária (Tipo 3)
        $journeys = JourneyEntry::with('user')
            ->where('tenant_id', $tenantId)
            ->whereBetween('reference_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        $registerCount = 3;

        foreach ($journeys as $journey) {
            $lines[] = $this->generateJourneyRecord($journey);
            $registerCount++;
        }

        // 4. Trailer (Tipo 9)
        $lines[] = $this->generateTrailer($registerCount);

        return implode("\r\n", $lines)."\r\n";
    }

    private function generateHeader(Tenant $tenant, Carbon $startDate, Carbon $endDate): string
    {
        $doc = preg_replace('/[^0-9]/', '', $tenant->document);
        $cnpj = str_pad($doc, 14, '0', STR_PAD_LEFT);
        $cei = str_pad('', 14, ' ');
        $razao = str_pad(substr($tenant->name, 0, 150), 150, ' ');
        $dtIn = $startDate->format('dmY');
        $dtFi = $endDate->format('dmY');
        $dtGe = now()->format('dmY');
        $hrGe = now()->format('Hi');
        $versao = str_pad('00000000001', 11, '0', STR_PAD_LEFT);

        return "1{$cnpj}{$cei}{$razao}{$dtIn}{$dtFi}{$dtGe}{$hrGe}{$versao}";
    }

    private function generateCompany(Tenant $tenant): string
    {
        $doc = preg_replace('/[^0-9]/', '', $tenant->document);
        $cnpj = str_pad($doc, 14, '0', STR_PAD_LEFT);
        $cei = str_pad('', 14, ' ');
        $razao = str_pad(substr($tenant->name, 0, 150), 150, ' ');
        $local = str_pad(substr($tenant->address_city ?? 'Sede', 0, 100), 100, ' ');

        return "2{$cnpj}{$cei}{$razao}{$local}";
    }

    private function generateJourneyRecord(JourneyEntry $journey): string
    {
        $cpf = str_pad(preg_replace('/[^0-9]/', '', $journey->user->document ?? ''), 11, '0', STR_PAD_LEFT);
        $data = Carbon::parse($journey->reference_date)->format('dmY');

        $ent1 = $journey->first_in ? Carbon::parse($journey->first_in)->format('Hi') : '    ';
        $sai1 = $journey->first_out ? Carbon::parse($journey->first_out)->format('Hi') : '    ';
        $ent2 = $journey->second_in ? Carbon::parse($journey->second_in)->format('Hi') : '    ';
        $sai2 = $journey->second_out ? Carbon::parse($journey->second_out)->format('Hi') : '    ';

        $ht = $this->formatHours($journey->worked_minutes ?? 0);
        $hn = $this->formatHours($journey->night_minutes ?? 0);

        $he1 = $this->formatHours($journey->overtime_50_minutes ?? 0);
        $phe1 = str_pad('0050', 4, '0', STR_PAD_LEFT); // 50%

        $he2 = $this->formatHours($journey->overtime_100_minutes ?? 0);
        $phe2 = str_pad('0100', 4, '0', STR_PAD_LEFT); // 100%

        $faltas = $this->formatHours($journey->missing_minutes ?? 0);

        $saldo = $journey->hour_bank_minutes ?? 0;
        $sinal = ($saldo >= 0) ? '+' : '-';
        $bh = $this->formatHours(abs($saldo));

        return "3{$cpf}{$data}{$ent1}{$sai1}{$ent2}{$sai2}{$ht}{$hn}{$he1}{$phe1}{$he2}{$phe2}{$faltas}{$sinal}{$bh}";
    }

    private function generateTrailer(int $count): string
    {
        $qte = str_pad((string) $count, 9, '0', STR_PAD_LEFT);

        return "9{$qte}";
    }

    private function formatHours(int $minutes): string
    {
        $h = floor($minutes / 60);
        $m = $minutes % 60;

        return str_pad($h, 2, '0', STR_PAD_LEFT).str_pad($m, 2, '0', STR_PAD_LEFT);
    }
}
