<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; margin: 20px; margin-bottom: 50px; }
        h1 { margin: 0 0 8px; font-size: 18px; color: #111827; }
        .meta { margin-bottom: 16px; color: #4b5563; line-height: 1.6; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; }
        th { background: #f3f4f6; text-align: left; font-size: 11px; text-transform: uppercase; }
        td.num, th.num { text-align: right; }
        tr:nth-child(even) { background-color: #f9fafb; }
        tr { page-break-inside: avoid; }
        .footer { margin-top: 16px; font-size: 12px; color: #111827; padding-top: 8px; border-top: 2px solid #e5e7eb; page-break-inside: avoid; }
        .page-footer { position: fixed; bottom: 0; left: 0; right: 0; padding: 8px 20px; text-align: center; font-size: 8px; color: #94a3b8; background: #f8fafc; border-top: 1px solid #e2e8f0; }
        .status-pending { color: #d97706; }
        .status-approved { color: #2563eb; }
        .status-paid { color: #059669; }
        .status-cancelled, .status-rejected, .status-reversed { color: #dc2626; }
    </style>
</head>
<body>
    @php
        $settlementStatusValue = $settlementStatus instanceof \App\Enums\CommissionSettlementStatus
            ? $settlementStatus->value
            : $settlementStatus;
        $settlementStatusLabel = $settlementStatus instanceof \App\Enums\CommissionSettlementStatus
            ? $settlementStatus->label()
            : ($settlementStatusValue ?: 'nao fechado');
        $statusLabels = [
            'pending' => 'Pendente',
            'approved' => 'Aprovado',
            'paid' => 'Pago',
            'cancelled' => 'Cancelado',
            'rejected' => 'Rejeitado',
            'reversed' => 'Estornado',
        ];
        $calcLabels = \App\Models\CommissionRule::CALCULATION_TYPES;
    @endphp

    <h1>Extrato de Comissoes</h1>
    <div class="meta">
        <div><strong>Usuario:</strong> {{ $userName }}</div>
        <div><strong>Periodo:</strong> {{ $period }}</div>
        <div><strong>Gerado em:</strong> {{ $generatedAt->format('d/m/Y H:i') }}</div>
        <div><strong>Status fechamento:</strong> {{ $settlementStatusLabel }}</div>
        @if($paidAt)
            <div><strong>Pago em:</strong> {{ \Illuminate\Support\Carbon::parse($paidAt)->format('d/m/Y') }}</div>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>OS</th>
                <th>Regra</th>
                <th>Tipo</th>
                <th class="num">Base</th>
                <th class="num">Comissao</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($events as $event)
                @php
                    $eventStatusValue = $event->status instanceof \App\Enums\CommissionEventStatus
                        ? $event->status->value
                        : $event->status;
                    $eventStatusLabel = $event->status instanceof \App\Enums\CommissionEventStatus
                        ? $event->status->label()
                        : ($statusLabels[$eventStatusValue] ?? ucfirst((string) $eventStatusValue));
                @endphp
                <tr>
                    <td>{{ optional($event->created_at)->format('d/m/Y') }}</td>
                    <td>{{ $event->workOrder?->os_number ?? $event->workOrder?->number ?? '-' }}</td>
                    <td>{{ $event->rule?->name ?? '-' }}</td>
                    <td>{{ $calcLabels[$event->rule?->calculation_type] ?? $event->rule?->calculation_type ?? '-' }}</td>
                    <td class="num">R$ {{ number_format((float) $event->base_amount, 2, ',', '.') }}</td>
                    <td class="num">R$ {{ number_format((float) $event->commission_amount, 2, ',', '.') }}</td>
                    <td class="status-{{ $eventStatusValue }}">{{ $eventStatusLabel }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <strong>Quantidade de eventos:</strong> {{ $eventsCount }}<br>
        <strong>Total:</strong> R$ {{ number_format((float) $totalAmount, 2, ',', '.') }}
    </div>

    <div class="page-footer">
        Extrato de Comissoes - {{ $userName }} - Gerado em {{ $generatedAt->format('d/m/Y H:i') }}
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $text = "Pagina {PAGE_NUM} de {PAGE_COUNT}";
            $font = $fontMetrics->getFont("Helvetica");
            $size = 8;
            $width = $fontMetrics->getTextWidth($text, $font, $size);
            $x = ($pdf->get_width() - $width) / 2;
            $y = $pdf->get_height() - 15;
            $pdf->page_text($x, $y, $text, $font, $size, [0.58, 0.64, 0.70]);
        }
    </script>
</body>
</html>
