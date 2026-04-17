<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Relatorio de conciliacao bancaria</title>
    <style>
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            color: #1f2937;
            margin: 28px;
        }

        h1 {
            font-size: 20px;
            margin: 0 0 6px;
            color: #111827;
        }

        h2 {
            font-size: 13px;
            margin: 18px 0 8px;
            color: #111827;
            text-transform: uppercase;
        }

        .muted {
            color: #6b7280;
        }

        .header {
            border-bottom: 2px solid #2563eb;
            margin-bottom: 16px;
            padding-bottom: 12px;
        }

        .meta-grid,
        .summary-grid {
            display: table;
            width: 100%;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 8px;
            margin-left: -8px;
        }

        .box {
            display: table-cell;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 8px 10px;
            vertical-align: top;
        }

        .label {
            color: #6b7280;
            font-size: 9px;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .value {
            font-size: 13px;
            font-weight: 700;
            color: #111827;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 6px 7px;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
            color: #374151;
            font-size: 9px;
            text-align: left;
            text-transform: uppercase;
        }

        tr {
            page-break-inside: avoid;
        }

        .num {
            text-align: right;
            white-space: nowrap;
        }

        .center {
            text-align: center;
        }

        .status {
            font-weight: 700;
        }

        .status-pending {
            color: #b45309;
        }

        .status-matched {
            color: #047857;
        }

        .status-ignored {
            color: #4b5563;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 28px;
            right: 28px;
            border-top: 1px solid #e5e7eb;
            padding-top: 6px;
            font-size: 8px;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $entriesCount = $entries->count();
        $matchedCount = $entries->where('status', 'matched')->count();
        $pendingCount = $entries->where('status', 'pending')->count();
        $ignoredCount = $entries->where('status', 'ignored')->count();
        $creditTotal = $entries->where('type', 'credit')->sum(fn ($entry) => (float) $entry->amount);
        $debitTotal = $entries->where('type', 'debit')->sum(fn ($entry) => (float) $entry->amount);
        $statusLabels = [
            'pending' => 'Pendente',
            'matched' => 'Conciliado',
            'ignored' => 'Ignorado',
        ];
        $typeLabels = [
            'credit' => 'Credito',
            'debit' => 'Debito',
        ];
    @endphp

    <div class="header">
        <h1>Relatorio de conciliacao bancaria</h1>
        <div class="muted">Gerado em {{ now()->format('d/m/Y H:i') }}</div>
    </div>

    <div class="meta-grid">
        <div class="box">
            <div class="label">Extrato</div>
            <div class="value">{{ $statement->filename }}</div>
            <div class="muted">Formato: {{ strtoupper((string) ($statement->format ?? '-')) }}</div>
        </div>
        <div class="box">
            <div class="label">Conta bancaria</div>
            <div class="value">{{ $statement->bankAccount?->name ?? '-' }}</div>
            <div class="muted">{{ $statement->bankAccount?->bank_name ?? '-' }}</div>
        </div>
        <div class="box">
            <div class="label">Importacao</div>
            <div class="value">{{ $statement->imported_at?->format('d/m/Y H:i') ?? '-' }}</div>
            <div class="muted">Por: {{ $statement->creator?->name ?? '-' }}</div>
        </div>
    </div>

    <h2>Resumo</h2>
    <div class="summary-grid">
        <div class="box">
            <div class="label">Lancamentos</div>
            <div class="value">{{ $entriesCount }}</div>
        </div>
        <div class="box">
            <div class="label">Conciliados</div>
            <div class="value">{{ $matchedCount }}</div>
        </div>
        <div class="box">
            <div class="label">Pendentes</div>
            <div class="value">{{ $pendingCount }}</div>
        </div>
        <div class="box">
            <div class="label">Ignorados</div>
            <div class="value">{{ $ignoredCount }}</div>
        </div>
    </div>

    <div class="summary-grid">
        <div class="box">
            <div class="label">Creditos</div>
            <div class="value">R$ {{ number_format($creditTotal, 2, ',', '.') }}</div>
        </div>
        <div class="box">
            <div class="label">Debitos</div>
            <div class="value">R$ {{ number_format($debitTotal, 2, ',', '.') }}</div>
        </div>
        <div class="box">
            <div class="label">Saldo liquido</div>
            <div class="value">R$ {{ number_format($creditTotal - $debitTotal, 2, ',', '.') }}</div>
        </div>
    </div>

    <h2>Lancamentos</h2>
    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Descricao</th>
                <th class="center">Tipo</th>
                <th class="num">Valor</th>
                <th class="center">Status</th>
                <th>Categoria</th>
            </tr>
        </thead>
        <tbody>
            @forelse($entries as $entry)
                <tr>
                    <td>{{ $entry->date?->format('d/m/Y') ?? '-' }}</td>
                    <td>{{ $entry->description }}</td>
                    <td class="center">{{ $typeLabels[$entry->type] ?? ucfirst((string) $entry->type) }}</td>
                    <td class="num">R$ {{ number_format((float) $entry->amount, 2, ',', '.') }}</td>
                    <td class="center status status-{{ $entry->status }}">
                        {{ $statusLabels[$entry->status] ?? ucfirst((string) $entry->status) }}
                    </td>
                    <td>{{ $entry->category ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="center muted">Nenhum lancamento encontrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Relatorio de conciliacao bancaria - {{ $statement->filename }}
    </div>
</body>
</html>
