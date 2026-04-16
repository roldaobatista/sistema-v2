<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>@yield('title')</title>
    <style>
        @page {
            margin: 0px;
        }

        /* ─── Reset & Base ──────────────────────── */
        div, span, p, h1, h2, h3, h4, h5, h6, ul, ol, li, table, tr, th, td, img {
            margin: 0;
            padding: 0;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', 'Arial', 'Helvetica', sans-serif;
            font-size: 9px;
            line-height: 1.5;
            color: #1e293b;
            background: #fff;
            margin: 80px 50px 80px 50px;
        }

        /* ─── Layout ────────────────────────────── */
        .page { padding: 0; }
        .page-break { page-break-after: always; }

        /* ─── Page Break Control ────────────────── */
        .no-break { page-break-inside: avoid; }
        .equipment-block { page-break-inside: auto; }
        .equipment-header { page-break-after: avoid; }
        .data-table { page-break-inside: auto; }
        .data-table thead { display: table-header-group; }
        .data-table tr { page-break-inside: avoid; }
        .approval-section { page-break-inside: avoid; }
        .conditions-group { page-break-inside: auto; }

        /* ─── Header ────────────────────────────── */
        .header {
            display: table;
            width: 100%;
            table-layout: fixed;
            padding-bottom: 12px;
            margin-bottom: 16px;
            border-bottom: 2px solid #1e40af;
        }
        .header-logo {
            display: table-cell;
            width: 60%;
            vertical-align: middle;
            padding-right: 16px;
        }
        .header-logo .company-logo-img {
            display: block;
            max-height: 50px;
            max-width: 200px;
            margin-bottom: 4px;
        }
        .header-logo .company-name {
            font-size: 16px;
            font-weight: 700;
            color: #1e40af;
            line-height: 1.2;
            letter-spacing: -0.3px;
            word-break: break-word;
        }
        .header-logo .company-tagline {
            font-size: 8px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-top: 3px;
        }
        .header-info {
            display: table-cell;
            width: 40%;
            text-align: right;
            vertical-align: middle;
        }
        .header-info p {
            font-size: 8px;
            color: #475569;
            line-height: 1.7;
            word-break: break-word;
        }
        .header-info strong {
            color: #334155;
            font-weight: 700;
        }

        /* ─── Document Title Badge ──────────────── */
        .doc-badge {
            display: table;
            width: 100%;
            table-layout: fixed;
            margin-bottom: 14px;
            background: #f0f4ff;
            border: 1px solid #bfdbfe;
            border-radius: 6px;
            padding: 10px 14px;
        }
        .doc-badge-left {
            display: table-cell;
            vertical-align: middle;
        }
        .doc-badge-left .doc-type {
            font-size: 7px;
            font-weight: 700;
            color: #fff;
            background: #1e40af;
            padding: 2px 10px;
            border-radius: 3px;
            text-transform: uppercase;
            letter-spacing: 2px;
            display: inline-block;
        }
        .doc-badge-left .doc-number {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 2px;
            letter-spacing: -0.5px;
        }
        .doc-badge-left .doc-revision {
            font-size: 8px;
            color: #64748b;
            font-weight: 600;
            margin-left: 6px;
        }
        .doc-badge-right {
            display: table-cell;
            text-align: right;
            vertical-align: middle;
        }
        .doc-badge-right .doc-date {
            font-size: 9px;
            color: #475569;
            line-height: 1.8;
        }
        .doc-badge-right .doc-date strong {
            color: #1e293b;
        }

        /* ─── Executive Summary ────────────────── */
        .exec-summary {
            display: table;
            width: 100%;
            table-layout: fixed;
            margin-bottom: 16px;
            border: 2px solid #1e40af;
            border-radius: 6px;
            overflow: hidden;
        }
        .exec-summary-item {
            display: table-cell;
            text-align: center;
            vertical-align: middle;
            padding: 10px 8px;
            border-right: 1px solid #bfdbfe;
        }
        .exec-summary-item:last-child {
            border-right: none;
        }
        .exec-summary-item.highlight {
            background: #1e40af;
        }
        .exec-summary-label {
            font-size: 7px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #64748b;
            margin-bottom: 2px;
        }
        .exec-summary-item.highlight .exec-summary-label {
            color: #bfdbfe;
        }
        .exec-summary-value {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
        }
        .exec-summary-item.highlight .exec-summary-value {
            color: #fff;
            font-size: 16px;
        }

        /* ─── Intro Box ────────────────────────── */
        .intro-box {
            background: #f8fafc;
            border-left: 3px solid #2563eb;
            padding: 10px 14px;
            margin-bottom: 16px;
        }
        .intro-text {
            font-size: 9px;
            color: #334155;
            line-height: 1.7;
        }

        /* ─── Info Grid ─────────────────────────── */
        .info-grid {
            display: table;
            width: 100%;
            table-layout: fixed;
            margin-bottom: 16px;
        }
        .info-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .info-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            padding: 10px 12px;
            margin-right: 6px;
        }
        .info-col:last-child .info-box { margin-right: 0; margin-left: 6px; }
        .info-box-title {
            font-size: 7px;
            font-weight: 700;
            color: #1e40af;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 6px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
        }
        .info-row {
            display: table;
            width: 100%;
            table-layout: fixed;
            margin-bottom: 2px;
        }
        .info-label {
            display: table-cell;
            width: 30%;
            font-size: 8px;
            color: #64748b;
            font-weight: 600;
            padding: 1px 6px 1px 0;
            vertical-align: top;
        }
        .info-value {
            display: table-cell;
            font-size: 9px;
            color: #1e293b;
            padding: 1px 0;
            vertical-align: top;
            word-break: break-word;
        }

        /* ─── Table ─────────────────────────────── */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
            table-layout: fixed;
        }
        .data-table thead th {
            background: #1e293b;
            color: #fff;
            font-size: 7px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 6px 8px;
            text-align: left;
            border: none;
        }
        .data-table thead th:first-child { border-radius: 0; }
        .data-table thead th:last-child { border-radius: 0; text-align: right; }
        .data-table tbody td {
            padding: 5px 8px;
            font-size: 9px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: top;
        }
        .data-table tbody tr:nth-child(even) { background: #f8fafc; }
        .data-table tbody td:last-child { text-align: right; }
        .data-table tfoot td {
            padding: 8px;
            font-size: 9px;
            font-weight: 700;
            border-top: 2px solid #1e40af;
        }

        /* ─── Equipment Block ──────────────────── */
        .equipment-header-bar {
            background: #1e293b;
            color: #fff;
            padding: 7px 12px;
            border-radius: 4px 4px 0 0;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        .equipment-header-bar .eq-label {
            font-size: 7px;
            font-weight: 400;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #94a3b8;
        }
        .equipment-subtotal-bar {
            background: #f0f4ff;
            border: 1px solid #bfdbfe;
            border-top: none;
            border-radius: 0 0 4px 4px;
            padding: 6px 12px;
            text-align: right;
            font-size: 9px;
            color: #1e40af;
            font-weight: 700;
        }

        /* ─── Photos ───────────────────────────── */
        .photos-grid {
            display: table;
            width: 100%;
            table-layout: fixed;
            margin-top: 6px;
        }
        .photo-cell {
            display: table-cell;
            width: 25%;
            padding: 3px;
            vertical-align: top;
        }
        .photo-cell img {
            width: 100%;
            max-height: 80px;
            height: auto;
            border-radius: 3px;
            border: 1px solid #e2e8f0;
        }

        /* ─── Totals ────────────────────────────── */
        .totals-wrapper {
            width: 100%;
            border-collapse: collapse;
            margin: 14px 0;
        }
        .totals-spacer {
            width: 58%;
        }
        .totals-cell {
            width: 42%;
            vertical-align: top;
        }
        .totals-box {
            page-break-inside: avoid;
            width: 100%;
            background: #f0f4ff;
            border: 1px solid #bfdbfe;
            border-radius: 6px;
            padding: 10px 14px;
        }
        .totals-row {
            display: table;
            width: 100%;
            margin-bottom: 3px;
            table-layout: fixed;
        }
        .totals-label {
            display: table-cell;
            font-size: 9px;
            color: #64748b;
            padding-right: 8px;
        }
        .totals-value {
            display: table-cell;
            text-align: right;
            font-size: 9px;
            color: #1e293b;
            font-weight: 600;
        }
        .totals-row.total-final {
            border-top: 2px solid #1e40af;
            padding-top: 6px;
            margin-top: 6px;
        }
        .totals-row.total-final .totals-label {
            font-size: 12px;
            font-weight: 700;
            color: #1e40af;
        }
        .totals-row.total-final .totals-value {
            font-size: 12px;
            font-weight: 700;
            color: #1e40af;
        }

        /* ─── Notes ─────────────────────────────── */
        .notes-section {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-left: 3px solid #f59e0b;
            border-radius: 4px;
            padding: 10px 14px;
            margin-bottom: 14px;
            page-break-inside: avoid;
        }
        .notes-title {
            font-size: 7px;
            font-weight: 700;
            color: #92400e;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 4px;
        }
        .notes-text {
            font-size: 9px;
            color: #78350f;
            line-height: 1.6;
        }

        /* ─── Section Cards ─────────────────────── */
        .section-card {
            page-break-inside: avoid;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            padding: 12px 14px;
            margin-top: 10px;
            background: #ffffff;
        }
        .section-title {
            font-size: 7px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #e2e8f0;
        }
        .section-title-blue {
            color: #1e40af;
            border-bottom-color: #bfdbfe;
        }
        .section-title-green {
            color: #16a34a;
            border-bottom-color: #bbf7d0;
        }
        .section-muted-text {
            font-size: 8px;
            color: #475569;
            line-height: 1.8;
        }

        /* ─── Payment Summary ──────────────────── */
        .payment-summary-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .payment-summary-grid td {
            padding: 4px 0;
            font-size: 9px;
            vertical-align: top;
            border-bottom: 1px solid #dcfce7;
        }
        .payment-summary-grid tr:last-child td {
            border-bottom: none;
        }
        .payment-summary-label {
            width: 28%;
            font-weight: 700;
            color: #166534;
        }
        .payment-summary-value {
            color: #334155;
        }
        .payment-schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        .payment-schedule-table th,
        .payment-schedule-table td {
            border: 1px solid #bbf7d0;
            padding: 5px 6px;
            font-size: 8px;
        }
        .payment-schedule-table th {
            background: #dcfce7;
            color: #166534;
            font-weight: 700;
            text-align: left;
        }

        /* ─── Signature ─────────────────────────── */
        .approval-wrapper {
            margin-top: 24px;
            border: 2px solid #1e40af;
            border-radius: 6px;
            page-break-inside: avoid;
        }
        .approval-title {
            background: #1e40af;
            color: #fff;
            text-align: center;
            padding: 6px;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .approval-body {
            padding: 14px 16px;
        }
        .approval-text {
            font-size: 8px;
            color: #64748b;
            text-align: center;
            margin-bottom: 10px;
        }
        .signatures {
            display: table;
            width: 100%;
            table-layout: fixed;
            margin-top: 30px;
        }
        .sig-col {
            display: table-cell;
            width: 45%;
            text-align: center;
            vertical-align: top;
        }
        .sig-col:first-child { padding-right: 10%; }
        .sig-line {
            border-top: 1px solid #334155;
            padding-top: 5px;
            margin-top: 30px;
        }
        .sig-name {
            font-size: 9px;
            font-weight: 700;
            color: #1e293b;
        }
        .sig-role {
            font-size: 7px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .sig-date {
            font-size: 8px;
            color: #64748b;
            margin-top: 4px;
        }

        /* ─── QR Code ──────────────────────────── */
        .qr-section {
            text-align: center;
            margin-top: 12px;
            padding: 10px;
            page-break-inside: avoid;
        }
        .qr-label {
            font-size: 7px;
            font-weight: 700;
            color: #1e40af;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 4px;
        }
        .qr-sublabel {
            font-size: 7px;
            color: #94a3b8;
            margin-top: 3px;
        }

        /* ─── Watermark ────────────────────────── */
        .watermark {
            position: fixed;
            top: 45%;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 75px;
            font-weight: 700;
            color: rgba(0, 0, 0, 0.05); /* Textura ténue para não esconder o print e o texto da proposta */
            z-index: -1;
            letter-spacing: 12px;
            text-transform: uppercase;
        }

        /* ─── Status Badges ─────────────────────── */
        .status-badge {
            font-size: 7px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 3px;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: inline-block;
        }
        .status-draft { background: #f1f5f9; color: #475569; }
        .status-pending_internal_approval { background: #fef3c7; color: #92400e; }
        .status-internally_approved { background: #ccfbf1; color: #0f766e; }
        .status-sent { background: #dbeafe; color: #1e40af; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .status-expired { background: #fef3c7; color: #92400e; }

        .status-in_execution { background: #e0e7ff; color: #3730a3; }
        .status-installation_testing { background: #ffedd5; color: #9a3412; }
        .status-renegotiation { background: #ffe4e6; color: #be123c; }
        .status-invoiced { background: #f3e8ff; color: #7c3aed; }

        /* ─── Footer ────────────────────────────── */
        .footer {
            position: fixed;
            bottom: 30px;
            left: 50px;
            right: 50px;
            padding: 8px 0 0;
            border-top: 1px solid #e2e8f0;
            font-size: 7px;
            color: #94a3b8;
        }
        .footer-inner {
            display: table;
            width: 100%;
            table-layout: fixed;
        }
        .footer-left {
            display: table-cell;
            text-align: left;
            vertical-align: middle;
            width: 60%;
        }
        .footer-right {
            display: table-cell;
            text-align: right;
            vertical-align: middle;
            width: 40%;
        }
        .footer strong { color: #475569; }
        .footer-contact {
            font-size: 7px;
            color: #94a3b8;
        }

        @yield('extra-styles')
    </style>
</head>
<body>
    {{-- Watermark for non-final statuses --}}
    @if(!empty($watermark_text))
        <div class="watermark">{{ $watermark_text }}</div>
    @endif

    <div class="page">
        {{-- Header --}}
        <div class="header">
            <div class="header-logo">
                @if(!empty($company_logo_path) && file_exists($company_logo_path))
                    <img class="company-logo-img" src="{{ $company_logo_path }}" alt="Logo">
                @endif
                <div class="company-name">{{ $tenant->name ?? 'Empresa' }}</div>
                @if(!empty($company_tagline))
                    <div class="company-tagline">{{ $company_tagline }}</div>
                @endif
            </div>
            <div class="header-info">
                <p>
                    @if($tenant->document ?? false)<strong>CNPJ:</strong> {{ $tenant->document }}<br>@endif
                    @if($tenant->phone ?? false)<strong>Tel:</strong> {{ $tenant->phone }}<br>@endif
                    @if($tenant->email ?? false){{ $tenant->email }}<br>@endif
                    @if($tenant->full_address ?? false){{ $tenant->full_address }}@endif
                </p>
            </div>
        </div>

        @yield('content')
    </div>

    {{-- Footer --}}
    <div class="footer">
        <div class="footer-inner">
            <div class="footer-left">
                <strong>{{ $tenant->name ?? 'Empresa' }}</strong>
                @if($tenant->phone ?? false) &middot; {{ $tenant->phone }}@endif
                @if($tenant->email ?? false) &middot; {{ $tenant->email }}@endif
                <br>Gerado em {{ now()->format('d/m/Y \à\s H:i') }}
            </div>
            <div class="footer-right">
                {{-- Page number handled by PHP script below --}}
            </div>
        </div>
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $text = "Página {PAGE_NUM} de {PAGE_COUNT}";
            $font = $fontMetrics->getFont("DejaVu Sans");
            $size = 7;
            $width = $fontMetrics->getTextWidth($text, $font, $size);
            $x = $pdf->get_width() - $width - 50;
            $y = $pdf->get_height() - 40;
            $pdf->page_text($x, $y, $text, $font, $size, [0.58, 0.64, 0.70]);
        }
    </script>
</body>
</html>
