# Dependency Layers

**Matrix version:** 0.1.0

## Camadas

| Camada | Nome | Depende de | Foco |
| --- | --- | --- | --- |
| 0 | Baseline Operacional | nenhuma | boot, ambiente local, scripts, dependencias, memoria, comandos, Git |
| 1 | Fundacao, Auth e Tenant | 0 | autenticacao, permissao, tenant isolation, policies, FormRequest authorize |
| 2 | API e Backend | 0, 1 | rotas, controllers, FormRequests, resources, paginacao, contratos REST |
| 3 | Frontend SPA | 0, 2 | React, TypeScript, API clients, hooks, forms, acessibilidade basica |
| 4 | Modulos e E2E | 0, 1, 2, 3 | fluxos de negocio, Playwright, integracao frontend/backend |
| 5 | Infra e CI/CD | 0, 2, 3 | artefatos nao-produtivos, Docker, CI, build, health checks locais |
| 6 | Qualidade Global | 0-5 | fechamento transversal sem redesign estrutural |
| 7 | Producao e Deploy | 0-6 | deploy real autorizado, rollback, backups, migrations sensiveis, health checks |

## Criterios de Aceite

Todos os criterios abaixo sao avaliados por cinco auditores/subagentes read-only diferentes, com contexto limpo, antes de uma camada poder ser aprovada. Os comandos deterministas nao substituem os auditores; eles sao evidencias que os auditores e o verificador devem registrar. Antes de acionar os auditores, a run deve ter `impact-manifest.json` gerado pelo CLI para expor arquivos alterados, superficies afetadas e riscos por path.

| Camada | Criterios bloqueantes | Fontes canonicas |
| --- | --- | --- |
| 0 | regras carregaveis; runtime minimo; comandos principais conhecidos; Git sem sujeira da run; memoria consultada | `AGENTS.md`, `.codex/memory.md`, `.agent/rules/test-runner.md`, `docs/operacional/mapa-testes.md` |
| 1 | tenant isolation; auth/Sanctum/permissoes; FormRequests com `authorize()` real; auditor de permissoes quando existir | `.agent/rules/kalibrium-context.md`, `docs/auditoria/CAMADA-1-FUNDACAO.md`, `backend/tests/README.md` |
| 2 | rotas/controllers/FormRequests/resources aderentes; paginacao; eager loading; contratos REST; tenant no controller | `.agent/rules/kalibrium-context.md`, `docs/auditoria/CAMADA-2-API-BACKEND.md`, `docs/architecture/15-15-api-versionada.md` |
| 3 | TypeScript sem `any` novo; React Query; RHF/Zod; API clients corretos; build/typecheck/lint afetados | `.cursor/rules/frontend-type-consistency.mdc`, `docs/auditoria/CAMADA-3-FRONTEND.md`, `docs/design-system/` |
| 4 | fluxos preservados; E2E afetado; regressao de modulo; contratos frontend/backend coerentes | `docs/auditoria/CAMADA-4-MODULOS-E2E.md`, `docs/PRD-KALIBRIUM.md` |
| 5 | Docker/scripts/CI sem regressao; configs sem segredo; build e health checks locais; sem deploy real | `docs/auditoria/CAMADA-5-INFRA-DEPLOY.md`, `.cursor/rules/integration-safety.mdc`, `.cursor/rules/deploy-production.mdc` |
| 6 | lint/analise/testes relevantes; seguranca transversal triada; cobertura nao piora; owner reclassificado | `docs/auditoria/CAMADA-6-TESTES-QUALIDADE.md`, `.agent/rules/test-policy.md`, `.agent/rules/test-runner.md` |
| 7 | backup/rollback; deploy pela LLM CLI somente com `deployment_authorization`; health checks; migrations seguras | `docs/auditoria/CAMADA-7-PRODUCAO-DEPLOY.md`, `.cursor/rules/deploy-production.mdc`, `.cursor/rules/migration-production.mdc` |

## Ownership

| Padrao | Camada proprietaria | Observacao |
| --- | --- | --- |
| `AGENTS.md`, `.agent/rules/**`, `.agent/skills/**`, `.agent/workflows/**` | governanca sensivel | exige justificativa |
| `.codex/memory.md`, `docs/harness/**` | governanca/harness | permitido para tarefa de harness |
| `backend/app/Models/**`, `backend/database/migrations/**`, `backend/database/factories/**` | 1, 2 ou 7 | depende de auth/tenant, dominio API ou deploy/migration em producao |
| `backend/app/Http/Middleware/**`, `backend/app/Policies/**` | 1 | auth/permissao |
| `backend/app/Http/Controllers/**`, `backend/app/Http/Requests/**`, `backend/app/Http/Resources/**`, `backend/routes/**` | 2 | contratos REST |
| `backend/tests/Feature/Auth/**` | 1 | auth/tenant/permissao |
| `backend/tests/Feature/**`, `backend/tests/Unit/**` | camada do comportamento | teste herda owner do comportamento |
| `frontend/src/**` | 3 | UI, hooks, clients, tipos |
| `frontend/tests/**`, `frontend/src/**/*.test.*` | 3 ou 4 | unit frontend ou fluxo |
| `e2e/**`, `frontend/e2e/**`, `playwright.config.*` | 4 | E2E |
| `Dockerfile*`, `docker-compose*`, `.github/**`, `nginx/**`, `.env.example` | 5 | infra nao-produtiva |
| `deploy.sh`, docs/scripts de rollback/producao/backup/health real | 7 | exige autorizacao |
| `docs/auditoria/**`, `docs/architecture/**`, `docs/PRD-KALIBRIUM.md` | fonte canonica | mudar criterio exige versionamento |

## Verificacoes Deterministicas Iniciais

Para fechamento `approved`, as verificacoes deterministicas da camada devem estar registradas em `commands.log.jsonl`, `impact-manifest.json` deve estar atualizado para o HEAD, `consolidated-findings.json` deve conter `audit_coverage` com agent_ids correspondentes exatamente aos cinco auditores executados, e os cinco auditores obrigatorios devem aprovar a rodada declarando `audit_limitations`. `targeted` e `verification_only` nao fecham camada como aprovada.

| Camada | Comandos |
| --- | --- |
| 0 | `node scripts/harness-cycle.mjs generate-impact --run-id RUN_ID`; `git status --short`; `php -v`; `node -v`; `cd backend && php artisan --version`; `cd backend && ./vendor/bin/pest --help`; `cd frontend && node -e "const s=require('./package.json').scripts; ['typecheck','lint','build','test','test:e2e'].forEach(k=>{if(!s[k]) throw new Error(k)})"` |
| 1 | `node scripts/harness-cycle.mjs generate-impact --run-id RUN_ID`; `cd backend && php artisan camada1:audit-permissions`; testes afetados de auth/tenant/permissao; `cd backend && ./vendor/bin/pest --dirty --parallel --no-coverage` |
| 2 | `node scripts/harness-cycle.mjs generate-impact --run-id RUN_ID`; `cd backend && php artisan camada2:validate-routes`; testes afetados de controller/API; `cd backend && ./vendor/bin/pest --dirty --parallel --no-coverage` |
| 3 | `node scripts/harness-cycle.mjs generate-impact --run-id RUN_ID`; `cd frontend && npm run typecheck`; `cd frontend && npm run lint`; `cd frontend && npm run build`; Vitest afetado |
| 4 | `node scripts/harness-cycle.mjs generate-impact --run-id RUN_ID`; Playwright afetado; testes backend/frontend do fluxo; smoke de contrato quando aplicavel |
| 5 | `node scripts/harness-cycle.mjs generate-impact --run-id RUN_ID`; validacao de scripts/compose; build local; checagem de secrets/configs; sem deploy |
| 6 | `node scripts/harness-cycle.mjs generate-impact --run-id RUN_ID`; analise estatica/lint relevante; Pest/Vitest/Playwright em escopo escalado |
| 7 | `node scripts/harness-cycle.mjs generate-impact --run-id RUN_ID`; checklist de backup/rollback; dry-run quando existir; deploy real pela LLM CLI quando autorizado |
