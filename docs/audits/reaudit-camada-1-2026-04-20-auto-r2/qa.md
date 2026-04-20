# QA Expert — Reauditoria Camada 1 r2

## Escopo

Regressões de Camada 1: HR schedules, RBAC, Service Ops tenant context, webhooks com `external_id` único, org chart limitado e gates de backend.

## Procedimento

- Rodados testes específicos de HR schedules e RBAC.
- Rodado gate afetado pelo diff via `pest --dirty --parallel`.
- Verificados padrões anti-máscara nos testes alterados.

## Resultado

Zero findings S1..S4.

## Evidências

- Há regressão explícita para rejeitar criação de escala sem `current_tenant_id`.
- Cross-tenant em schedules retorna 404 nos testes existentes.
- Payload de schedule usa `technician_id`, não `user_id`.
- Não foram introduzidos `skip`, `markIncomplete` ou `assertTrue(true)` nos testes do escopo.
- Estado global de mass-assignment em testes é restaurado no `tearDown()` base para não depender da ordem do `pest --parallel`.

## Comandos

```powershell
cd backend
php vendor\bin\pest tests\Feature\Api\V1\Hr\WorkScheduleControllerTest.php --no-coverage
# Tests: 14 passed (27 assertions)
# Duration: 3.95s

php vendor\bin\pest tests\Feature\Api\V1\HRControllerTest.php --filter="schedule" --no-coverage
# Tests: 9 passed (22 assertions)
# Duration: 3.21s

php vendor\bin\pest tests\Feature\Rbac\HrRbacTest.php --filter="schedule" --no-coverage
# Tests: 6 passed (6 assertions)
# Duration: 2.65s

php vendor\bin\pest tests\Feature\Security\AuditLogImmutabilityTest.php --filter="mass-assignment via create" --no-coverage
# Tests: 1 passed (1 assertions)
# Duration: 1.83s

php vendor\bin\pest tests\Unit\Services\WorkOrderServiceTest.php tests\Feature\Security\AuditLogImmutabilityTest.php --no-coverage
# Tests: 29 passed (47 assertions)
# Duration: 5.95s

.\vendor\bin\pest --dirty --parallel --no-coverage --log-junit=storage\logs\pest-dirty-r2.xml
# Tests: 8509 passed (22484 assertions)
# Duration: 253.72s
```
