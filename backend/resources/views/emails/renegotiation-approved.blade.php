@component('mail::message')
# Renegociação Aprovada

Olá {{ $customerName }},

Sua renegociação de débito foi **aprovada**.

**Detalhes:**
- **Valor original:** R$ {{ $originalDebt }}
- **Novo valor total:** R$ {{ $newAmount }}
- **Parcelas:** {{ $installments }}x

As novas parcelas já estão disponíveis para pagamento.

@component('mail::button', ['url' => config('app.frontend_url')])
Acessar Portal
@endcomponent

Atenciosamente,<br>
{{ config('app.name') }}
@endcomponent
