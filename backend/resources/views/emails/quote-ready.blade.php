@component('mail::message')
# Orçamento Pronto

Olá, **{{ $customerName }}**!

O seu orçamento **#{{ $quote->quote_number }}** está pronto para análise.

**Valor Total:** R$ {{ $total }}

@if($quote->valid_until)
**Válido até:** {{ $quote->valid_until->format('d/m/Y') }}
@endif

@if($quote->seller)
**Vendedor:** {{ $quote->seller->name }}
@if($quote->seller->phone)
 | **Telefone:** {{ $quote->seller->phone }}
@endif
@endif

@if(!empty($customMessage))
---
{{ $customMessage }}
---
@endif

@component('mail::button', ['url' => $approvalUrl])
Ver Orçamento
@endcomponent

Ficamos à disposição para esclarecer quaisquer dúvidas.

Obrigado pela confiança!

{{ config('app.name') }}
@endcomponent
