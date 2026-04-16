<?php

namespace App\Http\Controllers\Api\V1\Financial;

use App\Enums\FinancialStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\FinancialExportRequest;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class FinancialExportController extends Controller
{
    use ResolvesCurrentTenant;

    private function hasPermissionForType(Request $request, string $type): bool
    {
        $permission = $type === 'receivable'
            ? 'finance.receivable.view'
            : 'finance.payable.view';

        $user = $request->user();
        if (! $user) {
            return false;
        }

        return $user->getEffectivePermissions()->contains('name', $permission);
    }

    private function osNumberFilter(Request $request): ?string
    {
        $osNumber = trim((string) $request->get('os_number', ''));

        return $osNumber !== '' ? $osNumber : null;
    }

    private function applyReceivableOsFilter($query, ?string $osNumber): void
    {
        if (! $osNumber) {
            return;
        }

        $query->whereHas('workOrder', function ($wo) use ($osNumber) {
            $wo->where(function ($q) use ($osNumber) {
                $q->where('os_number', 'like', "%{$osNumber}%")
                    ->orWhere('number', 'like', "%{$osNumber}%");
            });
        });
    }

    private function applyPayableIdentifierFilter($query, ?string $osNumber): void
    {
        if (! $osNumber) {
            return;
        }

        $query->where(function ($q) use ($osNumber) {
            $q->where('description', 'like', "%{$osNumber}%")
                ->orWhere('notes', 'like', "%{$osNumber}%");
        });
    }

    /**
     * OFX Export.
     * GET /financial/export/ofx?type=receivable|payable&from=2024-01-01&to=2024-01-31
     */
    public function ofx(FinancialExportRequest $request): Response
    {
        try {
            $validated = $request->validated();
            if (! $this->hasPermissionForType($request, $validated['type'])) {
                return response('Voce nao possui permissao para exportar este tipo de financeiro.', 403);
            }

            $isReceivable = $validated['type'] === 'receivable';
            $model = $isReceivable ? AccountReceivable::class : AccountPayable::class;
            $tenantId = $this->tenantId();

            $query = $model::query()
                ->where('tenant_id', $tenantId)
                ->whereBetween('due_date', [$validated['from'], $validated['to']])
                ->orderBy('due_date');

            $osNumber = $this->osNumberFilter($request);
            if ($isReceivable) {
                $this->applyReceivableOsFilter($query, $osNumber);
                $query->with('customer:id,name');
            } else {
                $this->applyPayableIdentifierFilter($query, $osNumber);
                $query->with('supplierRelation:id,name');
            }

            $records = $query->get();

            $dtStart = str_replace('-', '', $validated['from']).'000000';
            $dtEnd = str_replace('-', '', $validated['to']).'235959';
            $acctId = $isReceivable ? '0001-RECEIVABLE' : '0002-PAYABLE';
            $acctType = $isReceivable ? 'SAVINGS' : 'CHECKING';

            $transactions = '';
            foreach ($records as $record) {
                $dt = $record->due_date->format('Ymd').'120000';
                $amount = $isReceivable ? $record->amount : -$record->amount;
                $fitId = strtoupper(md5($record->id.$record->due_date));
                $name = $isReceivable
                    ? ($record->customer?->name ?? ($record->description ?? 'N/A'))
                    : ($record->supplierRelation?->name ?? $record->supplier ?? ($record->description ?? 'N/A'));
                $memo = $record->description ?? '';

                $transactions .= '
<STMTTRN>
<TRNTYPE>'.($amount >= 0 ? 'CREDIT' : 'DEBIT')."
<DTPOSTED>{$dt}
<TRNAMT>{$amount}
<FITID>{$fitId}
<NAME>".mb_substr($name, 0, 32)."
<MEMO>{$memo}
</STMTTRN>";
            }

            $ofx = 'OFXHEADER:100
DATA:OFXSGML
VERSION:102
SECURITY:NONE
ENCODING:UTF-8
CHARSET:UTF-8
COMPRESSION:NONE
OLDFILEUID:NONE
NEWFILEUID:NONE

<OFX>
<SIGNONMSGSRSV1>
<SONRS>
<STATUS><CODE>0<SEVERITY>INFO</STATUS>
<DTSERVER>'.now()->format('YmdHis')."
<LANGUAGE>POR
</SONRS>
</SIGNONMSGSRSV1>
<BANKMSGSRSV1>
<STMTTRNRS>
<TRNUID>1
<STATUS><CODE>0<SEVERITY>INFO</STATUS>
<STMTRS>
<CURDEF>BRL
<BANKACCTFROM>
<BANKID>0000
<ACCTID>{$acctId}
<ACCTTYPE>{$acctType}
</BANKACCTFROM>
<BANKTRANLIST>
<DTSTART>{$dtStart}
<DTEND>{$dtEnd}
{$transactions}
</BANKTRANLIST>
<LEDGERBAL>
<BALAMT>0.00
<DTASOF>".now()->format('YmdHis').'
</LEDGERBAL>
</STMTRS>
</STMTTRNRS>
</BANKMSGSRSV1>
</OFX>';

            return response($ofx, 200, [
                'Content-Type' => 'application/x-ofx',
                'Content-Disposition' => "attachment; filename=\"export_{$validated['type']}_{$validated['from']}_{$validated['to']}.ofx\"",
            ]);
        } catch (\Throwable $e) {
            Log::error('OFX export failed', ['error' => $e->getMessage()]);

            return response('Erro ao gerar exportacao OFX', 500);
        }
    }

    /**
     * CSV Export using fputcsv for proper escaping.
     * GET /financial/export/csv?type=receivable|payable&from=...&to=...
     */
    public function csv(FinancialExportRequest $request): Response
    {
        try {
            $validated = $request->validated();
            if (! $this->hasPermissionForType($request, $validated['type'])) {
                return response('Voce nao possui permissao para exportar este tipo de financeiro.', 403);
            }

            $isReceivable = $validated['type'] === 'receivable';
            $model = $isReceivable ? AccountReceivable::class : AccountPayable::class;
            $tenantId = $this->tenantId();

            $query = $model::query()
                ->where('tenant_id', $tenantId)
                ->whereBetween('due_date', [$validated['from'], $validated['to']])
                ->orderBy('due_date');

            $osNumber = $this->osNumberFilter($request);
            if ($isReceivable) {
                $this->applyReceivableOsFilter($query, $osNumber);
                $query->with('customer:id,name');
            } else {
                $this->applyPayableIdentifierFilter($query, $osNumber);
                $query->with('supplierRelation:id,name');
            }

            $records = $query->get();

            $statusLabels = collect(FinancialStatus::cases())
                ->mapWithKeys(fn (FinancialStatus $status) => [$status->value => $status->label()])
                ->all();

            $handle = fopen('php://temp', 'r+');
            fputcsv($handle, ['Data Vencimento', 'Descricao', 'Cliente/Fornecedor', 'Valor', 'Status', 'Valor Pago'], ';');

            foreach ($records as $record) {
                $customer = $isReceivable
                    ? ($record->customer?->name ?? '')
                    : ($record->supplierRelation?->name ?? $record->supplier ?? '');

                fputcsv($handle, [
                    $record->due_date->format('d/m/Y'),
                    $record->description ?? '',
                    $customer,
                    number_format((float) $record->amount, 2, ',', '.'),
                    $statusLabels[($record->status instanceof FinancialStatus ? $record->status->value : ($record->status ?? ''))] ?? ($record->status ?? ''),
                    number_format((float) ($record->amount_paid ?? 0), 2, ',', '.'),
                ], ';');
            }

            rewind($handle);
            $csv = stream_get_contents($handle);
            fclose($handle);

            $csv = "\xEF\xBB\xBF".$csv;

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"export_{$validated['type']}_{$validated['from']}_{$validated['to']}.csv\"",
            ]);
        } catch (\Throwable $e) {
            Log::error('CSV export failed', ['error' => $e->getMessage()]);

            return response('Erro ao gerar exportacao CSV', 500);
        }
    }
}
