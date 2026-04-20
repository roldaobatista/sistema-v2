# Integration Expert — Reauditoria Camada 1 r2

## Escopo

Integrações públicas e contratos internos da Camada 1: webhooks de pagamento, WhatsApp/email, idempotência por `external_id`, HR schedule API e RBAC.

## Procedimento

- Verificadas rotas públicas e middlewares de webhook.
- Verificados contratos de payload para HR schedules.
- Rodados testes de RBAC e regressões de API.

## Resultado

Zero findings S1..S4.

## Evidências

- `POST /api/v1/webhooks/payment` é público, throttled e valida assinatura internamente antes de processamento.
- Webhooks WhatsApp/email usam middleware `verify.webhook` e não aceitam tenant do payload.
- `POST /api/v1/hr/schedules` e `POST /api/v1/work-schedules` usam `technician_id`.
- RBAC de schedules continua cobrindo view/manage.

## Comandos

```powershell
cd backend
php vendor\bin\pest tests\Feature\Rbac\HrRbacTest.php --filter="schedule" --no-coverage
# Tests: 6 passed (6 assertions)
# Duration: 2.65s

.\vendor\bin\pest --dirty --parallel --no-coverage --log-junit=storage\logs\pest-dirty-r2.xml
# Tests: 8509 passed (22484 assertions)
# Duration: 253.72s
```
