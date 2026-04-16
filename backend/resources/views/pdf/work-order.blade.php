@extends('pdf.layout')

@section('title', "OS {$workOrder->business_number}")

@section('content')
    {{-- Badge --}}
    <div class="doc-badge">
        <div class="doc-badge-left">
            <span class="doc-type">Ordem de Serviço</span>
            <div class="doc-number">{{ $workOrder->business_number }}</div>
        </div>
        <div class="doc-badge-right">
            <div class="doc-date">
                <strong>Emissão:</strong> {{ $workOrder->created_at->format('d/m/Y') }}<br>
                @if($workOrder->completed_at)
                    <strong>Conclusão:</strong> {{ $workOrder->completed_at->format('d/m/Y') }}<br>
                @endif
                <span class="status-badge status-{{ $workOrder->status }}">
                    {{ \App\Models\WorkOrder::STATUSES[$workOrder->status]['label'] ?? $workOrder->status }}
                </span>
                @if($workOrder->status === 'invoiced')
                    <span style="background: #10b981; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-left: 4px;">FATURADA</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Cliente & Equipamento --}}
    <div class="info-grid">
        <div class="info-col">
            <div class="info-box">
                <div class="info-box-title">Cliente</div>
                <div class="info-row"><span class="info-label">Nome</span><span class="info-value">{{ $workOrder->customer->name ?? '—' }}</span></div>
                <div class="info-row"><span class="info-label">CPF/CNPJ</span><span class="info-value">{{ $workOrder->customer->document ?? '—' }}</span></div>
                <div class="info-row"><span class="info-label">Telefone</span><span class="info-value">{{ $workOrder->customer->phone ?? '—' }}</span></div>
                <div class="info-row"><span class="info-label">E-mail</span><span class="info-value">{{ $workOrder->customer->email ?? '—' }}</span></div>
                @if($workOrder->customer->address_city ?? false)
                    <div class="info-row"><span class="info-label">Cidade</span><span class="info-value">{{ $workOrder->customer->address_city }}/{{ $workOrder->customer->address_state }}</span></div>
                @endif
            </div>
        </div>
        <div class="info-col">
            <div class="info-box">
                <div class="info-box-title">Equipamento</div>
                @if($workOrder->equipment)
                    <div class="info-row"><span class="info-label">Código</span><span class="info-value">{{ $workOrder->equipment->code ?? '—' }}</span></div>
                    <div class="info-row"><span class="info-label">Marca</span><span class="info-value">{{ $workOrder->equipment->brand ?? '—' }}</span></div>
                    <div class="info-row"><span class="info-label">Modelo</span><span class="info-value">{{ $workOrder->equipment->model ?? '—' }}</span></div>
                    <div class="info-row"><span class="info-label">Nº Série</span><span class="info-value">{{ $workOrder->equipment->serial_number ?? '—' }}</span></div>
                    <div class="info-row"><span class="info-label">Capacidade</span><span class="info-value">{{ $workOrder->equipment->capacity ?? '—' }} {{ $workOrder->equipment->capacity_unit ?? '' }}</span></div>
                @else
                    <div class="info-row"><span class="info-value">Nenhum equipamento vinculado</span></div>
                @endif
            </div>
        </div>
    </div>

    {{-- Informações da OS --}}
    <div class="info-grid">
        <div class="info-col">
            <div class="info-box">
                <div class="info-box-title">Detalhes da OS</div>
                <div class="info-row"><span class="info-label">Prioridade</span><span class="info-value">{{ \App\Models\WorkOrder::PRIORITIES[$workOrder->priority]['label'] ?? $workOrder->priority }}</span></div>
                <div class="info-row"><span class="info-label">Técnico</span><span class="info-value">{{ $workOrder->assignee->name ?? '—' }}</span></div>
                <div class="info-row"><span class="info-label">Vendedor</span><span class="info-value">{{ $workOrder->seller->name ?? '—' }}</span></div>
                <div class="info-row"><span class="info-label">Criado por</span><span class="info-value">{{ $workOrder->creator->name ?? '—' }}</span></div>
            </div>
        </div>
        <div class="info-col">
            <div class="info-box">
                <div class="info-box-title">Datas</div>
                <div class="info-row"><span class="info-label">Recebido</span><span class="info-value">{{ $workOrder->received_at?->format('d/m/Y H:i') ?? '—' }}</span></div>
                <div class="info-row"><span class="info-label">Iniciado</span><span class="info-value">{{ $workOrder->started_at?->format('d/m/Y H:i') ?? '—' }}</span></div>
                <div class="info-row"><span class="info-label">Concluído</span><span class="info-value">{{ $workOrder->completed_at?->format('d/m/Y H:i') ?? '—' }}</span></div>
                <div class="info-row"><span class="info-label">Entregue</span><span class="info-value">{{ $workOrder->delivered_at?->format('d/m/Y H:i') ?? '—' }}</span></div>
            </div>
        </div>
    </div>

    {{-- Descrição --}}
    @if($workOrder->description)
        <div class="notes-section" style="background: #f0f4ff; border-color: #bfdbfe;">
            <div class="notes-title" style="color: #1e40af;">Descrição do Serviço</div>
            <div class="notes-text" style="color: #1e293b;">{{ $workOrder->description }}</div>
        </div>
    @endif

    {{-- Itens / Serviços --}}
    @if($workOrder->items->isEmpty())
        <p style="text-align: center; color: #666; padding: 20px;">Nenhum item cadastrado nesta OS.</p>
    @else
        <table class="data-table" style="page-break-inside: auto;">
            <thead>
                <tr>
                    <th style="width: 40px">#</th>
                    <th>Descrição</th>
                    <th style="width: 50px">Tipo</th>
                    <th style="width: 45px; text-align: center">Qtd</th>
                    <th style="width: 80px; text-align: right">Unit.</th>
                    <th style="width: 90px; text-align: right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($workOrder->items as $i => $item)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td style="word-break: break-word; overflow-wrap: break-word; max-width: 300px;"><strong>{{ $item->description }}</strong></td>
                        <td>{{ $item->type === 'product' ? 'Peça' : 'Serviço' }}</td>
                        <td style="text-align: center">{{ number_format($item->quantity, 0) }}</td>
                        <td style="text-align: right">R$ {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                        <td style="text-align: right"><strong>R$ {{ number_format($item->total, 2, ',', '.') }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Totais --}}
    <div class="clearfix">
        <div class="totals-box">
            <div class="totals-row"><span class="totals-label">Subtotal Bruto</span><span class="totals-value">R$ {{ $subtotal }}</span></div>
            @if((float) $items_discount > 0)
                <div class="totals-row"><span class="totals-label">Desconto dos Itens</span><span class="totals-value" style="color: #dc2626">- R$ {{ $items_discount }}</span></div>
            @endif
            @if((float) $discount > 0)
                <div class="totals-row"><span class="totals-label">Desconto Global</span><span class="totals-value" style="color: #dc2626">- R$ {{ $discount }}</span></div>
            @endif
            @if((float) $displacement > 0)
                <div class="totals-row"><span class="totals-label">Deslocamento</span><span class="totals-value">R$ {{ $displacement }}</span></div>
            @endif
            <div class="totals-row total-final"><span class="totals-label">TOTAL</span><span class="totals-value">R$ {{ $total }}</span></div>
        </div>
    </div>

    {{-- Laudo Técnico --}}
    @if($workOrder->technical_report)
        <div class="notes-section no-break" style="margin-top: 20px;">
            <div class="notes-title">Laudo Técnico</div>
            <div class="notes-text">{{ $workOrder->technical_report }}</div>
        </div>
    @endif

    {{-- Observações Internas --}}
    @if($workOrder->internal_notes)
        <div class="notes-section no-break" style="margin-top: 10px; background: #fef2f2; border-color: #fecaca;">
            <div class="notes-title" style="color: #991b1b;">Observações Internas</div>
            <div class="notes-text" style="color: #7f1d1d;">{{ $workOrder->internal_notes }}</div>
        </div>
    @endif

    {{-- Fotos do Checklist --}}
    @php
        $checklist = $workOrder->photo_checklist;
        $photos = [];
        if (is_array($checklist)) {
            foreach (['before' => 'Antes', 'during' => 'Durante', 'after' => 'Depois'] as $step => $label) {
                foreach ($checklist[$step] ?? [] as $entry) {
                    if (!empty($entry['path']) || !empty($entry['url'])) {
                        $photos[] = ['label' => $label, 'path' => $entry['path'] ?? $entry['url'], 'desc' => $entry['description'] ?? ''];
                    }
                }
            }
            foreach ($checklist['items'] ?? [] as $item) {
                if (!empty($item['photo_url'])) {
                    $photos[] = ['label' => $item['text'] ?? 'Item', 'path' => $item['photo_url'], 'desc' => ''];
                }
            }
        }
    @endphp
    @if(count($photos) > 0)
        <div class="no-break" style="margin-top: 20px;">
            <div class="notes-title">Registro Fotográfico</div>
            <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 8px;">
                @foreach($photos as $photo)
                    <div style="width: 48%; text-align: center; margin-bottom: 10px;">
                        @php
                            $fullPath = Storage::disk('public')->path($photo['path']);
                            $exists = file_exists($fullPath);
                        @endphp
                        @if($exists)
                            <img src="{{ $fullPath }}" style="max-width: 100%; max-height: 200px; border: 1px solid #e5e7eb; border-radius: 4px;" />
                        @else
                            <div style="height: 100px; background: #f3f4f6; border: 1px dashed #d1d5db; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 11px;">Foto indisponível</div>
                        @endif
                        <p style="font-size: 10px; color: #6b7280; margin-top: 4px;">{{ $photo['label'] }}{{ $photo['desc'] ? ' — ' . $photo['desc'] : '' }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Assinaturas --}}
    <div class="signatures no-break">
        <div class="sig-col">
            <div class="sig-line">
                <div class="sig-name">{{ $workOrder->assignee->name ?? 'Técnico' }}</div>
                <div class="sig-role">Técnico Responsável</div>
            </div>
        </div>
        <div class="sig-col">
            <div class="sig-line">
                @if($workOrder->signature_at || ($workOrder->relationLoaded('signatures') && $workOrder->signatures->isNotEmpty()))
                    <div class="sig-name">{{ $workOrder->customer->name ?? 'Cliente' }}</div>
                    <div class="sig-role">Cliente — Assinado em {{ $workOrder->signature_at?->format('d/m/Y H:i') ?? '' }}</div>
                @else
                    <div class="sig-name" style="color: #999; font-style: italic;">Aguardando assinatura</div>
                    <div class="sig-role">Cliente</div>
                @endif
            </div>
        </div>
    </div>
@endsection
