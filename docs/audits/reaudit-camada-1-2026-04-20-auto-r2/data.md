# Data Expert — Reauditoria Camada 1 r2

## Escopo

Migrations, schema, tenant isolation em persistência, índices de idempotência de webhooks e renomeação de `work_schedules.user_id` para `technician_id`.

## Procedimento

- Inspecionadas migrations novas de `external_id` único e `work_schedules`.
- Inspecionadas queries de HR schedules, webhooks e Service Ops.
- Verificados testes de tenant isolation e regressão de contexto de tenant atual.

## Resultado

Zero findings S1..S4.

## Evidências

- Migration de `external_id` único valida existência de tabela/coluna, deduplica antes de criar índice e possui `down()`.
- Migration de `work_schedules` valida tabela, coluna de origem e coluna de destino antes de renomear em `up()` e `down()`.
- Índice novo de escala é tenant-aware: `tenant_id`, `technician_id`, `date`.
- HR schedules persiste `tenant_id` pelo contexto atual resolvido, não pelo body.
- `technician_id` é validado contra `users.id` filtrado pelo tenant atual.

## Comandos

```powershell
cd backend
php vendor\bin\pest tests\Feature\Api\V1\Hr\WorkScheduleControllerTest.php --no-coverage
# Tests: 14 passed (27 assertions)
# Duration: 3.95s

php vendor\bin\pest tests\Feature\Api\V1\HRControllerTest.php --filter="schedule" --no-coverage
# Tests: 9 passed (22 assertions)
# Duration: 3.21s

composer analyse
# [OK] No errors
```
