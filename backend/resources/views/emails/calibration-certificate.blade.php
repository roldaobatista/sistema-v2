@component('mail::message')
# Certificados de Calibração

Olá, **{{ $customerName }}**!

A Nota Fiscal referente à sua Ordem de Serviço **#{{ $workOrder->business_number }}** foi autorizada.

Segue(m) em anexo **{{ $count }}** certificado(s) de calibração para sua guarda.

**Resumo:**
- **OS:** {{ $workOrder->business_number }}
- **Certificados anexos:** {{ $count }}

Em caso de dúvidas, entre em contato conosco.

{{ config('app.name') }}
@endcomponent
