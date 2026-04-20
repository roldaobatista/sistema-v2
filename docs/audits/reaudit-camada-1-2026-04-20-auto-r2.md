# Reauditoria Camada 1 — auto r2 — 2026-04-20

## Escopo

Camada 1: fundação multi-tenant, auth/portal/security, webhooks externos, schema/migrations, HR schedules, inventory tenant-fill, testes/gates e harness.

## Regra de auditoria

Reauditoria conduzida com prompt neutro conforme `AGENTS.md` e `.claude/skills/audit-prompt.md`: investigação por perímetro funcional, sem usar diff/commit como fonte de direcionamento e sem solicitar aprovação/validação antecipada.

## Experts

- Security: `docs/audits/reaudit-camada-1-2026-04-20-auto-r2/security.md`
- Data: `docs/audits/reaudit-camada-1-2026-04-20-auto-r2/data.md`
- QA: `docs/audits/reaudit-camada-1-2026-04-20-auto-r2/qa.md`
- Governance: `docs/audits/reaudit-camada-1-2026-04-20-auto-r2/governance.md`
- Architecture: `docs/audits/reaudit-camada-1-2026-04-20-auto-r2/architecture.md`
- Integration: `docs/audits/reaudit-camada-1-2026-04-20-auto-r2/integration.md`

## Findings

Zero findings S1..S4 após correção dos achados descobertos durante a própria r2.

## Achados corrigidos durante r2

- `r2-hr-tenant-fallback`: HR schedules ainda tinha fallback para `user()->tenant_id` em controller/FormRequests. Corrigido para `CurrentTenantResolver`/`ResolvesCurrentTenant` e coberto por regressões 403 sem `current_tenant_id`.
- `r2-work-schedules-migration-guard`: migration de renomeação podia tentar renomear coluna ausente em schema parcialmente migrado. Corrigida com guardas de coluna de origem/destino em `up()` e `down()`.
- `r2-lei4-comments`: alguns acessos sem `tenant` global scope no perímetro de webhook/tenant service não tinham justificativa local no ponto de uso. Comentários `LEI 4 JUSTIFICATIVA` adicionados.
- `r2-test-unguard-leak`: tentativa de commit foi bloqueada porque testes anteriores deixavam `Model::unguard()` ativo no processo paralelo, quebrando a garantia forense de mass-assignment do `AuditLog`. Corrigido com `Model::reguard()` no `tearDown()` base e regressão combinando teste que desguarda model com `AuditLogImmutabilityTest`.

## Evidência de comandos

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

.\vendor\bin\pint --test
# {"result":"pass"}

composer analyse
# [OK] No errors

.\vendor\bin\pest --dirty --parallel --no-coverage --log-junit=storage\logs\pest-dirty-r2.xml
# Tests: 8509 passed (22484 assertions)
# Duration: 253.72s
```

## Veredito

FECHADA no perímetro auditado: zero findings S1..S4.
