# QA Expert — Camada 1 — auto r1

- **ID:** qa-01
  **Severidade:** S2
  **Arquivo:** `backend/app/Http/Requests/Iam/ListAuditLogRequest.php:18` / `backend/tests/Feature/Api/V1/AuditLogControllerTest.php:76-101`
  **Descrição:** o filtro `user_id` da listagem de audit logs usa `exists:users,id` sem restringir ao tenant atual. Isso permite distinguir IDs globais válidos de inválidos por validação e abre enumeração de usuários entre tenants.
  **Evidência:** a request valida `user_id` globalmente; o teste marca o caso como "AUDIT FINDING P0" e só comprova que logs de outro tenant não vazam, não que a enumeração foi fechada.
  **Impacto:** um usuário com acesso ao endpoint pode mapear IDs de usuários existentes fora do tenant, vazando informação sensível de IAM.

- **ID:** qa-02
  **Severidade:** S3
  **Arquivo:** `backend/tests/Feature/TenantIsolationTest.php:76-125`
  **Descrição:** os testes de leitura, atualização e exclusão cross-tenant aceitam tanto `403` quanto `404`. Essa tolerância enfraquece o contrato de isolamento e pode mascarar regressões entre "sem permissão" e "não encontrado".
  **Evidência:** os três fluxos usam `assertContains($response->getStatusCode(), [403, 404]);`.
  **Impacto:** uma mudança que quebre o comportamento esperado de isolamento pode continuar passando, reduzindo a capacidade do teste de detectar regressões de segurança/comportamento.

- **ID:** qa-03
  **Severidade:** S2
  **Arquivo:** `backend/tests/Feature/AuthSecurityTest.php:33-37` / `backend/app/Http/Requests/Auth/SwitchTenantRequest.php:9-12` / `backend/app/Http/Controllers/Api/V1/Auth/AuthController.php:256-309`
  **Descrição:** a suíte de segurança de autenticação desativa todas as checagens de Gate com `Gate::before(fn () => true)`, mas o fluxo de troca de tenant depende exatamente de `SwitchTenantRequest::authorize() -> can('platform.tenant.switch')`. Assim, a suíte não detecta regressões na autorização desse endpoint.
  **Evidência:** o teste força Gate a aprovar tudo; o request autoriza por permissão; o controller executa troca de tenant, revogação de tokens e atualização de `current_tenant_id` após essa autorização.
  **Impacto:** uma quebra real na permissão `platform.tenant.switch` pode entrar em produção sem ser capturada por essa cobertura.

- **Nada encontrado em** `frontend/package.json` e nos testes frontend inspecionados para a seção de gates de qualidade. **Verificado:** `frontend/package.json`, `frontend/vitest.config.ts`, `frontend/src/__tests__/api/api-client.test.ts`, `frontend/src/__tests__/contracts/backend-route-contracts.test.ts`.
