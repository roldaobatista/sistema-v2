<?php

namespace App\Services\Fiscal;

use App\Models\FiscalNote;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Reports: SPED (#1), tax dashboard (#2), accountant export (#3),
 * ledger (#4), tax forecast (#5).
 */
class FiscalReportService
{
    /**
     * #1 — Generate SPED Fiscal data (simplified structure).
     */
    public function generateSpedFiscal(Tenant $tenant, Carbon $inicio, Carbon $fim): array
    {
        $notes = FiscalNote::forTenant($tenant->id)
            ->authorized()
            ->whereBetween('issued_at', [$inicio, $fim])
            ->orderBy('issued_at')
            ->get();

        $registers = [];

        // Register 0000 - Opening
        $registers[] = $this->spedRegister('0000', [
            'REG' => '0000', 'COD_VER' => '016', 'COD_FIN' => '0',
            'DT_INI' => $inicio->format('dmY'), 'DT_FIN' => $fim->format('dmY'),
            'NOME' => $tenant->name, 'CNPJ' => preg_replace('/\D/', '', $tenant->document ?? ''),
            'UF' => $tenant->address_state ?? $tenant->state ?? 'MT', 'IE' => $tenant->state_registration ?? '',
        ]);

        // Register C100 - NF-e
        $nfeNotes = $notes->where('type', 'nfe');
        foreach ($nfeNotes as $note) {
            $registers[] = $this->spedRegister('C100', [
                'IND_OPER' => '1', 'IND_EMIT' => '0', 'COD_PART' => $note->customer_id,
                'COD_MOD' => '55', 'SER' => $note->series, 'NUM_DOC' => $note->number,
                'CHV_NFE' => $note->access_key, 'DT_DOC' => $note->issued_at?->format('dmY'),
                'VL_DOC' => number_format((float) ($note->total_amount ?? 0), 2, ',', ''),
            ]);
        }

        return [
            'registers' => $registers,
            'total_notes' => $notes->count(),
            'total_nfe' => $nfeNotes->count(),
            'total_nfse' => $notes->where('type', 'nfse')->count(),
            'period' => $inicio->format('m/Y'),
        ];
    }

    /**
     * #2 — Tax dashboard data (ICMS, ISS, PIS, COFINS by period).
     */
    public function taxDashboard(Tenant $tenant, string $periodo = 'month'): array
    {
        $notes = FiscalNote::forTenant($tenant->id)
            ->authorized()
            ->when($periodo === 'month', fn ($q) => $q->whereMonth('issued_at', now()->month)->whereYear('issued_at', now()->year))
            ->when($periodo === 'quarter', fn ($q) => $q->where('issued_at', '>=', now()->startOfQuarter()))
            ->when($periodo === 'year', fn ($q) => $q->whereYear('issued_at', now()->year))
            ->get();

        $totalNFe = bcadd((string) $notes->where('type', 'nfe')->sum('total_amount'), '0', 2);
        $totalNFSe = bcadd((string) $notes->where('type', 'nfse')->sum('total_amount'), '0', 2);
        $totalFaturamento = bcadd($totalNFe, $totalNFSe, 2);

        // Estimated taxes based on Simples Nacional (typical rates)
        $regime = $tenant->fiscal_regime ?? 'simples_nacional';
        $rates = $this->getTaxRates($regime, $totalFaturamento);

        $totalImpostos = '0';
        $impostos = [];
        foreach ($rates as $key => $rate) {
            $base = in_array($key, ['icms']) ? $totalNFe : (in_array($key, ['iss']) ? $totalNFSe : $totalFaturamento);
            $valor = bcmul($base, (string) $rate, 2);
            $impostos[$key] = $valor;
            $totalImpostos = bcadd($totalImpostos, $valor, 2);
        }

        return [
            'periodo' => $periodo,
            'total_faturamento' => $totalFaturamento,
            'total_nfe' => $totalNFe,
            'total_nfse' => $totalNFSe,
            'impostos_estimados' => $impostos,
            'total_impostos' => $totalImpostos,
            'notas_emitidas' => $notes->count(),
            'by_type' => $notes->groupBy('type')->map(fn ($g) => ['count' => $g->count(), 'total' => $g->sum('total_amount')]),
        ];
    }

    /**
     * #3 — Export XML/PDF files as ZIP for accountant.
     */
    public function exportForAccountant(Tenant $tenant, Carbon $mes): array
    {
        $notes = FiscalNote::forTenant($tenant->id)
            ->authorized()
            ->whereMonth('issued_at', $mes->month)
            ->whereYear('issued_at', $mes->year)
            ->get();

        if ($notes->isEmpty()) {
            return ['success' => false, 'error' => 'Nenhuma nota encontrada no período'];
        }

        $zipName = "fiscal_{$tenant->id}_{$mes->format('Y_m')}.zip";
        $zipPath = "exports/{$zipName}";
        $fullPath = storage_path("app/{$zipPath}");

        if (! is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($fullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return ['success' => false, 'error' => 'Erro ao criar arquivo ZIP'];
        }

        $fileCount = 0;
        foreach ($notes as $note) {
            $folder = strtoupper($note->type)."/{$note->number}";
            if ($note->pdf_path && Storage::exists($note->pdf_path)) {
                $zip->addFromString("{$folder}/DANFE_{$note->number}.pdf", Storage::get($note->pdf_path));
                $fileCount++;
            }
            if ($note->xml_path && Storage::exists($note->xml_path)) {
                $zip->addFromString("{$folder}/XML_{$note->number}.xml", Storage::get($note->xml_path));
                $fileCount++;
            }
        }

        // Summary CSV
        $csv = "Tipo,Número,Série,Chave,Valor,Data\n";
        foreach ($notes as $note) {
            $csv .= "{$note->type},{$note->number},{$note->series},{$note->access_key},{$note->total_amount},{$note->issued_at?->format('d/m/Y')}\n";
        }
        $zip->addFromString('resumo.csv', $csv);
        $zip->close();

        return [
            'success' => true,
            'path' => $zipPath,
            'full_path' => $fullPath,
            'file_name' => $zipName,
            'notes_count' => $notes->count(),
            'files_count' => $fileCount,
        ];
    }

    /**
     * #4 — Ledger report (livro de entrada/saída).
     */
    public function ledgerReport(Tenant $tenant, Carbon $inicio, Carbon $fim): array
    {
        $notes = FiscalNote::forTenant($tenant->id)
            ->authorized()
            ->whereBetween('issued_at', [$inicio, $fim])
            ->orderBy('issued_at')
            ->get();

        $saidas = $notes->map(fn ($n) => [
            'data' => $n->issued_at?->format('d/m/Y'),
            'tipo' => strtoupper($n->type),
            'numero' => $n->number,
            'serie' => $n->series,
            'cliente' => $n->customer?->name,
            'valor' => $n->total_amount,
            'cfop' => $n->cfop,
            'natureza' => $n->nature_of_operation,
        ]);

        return [
            'periodo' => $inicio->format('d/m/Y').' - '.$fim->format('d/m/Y'),
            'total_saidas' => $notes->sum('total_amount'),
            'quantidade' => $notes->count(),
            'registros' => $saidas->values()->all(),
            'por_cfop' => $notes->groupBy('cfop')->map(fn ($g) => [
                'count' => $g->count(),
                'total' => $g->sum('total_amount'),
            ]),
        ];
    }

    /**
     * #5 — Tax forecast based on historical data.
     */
    public function taxForecast(Tenant $tenant): array
    {
        // Collect last 6 months of data
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $total = FiscalNote::forTenant($tenant->id)
                ->authorized()
                ->whereMonth('issued_at', $date->month)
                ->whereYear('issued_at', $date->year)
                ->sum('total_amount');

            $months[] = [
                'month' => $date->format('m/Y'),
                'total' => (float) $total,
            ];
        }

        // Simple moving average
        $values = array_column($months, 'total');
        $avg = count($values) > 0 ? array_sum($values) / count($values) : 0;

        // Trend (linear regression slope)
        $n = count($values);
        $trend = 0;
        if ($n > 1) {
            $sumX = ($n * ($n - 1)) / 2;
            $sumX2 = ($n * ($n - 1) * (2 * $n - 1)) / 6;
            $sumY = array_sum($values);
            $sumXY = 0;
            foreach ($values as $i => $v) {
                $sumXY += $i * $v;
            }
            $denominator = $n * $sumX2 - $sumX * $sumX;
            if ($denominator == 0) {
                $slope = 0;
            } else {
                $slope = ($n * $sumXY - $sumX * $sumY) / $denominator;
            }
            $trend = $slope;
        }

        $forecast = (string) max(0, $avg + $trend);
        $regime = $tenant->fiscal_regime ?? 'simples_nacional';
        $rates = $this->getTaxRates($regime, $forecast);

        $totalImpostosPrev = '0';
        $detalhamento = [];
        foreach ($rates as $key => $rate) {
            $val = bcmul($forecast, (string) $rate, 2);
            $detalhamento[$key] = $val;
            $totalImpostosPrev = bcadd($totalImpostosPrev, $val, 2);
        }

        return [
            'historico' => $months,
            'media_mensal' => bcadd((string) $avg, '0', 2),
            'tendencia' => bcadd((string) $trend, '0', 2),
            'previsao_proximo_mes' => bcadd($forecast, '0', 2),
            'impostos_previstos' => $totalImpostosPrev,
            'detalhamento' => $detalhamento,
        ];
    }

    private function getTaxRates(string $regime, float|string $faturamento): array
    {
        return match ($regime) {
            'simples_nacional' => [
                'icms' => 0.034, 'iss' => 0.02, 'pis' => 0.0, 'cofins' => 0.0,
                'irpj' => 0.0, 'csll' => 0.0,
            ],
            'lucro_presumido' => [
                'icms' => 0.07, 'iss' => 0.05, 'pis' => 0.0065, 'cofins' => 0.03,
                'irpj' => 0.048, 'csll' => 0.0288,
            ],
            'lucro_real' => [
                'icms' => 0.12, 'iss' => 0.05, 'pis' => 0.0165, 'cofins' => 0.076,
                'irpj' => 0.15, 'csll' => 0.09,
            ],
            default => [
                'icms' => 0.034, 'iss' => 0.02, 'pis' => 0.0, 'cofins' => 0.0,
                'irpj' => 0.0, 'csll' => 0.0,
            ],
        };
    }

    private function spedRegister(string $register, array $fields): string
    {
        return '|'.implode('|', $fields).'|';
    }
}
