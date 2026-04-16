<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; margin: 20px; }
        h1 { margin: 0 0 8px; font-size: 18px; color: #111827; }
        .meta { margin-bottom: 16px; color: #4b5563; line-height: 1.6; }
        .summary { display: table; width: 100%; margin: 12px 0 16px; border-collapse: collapse; }
        .summary-item { display: table-cell; border: 1px solid #d1d5db; padding: 8px; text-align: center; }
        .summary-value { display: block; font-size: 18px; font-weight: bold; color: #111827; }
        .summary-label { display: block; font-size: 10px; color: #4b5563; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; font-size: 11px; text-transform: uppercase; }
        td.num, th.num { text-align: right; }
        tr:nth-child(even) { background-color: #f9fafb; }
        tr { page-break-inside: avoid; }
        .footer { margin-top: 16px; font-size: 10px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    <h1>Relatorio de oportunidades INMETRO</h1>

    <div class="meta">
        <div><strong>Empresa:</strong> {{ $tenant->name ?? 'Empresa' }}</div>
        <div><strong>Gerado em:</strong> {{ $generated_at }}</div>
    </div>

    <div class="summary">
        <div class="summary-item">
            <span class="summary-value">{{ $total_leads }}</span>
            <span class="summary-label">Leads</span>
        </div>
        <div class="summary-item">
            <span class="summary-value">{{ $critical_count }}</span>
            <span class="summary-label">Criticos</span>
        </div>
        <div class="summary-item">
            <span class="summary-value">{{ $urgent_count }}</span>
            <span class="summary-label">Urgentes</span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Nome</th>
                <th>Documento</th>
                <th>Prioridade</th>
                <th class="num">Instrumentos</th>
                <th>Cidades</th>
                <th>Status</th>
                <th>Receita estimada</th>
            </tr>
        </thead>
        <tbody>
            @forelse($leads as $lead)
                <tr>
                    <td>{{ $lead['name'] }}</td>
                    <td>{{ $lead['document'] }}</td>
                    <td>{{ $lead['priority'] }}</td>
                    <td class="num">{{ $lead['instruments'] }}</td>
                    <td>{{ $lead['cities'] }}</td>
                    <td>{{ $lead['lead_status'] }}</td>
                    <td>{{ $lead['estimated_revenue'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Nenhuma oportunidade encontrada.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        KALIBRIUM ERP - Relatorio gerado automaticamente
    </div>
</body>
</html>
