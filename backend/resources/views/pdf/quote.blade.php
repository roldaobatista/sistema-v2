@extends('pdf.layout')

@section('title', "Orçamento {$quote->quote_number}")

@section('content')
    @php
        $paymentTermsEnum = \App\Enums\PaymentTerms::tryFrom($quote->payment_terms ?? '');
        $paymentTermsLabel = $paymentTermsEnum?->label() ?? ($quote->payment_terms ?: null);
        $rawStatus = $quote->status instanceof \App\Enums\QuoteStatus ? $quote->status->value : $quote->status;
        $paymentSummary = \App\Support\QuotePaymentSummary::fromQuote($quote);
        $equipmentCount = $quote->equipments->count();
        $totalItems = $quote->equipments->sum(fn($eq) => $eq->items->count());
        $validityDays = $quote->valid_until
            ? intval(max(0, $quote->created_at->diffInDays($quote->valid_until, false)))
            : null;
    @endphp

    {{-- ══════ Document Badge ══════ --}}
    <div class="doc-badge">
        <div class="doc-badge-left">
            <span class="doc-type">Proposta Comercial</span>
            <div class="doc-number">
                {{ $quote->quote_number }}
                @if(($quote->revision ?? 0) > 1)
                    <span class="doc-revision">Rev. {{ $quote->revision }}</span>
                @endif
            </div>
        </div>
        <div class="doc-badge-right">
            <div class="doc-date">
                <strong>Emissão:</strong> {{ $quote->created_at->format('d/m/Y') }}<br>
                <strong>Validade:</strong> {{ $quote->valid_until?->format('d/m/Y') ?? '-' }}<br>
                <span class="status-badge status-{{ $rawStatus }}">
                    {{ $quote->status instanceof \App\Enums\QuoteStatus ? $quote->status->label() : ($rawStatus ?? 'Desconhecido') }}
                </span>
            </div>
        </div>
    </div>

    {{-- ══════ Executive Summary ══════ --}}
    <div class="exec-summary">
        <div class="exec-summary-item">
            <div class="exec-summary-label">Equipamentos</div>
            <div class="exec-summary-value">{{ $equipmentCount }}</div>
        </div>
        <div class="exec-summary-item">
            <div class="exec-summary-label">Itens</div>
            <div class="exec-summary-value">{{ $totalItems }}</div>
        </div>
        <div class="exec-summary-item">
            <div class="exec-summary-label">Validade</div>
            <div class="exec-summary-value">{{ $validityDays !== null ? $validityDays . 'd' : '-' }}</div>
        </div>
        <div class="exec-summary-item highlight">
            <div class="exec-summary-label">Valor Total</div>
            <div class="exec-summary-value">R$ {{ number_format($quote->total ?? 0, 2, ',', '.') }}</div>
        </div>
    </div>

    {{-- ══════ Intro Text ══════ --}}
    <div class="intro-box">
        <p class="intro-text">
            Prezado(a) <strong>{{ $quote->customer->name ?? 'Cliente' }}</strong>,<br>
            Temos o prazer de apresentar nossa proposta comercial para os serviços e/ou produtos abaixo discriminados.
            Agradecemos a confiança depositada em nossa empresa e permanecemos à disposição para qualquer esclarecimento.
        </p>
    </div>

    {{-- ══════ Client & Seller Info ══════ --}}
    <div class="info-grid">
        <div class="info-col">
            <div class="info-box">
                <div class="info-box-title">Cliente</div>
                <div class="info-row"><span class="info-label">Razão Social</span><span class="info-value">{{ $quote->customer->name ?? '-' }}</span></div>
                <div class="info-row"><span class="info-label">CPF/CNPJ</span><span class="info-value">{{ $quote->customer->document ?? '-' }}</span></div>
                <div class="info-row"><span class="info-label">Telefone</span><span class="info-value">{{ $quote->customer->phone ?? '-' }}</span></div>
                <div class="info-row"><span class="info-label">E-mail</span><span class="info-value">{{ $quote->customer->email ?? '-' }}</span></div>
                @if($quote->customer->address_city ?? false)
                    <div class="info-row"><span class="info-label">Endereço</span><span class="info-value">
                        {{ collect([$quote->customer->address_street, $quote->customer->address_number, $quote->customer->address_neighborhood, $quote->customer->address_city, $quote->customer->address_state])->filter()->join(', ') }}
                    </span></div>
                @endif
            </div>
        </div>
        <div class="info-col">
            <div class="info-box">
                <div class="info-box-title">Vendedor</div>
                <div class="info-row"><span class="info-label">Nome</span><span class="info-value">{{ $quote->seller->name ?? '-' }}</span></div>
                @if($quote->seller->email ?? false)
                    <div class="info-row"><span class="info-label">E-mail</span><span class="info-value">{{ $quote->seller->email }}</span></div>
                @endif
                @if($quote->seller->phone ?? false)
                    <div class="info-row"><span class="info-label">Telefone</span><span class="info-value">{{ $quote->seller->phone }}</span></div>
                @endif
            </div>
        </div>
    </div>

    {{-- ══════ Equipment Blocks ══════ --}}
    @foreach($quote->equipments as $eqIndex => $quoteEquipment)
        @php
            $eqSubtotal = $quoteEquipment->items->sum('subtotal');
        @endphp
        <div class="equipment-block" style="margin-bottom: 14px; page-break-inside: avoid;">
            <div class="equipment-header-bar" style="page-break-after: avoid;">
                <span class="eq-label">Equipamento {{ $eqIndex + 1 }} de {{ $equipmentCount }}</span><br>
                @if($quoteEquipment->equipment)
                    {{ $quoteEquipment->equipment->brand ?? '' }} {{ $quoteEquipment->equipment->model ?? '' }}
                    @if($quoteEquipment->equipment->serial_number) &mdash; S/N: {{ $quoteEquipment->equipment->serial_number }} @endif
                @else
                    Equipamento não especificado
                @endif
            </div>
            <table class="data-table">
                <colgroup>
                    <col style="width: 5%;">
                    <col style="width: 45%;">
                    <col style="width: 10%;">
                    <col style="width: 10%;">
                    <col style="width: 15%;">
                    <col style="width: 15%;">
                </colgroup>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Descrição</th>
                        <th>Tipo</th>
                        <th style="text-align: center;">Qtd</th>
                        <th style="text-align: right;">Unitário</th>
                        <th style="text-align: right;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($quoteEquipment->items as $j => $item)
                        <tr>
                            <td>{{ $j + 1 }}</td>
                            <td><strong>{{ $item->description }}</strong></td>
                            <td>{{ $item->type === 'product' ? 'Peça' : 'Serviço' }}</td>
                            <td style="text-align: center">{{ number_format($item->quantity, 2, ',', '.') }}</td>
                            <td style="text-align: right">R$ {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                            <td style="text-align: right"><strong>R$ {{ number_format($item->subtotal, 2, ',', '.') }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if($quoteEquipment->items->count() > 0)
                <div class="equipment-subtotal-bar">
                    Subtotal Equipamento: R$ {{ number_format($eqSubtotal, 2, ',', '.') }}
                </div>
            @endif
        </div>

        {{-- Equipment Photos --}}
        @if(($quoteEquipment->photos ?? collect())->isNotEmpty())
            <div class="no-break" style="margin-bottom: 10px;">
                <div style="font-size: 7px; font-weight: 700; color: #1e40af; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 4px; padding-left: 2px;">
                    Registro Fotográfico
                </div>
                <div class="photos-grid">
                    @foreach($quoteEquipment->photos->take(4) as $photo)
                        <div class="photo-cell">
                            @php
                                $imgSrc = '';
                                if (!empty($photo->path)) {
                                    $localPath = storage_path('app/public/' . $photo->path);
                                    if (file_exists($localPath)) {
                                        $imgSrc = $localPath;
                                    }
                                }
                                // Fallback: se photo->url for um path local absoluto, tentar também
                                if (empty($imgSrc) && !empty($photo->url) && file_exists($photo->url)) {
                                    $imgSrc = $photo->url;
                                }
                            @endphp
                            <img src="{{ $imgSrc }}"
                                 alt="Foto do equipamento" />
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach

    {{-- ══════ Totals ══════ --}}
    <table class="totals-wrapper" role="presentation">
        <tr>
            <td class="totals-spacer"></td>
            <td class="totals-cell">
                <div class="totals-box">
                    <div class="totals-row">
                        <span class="totals-label">Subtotal</span>
                        <span class="totals-value">R$ {{ number_format($quote->subtotal ?? 0, 2, ',', '.') }}</span>
                    </div>
                    @if($quote->discount_percentage > 0)
                        <div class="totals-row">
                            <span class="totals-label">Desconto ({{ number_format($quote->discount_percentage, 1, ',', '.') }}%)</span>
                            <span class="totals-value" style="color: #dc2626">- R$ {{ number_format($quote->discount_amount ?? 0, 2, ',', '.') }}</span>
                        </div>
                    @elseif($quote->discount_amount > 0)
                        <div class="totals-row">
                            <span class="totals-label">Desconto</span>
                            <span class="totals-value" style="color: #dc2626">- R$ {{ number_format($quote->discount_amount, 2, ',', '.') }}</span>
                        </div>
                    @endif
                    @if(($quote->displacement_value ?? 0) > 0)
                        <div class="totals-row">
                            <span class="totals-label">Deslocamento</span>
                            <span class="totals-value">+ R$ {{ number_format($quote->displacement_value, 2, ',', '.') }}</span>
                        </div>
                    @endif
                    <div class="totals-row total-final">
                        <span class="totals-label">VALOR TOTAL</span>
                        <span class="totals-value">R$ {{ number_format($quote->total ?? 0, 2, ',', '.') }}</span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    {{-- ══════ Observations ══════ --}}
    @if($quote->observations)
        <div class="notes-section">
            <div class="notes-title">Observações</div>
            <div class="notes-text">{!! nl2br(e($quote->observations)) !!}</div>
        </div>
    @endif

    {{-- ══════ Conditions ══════ --}}
    <div class="conditions-group">
        <div class="section-card no-break">
            <div class="section-title section-title-blue">
                Condições Gerais
            </div>
            <div class="section-muted-text">
                @if(!empty($quote->general_conditions))
                    {!! nl2br(e($quote->general_conditions)) !!}
                @else
                    &bull; A validade desta proposta é de {{ $validityDays ?? 30 }} dias a contar da data de emissão.<br>
                    &bull; Os preços incluem todos os materiais e mão de obra necessários para a execução dos serviços descritos.<br>
                    &bull; Garantia de 90 dias para serviços e peças, exceto desgaste natural ou uso inadequado.<br>
                    &bull; O prazo de execução será confirmado após a aprovação comercial e o alinhamento operacional.<br>
                    &bull; As condições de pagamento seguem organizadas no quadro abaixo.
                @endif
            </div>
        </div>

        @if($paymentSummary['method_label'] || $paymentSummary['condition_summary'] || count($paymentSummary['schedule']) > 0 || $paymentSummary['detail_text'])
            <div class="section-card no-break" style="background: #f0fdf4; border-color: #bbf7d0;">
                <div class="section-title section-title-green">
                    Condições de Pagamento
                </div>
                <table class="payment-summary-grid">
                    <tr>
                        <td class="payment-summary-label">Meio</td>
                        <td class="payment-summary-value">{{ $paymentSummary['method_label'] ?: ($paymentTermsLabel ?: 'A combinar') }}</td>
                    </tr>
                    <tr>
                        <td class="payment-summary-label">Condição</td>
                        <td class="payment-summary-value">{{ $paymentSummary['condition_summary'] }}</td>
                    </tr>
                    @if($paymentSummary['detail_text'])
                        <tr>
                            <td class="payment-summary-label">Detalhes</td>
                            <td class="payment-summary-value">{{ $paymentSummary['detail_text'] }}</td>
                        </tr>
                    @endif
                </table>

                @if(count($paymentSummary['schedule']) > 0)
                    <div style="font-size: 7px; font-weight: 700; color: #166534; text-transform: uppercase; letter-spacing: 1.5px; margin: 8px 0 4px;">
                        Programação de vencimentos
                    </div>
                    <table class="payment-schedule-table">
                        <thead>
                            <tr>
                                <th style="width: 22%;">Parcela</th>
                                <th style="width: 16%;">Prazo</th>
                                <th style="width: 26%;">Vencimento</th>
                                <th>Descrição</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($paymentSummary['schedule'] as $scheduleLine)
                                <tr>
                                    <td>{{ ucfirst($scheduleLine['title']) }}</td>
                                    <td>{{ $scheduleLine['days'] }} dias</td>
                                    <td>{{ $scheduleLine['due_date'] ?? 'A definir' }}</td>
                                    <td>{{ $scheduleLine['text'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif

        {{-- Detailed Payment Schedule --}}
        @if($quote->payment_terms_detail)
            <div class="section-card no-break" style="background: #fffbeb; border-color: #fde68a;">
                <div class="section-title" style="color: #92400e; border-bottom-color: #fde68a;">
                    Cronograma de Pagamento Detalhado
                </div>
                <div style="font-size: 8px; color: #78350f; line-height: 1.7;">
                    {!! nl2br(e($quote->payment_terms_detail)) !!}
                </div>
            </div>
        @endif
    </div>

    {{-- ══════ QR Code (if available) ══════ --}}
    @if(!empty($qr_code_base64))
        <div class="qr-section no-break">
            <div class="qr-label">Acesse esta proposta online</div>
            <img src="data:image/png;base64,{{ $qr_code_base64 }}" alt="QR Code" style="width: 90px; height: 90px; margin: 4px 0;" />
            <div class="qr-sublabel">Escaneie o código para visualizar ou aprovar esta proposta</div>
        </div>
    @endif

    {{-- ══════ Approval / Signature ══════ --}}
    <div class="approval-wrapper">
        <div class="approval-title">Aceite da Proposta</div>
        <div class="approval-body">
            <p class="approval-text">
                Declaro que li, estou de acordo e aceito os termos e condições descritos nesta proposta comercial.
            </p>
            <div class="signatures">
                <div class="sig-col">
                    <div class="sig-line">
                        <div class="sig-name">{{ $quote->customer->name ?? 'Cliente' }}</div>
                        <div class="sig-role">Cliente</div>
                        <div class="sig-date">Data: ______/______/____________</div>
                    </div>
                </div>
                <div class="sig-col">
                    <div class="sig-line">
                        <div class="sig-name">{{ $quote->seller->name ?? 'Vendedor' }}</div>
                        <div class="sig-role">Representante Comercial</div>
                        <div class="sig-date">Data: ______/______/____________</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
