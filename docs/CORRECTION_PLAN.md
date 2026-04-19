# Plano de Correção — Kalibrium ERP (10 Camadas)

> **Diretiva do board:** corrigir TUDO. Sistema ainda sem clientes, risco baixo, pode ousar.
> **Estratégia:** dividir em 10 camadas de dependência e atacar **da base até o teto**.
> Cada camada só começa quando a anterior estiver com **zero findings** e CI verde.

## Idioma
- Board/agentes: **PT-BR** | Código/commits/branches: **inglês** | Docs "why": **PT-BR**

---

## CAMADA 1 — Infra & DevOps (FUNDAÇÃO)
Tudo depende disso funcionando.
- Dockerfile backend/frontend multi-stage + layer cache
- `docker-compose.yml` dev/prod/test (já existe — auditar)
- GitHub Actions: `ci.yml`, `deploy.yml`, `security.yml`, `nightly.yml`
- Observability: logs estruturados JSON + Prometheus + Grafana
- Backup automático DB + retention policy
- Rollback plan + runbook em `docs/runbooks/`

## CAMADA 2 — Segurança Fundacional
Impede vazamento mesmo com código abaixo quebrado.
- **Revogar** token exposto em `tests/e2e/tmp/config.json` + purge histórico (git filter-repo)
- Separar `.env.example` (dev) vs `.env.production.example`
- Fix `.env.example` dev: `APP_ENV=local`, `APP_DEBUG=true`
- CORS restritivo (allowlist explícita, sem `*`)
- CSP explícita em middleware
- Auth Bearer-only (remover `InjectBearerFromCookie`)
- Webhook secret só em header `X-Webhook-Secret`
- Rate limiting em endpoints públicos
- Dependabot + Trivy + Gitleaks no CI

## CAMADA 3 — Schema, Migrations, DB
Base de dados é o que sobrevive a tudo.
- Auditar as 442 migrations: todas zero-downtime? additive-first?
- Guards em migrations antigas (já existe — validar)
- Índices faltantes (EXPLAIN queries lentas)
- Constraints FK + CHECK
- Estratégia de backup + restore testada
- Tenant isolation testado em SQL puro (cross-tenant queries devem falhar)

## CAMADA 4 — Multi-tenant Core
Isolamento de dados é S1.
- Trait `BelongsToTenant` — auditar cobertura (todos os models)
- Middleware `EnsureTenantScope` — bypass paths, edge cases
- Policies — cobertura por model
- Global Scopes — garantir em 100% das queries
- Teste de regressão: tenant A **não pode** ler dados do tenant B em NENHUMA rota
- Red Team: tentar vazamento ativamente

## CAMADA 5 — Domain Models & Services
Regras de negócio.
- Models (411) — auditar invariants, mass-assignment protection
- Services (158) — transaction safety, idempotência
- Value Objects para dinheiro, CPF/CNPJ, datas
- Eventos de domínio (Event Sourcing parcial)
- Testes unitários cobrindo regras críticas (ISO 17025, cálculos fiscais, SLA)

## CAMADA 6 — API Controllers, Form Requests, Policies
Superfície de entrada HTTP.
- Controllers (300) — auditar padrão REST consistente
- Form Requests (835) — validação completa, sem bypass
- Policies aplicadas em TODO controller
- Resource transformers (API Resources) — sem leak de dados
- Versionamento de API (`/api/v1/...`)
- OpenAPI/Swagger gerado automaticamente

## CAMADA 7 — Integrações Externas
Fluxo de dinheiro entra e sai aqui.
- PSPs: boleto/PIX — happy-path + retries + idempotência
- Webhooks de baixa (PSP → sistema) com assinatura validada
- Auvo (campo) — sincronização bidirectional
- Brasil API — cache + fallback
- Fiscal NF-e/NFS-e — emissão, cancelamento, retransmissão
- WhatsApp inbound — consolidar (hoje fragmentado)
- Circuit breakers em todas integrações

## CAMADA 8 — Frontend Foundation
Base React/TS.
- `src/lib/api.ts` — eliminar duplicação (unwrapData, getApiOrigin)
- Auth state com Zustand — refresh token, logout global
- Routing + error boundary + suspense
- Offline/sync helpers consolidados
- Typed API client (zod schemas compartilhados com backend se possível)

## CAMADA 9 — Frontend Features
Páginas e componentes por módulo.
- 371 páginas — auditar padrão (loading/error/empty states)
- 167 componentes — consolidar duplicatas, design system
- 61 hooks — audit de side effects, cleanup
- Acessibilidade WCAG 2.1 AA
- Performance: bundle size, lazy loading, React.memo onde faz diferença

## CAMADA 10 — Testes E2E, Docs, Onboarding
Garantia de que não quebrou.
- Playwright E2E cobrindo fluxos críticos: login, OS fim-a-fim, faturamento, fiscal
- Regression suite completa passa em < 5min
- README atualizado com números reais
- `docs/architecture/` (atualizar), `docs/deploy/GUIDE.md` (criar)
- CHANGELOG a cada slice
- `docs/.archive/` — arquivar ou remover
- ADRs (`docs/adr/`) cobrindo decisões dessa reforma

---

## Regras de execução (toda camada)

1. **Entrada:** CEO cria épico `EPIC-NN: Camada N - Nome`.
2. **Quebra:** Tech Lead divide em slices de 1-3 dias cada (`feat/slice-NNN-...`).
3. **Implementação:** Implementer → 13 auditores em paralelo → Fixer → re-audit → zero findings → PR-Approver merge automático.
4. **Saída:** camada marcada ✅ + commit `chore(layer-N): camada N concluida` em `main`.
5. **Progressão:** só começa camada N+1 quando N estiver ✅ E suite verde.

## Milestone ao board

- A cada camada concluída, CEO cria comentário na issue-mãe com:
  - PRs mergeados
  - Testes adicionados
  - Riscos remanescentes
  - Tokens consumidos
- Board (humano) **NÃO** precisa aprovar. Apenas observa.
