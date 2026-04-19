# Playbook de Estabilização Kalibrium ERP — Bottom-Up por Dependência

> **Versão:** 1.0 — 2026-04-17
> **Owner:** Roldão (PO) + Claude (orchestrator + 12 especialistas)
> **Objetivo:** Estabilizar o Kalibrium ERP corrigindo falhas camada por camada, partindo da fundação física (Schema) até a superfície (Integrações), na ordem rigorosa de dependência.
> **Premissa:** Tudo depende da camada de baixo. Bug na fundação contamina N camadas acima. Consertar de baixo para cima minimiza retrabalho.

---

## 🎯 Princípios Operacionais Invioláveis

1. **Camada N+1 só inicia com camada N 100% aprovada (unanimemente)** pelos especialistas relevantes.
2. **Aprovação unânime = todos os especialistas atribuídos à camada confirmam: 0 findings de QUALQUER severidade (S0+S1+S2+S3+S4 = 0).** Não há "backlog" no meio do processo. Tudo é resolvido. Inclusive os menores. Se um finding não merece ser corrigido, justificar formalmente como "falso positivo" no relatório (não conta como finding).
3. **Re-auditoria COMPLETA após qualquer correção.** Toda correção pode introduzir novo bug ou expor finding que estava encoberto. Após corrigir 1 ou N findings, a rodada seguinte audita do ZERO (não delta) com TODOS os especialistas da camada. Não confiar em "já auditei isso na rodada anterior".
4. **Loop até 10 rodadas POR CAMADA.** Se na rodada 10 ainda houver QUALQUER finding (S0-S4) sem resolver ou justificar, ESCALAR para usuário — NUNCA pular camada, NUNCA marcar como aceitável.
5. **Modo autônomo durante a camada.** O orchestrator NÃO pergunta ao usuário no meio do processo. Decide, executa, audita, corrige, re-audita. Só interrompe para o usuário em 3 casos: (a) finding S0 detectado, (b) 3 tentativas falhas no mesmo finding, (c) rodada 10 atingida sem zerar tudo.
6. **Toda mudança segue formato Harness 6+1** (problema → arquivos → motivo → testes → resultado → riscos → rollback).
7. **Testes na pirâmide:** específico (camada) → grupo → testsuite → suite completa (gate final). NUNCA suite completa no meio. Mas a SUITE COMPLETA é mandatória no Gate Final da camada — não pular.
8. **Zero atalhos.** Iron Protocol em pleno vigor: sem `--no-verify`, sem `--ignore-platform-reqs`, sem mascarar teste, sem `markIncomplete`, sem `assertTrue(true)`, sem comentar código pra "desativar".

---

## 📐 Severidade dos Findings

| Severidade | Definição | Ação |
|---|---|---|
| **S0** | Sistêmico, exige decisão arquitetural ou de produto que orchestrator não pode tomar | ESCALAR usuário imediatamente (parar tudo) |
| **S1** | Crítico — bloqueia funcionalidade, vazamento de tenant, perda de dado, vulnerabilidade ativa | Corrigir nesta rodada, obrigatório |
| **S2** | Alto — bug visível para usuário, regressão, performance ruim, padrão obrigatório violado | Corrigir nesta rodada, obrigatório |
| **S3** | Médio — débito técnico, melhoria, cobertura adicional, código duplicado, falta documentação interna | **Corrigir nesta rodada, obrigatório** |
| **S4** | Baixo — refactor cosmético, naming, comentário desatualizado, melhoria de performance marginal | **Corrigir nesta rodada, obrigatório** |

**Aprovação unânime exige: S0 = S1 = S2 = S3 = S4 = 0 nesta camada.**

**Único escape:** finding marcado pelo especialista como "falso positivo" com justificativa técnica formal no relatório. Outro especialista da camada (revisor cruzado designado pelo orchestrator) precisa concordar. Se concordar, finding sai da contagem; se discordar, finding permanece e tem que ser corrigido.

---

## 🪜 Camadas em Ordem Rigorosa de Dependência

```
                    ┌──────────────────────────────────────┐
                    │  10. Integrações Externas            │  superfície
                    ├──────────────────────────────────────┤
                    │  9.  Componentes React + UX          │
                    ├──────────────────────────────────────┤
                    │  8.  API Client + State Management   │
                    ├──────────────────────────────────────┤
                    │  7.  TypeScript Types (DTOs)         │
                    ├──────────────────────────────────────┤
                    │  6.  API Contracts (JSON Shapes)     │
                    ├──────────────────────────────────────┤
                    │  5.  Controllers + Routes            │
                    ├──────────────────────────────────────┤
                    │  4.  FormRequests + Authorization    │
                    ├──────────────────────────────────────┤
                    │  3.  Services + Domain Logic         │
                    ├──────────────────────────────────────┤
                    │  2.  Models + BelongsToTenant        │
                    ├──────────────────────────────────────┤
                    │  1.  Schema + Migrations             │  fundação
                    └──────────────────────────────────────┘
```

> **Auditores por camada = especialistas DEDICADOS (variam) + 2 TRANSVERSAIS (sempre): `product-expert` + `governance`.**
> `product-expert` garante aderência ao PRD-KALIBRIUM (regra de negócio).
> `governance` garante conformidade com 5 leis CLAUDE.md + Iron Protocol + padrões obrigatórios.
> `orchestrator` NÃO audita — coordena os auditores em paralelo e consolida findings.

| # | Camada | Escopo | Dedicados | Transversais | **Total** | Gate de Testes |
|---|---|---|---|---|---|---|
| **1** | **Schema + Migrations** | DDL, índices, FK, constraints, `tenant_id` em todas as tabelas, schema dump consistente | `data-expert`, `security-expert` | `product-expert`, `governance` | **4** | `cd backend && php generate_sqlite_schema.php && ./vendor/bin/pest tests/Unit --filter=Migration` |
| **2** | **Models + BelongsToTenant** | Eloquent models, relationships, traits, casts, scopes globais, `BelongsToTenant` em 100% dos models de tenant | `data-expert`, `architecture-expert`, `security-expert` | `product-expert`, `governance` | **5** | `cd backend && ./vendor/bin/pest tests/Unit/Models` |
| **3** | **Services + Domain Logic** | Regras de negócio, lógica de domínio, transactions, side effects, eventos | `architecture-expert`, `qa-expert` | `product-expert`, `governance` | **4** | `cd backend && ./vendor/bin/pest tests/Unit/Services tests/Unit/Domain` |
| **4** | **FormRequests + Authorization** | Validação de input, rules, `authorize()` com lógica real (Spatie/Policy), exists com tenant scope | `security-expert`, `qa-expert` | `product-expert`, `governance` | **4** | `cd backend && ./vendor/bin/pest tests/Feature/Requests` |
| **5** | **Controllers + Routes** | HTTP handlers, paginação obrigatória, eager loading, `tenant_id`/`created_by` no controller, status codes | `architecture-expert`, `qa-expert`, `security-expert` | `product-expert`, `governance` | **5** | `cd backend && ./vendor/bin/pest tests/Feature/Controllers` |
| **6** | **API Contracts (JSON Shapes)** | Estrutura de response (`assertJsonStructure`), códigos HTTP, paginação, error format | `qa-expert`, `integration-expert` | `product-expert`, `governance` | **4** | `cd backend && ./vendor/bin/pest tests/Feature --filter=ApiContract` |
| **7** | **TypeScript Types (DTOs)** | Espelho dos DTOs do backend no frontend, ausência de `any`, sincronia com response | `architecture-expert`, `qa-expert` | `product-expert`, `governance` | **4** | `cd frontend && npm run typecheck` |
| **8** | **API Client + State Management** | Fetch/axios, react-query/zustand, error handling, caching | `architecture-expert`, `qa-expert` | `product-expert`, `governance` | **4** | `cd frontend && npm run test -- --run src/api src/state` |
| **9** | **Componentes React + UX** | UI, formulários, fluxos, acessibilidade, design-system | `ux-designer`, `qa-expert`, `architecture-expert` | `product-expert`, `governance` | **5** | `cd frontend && npm run test:e2e -- --grep="afetados"` |
| **10** | **Integrações Externas** | NFS-e, Boleto/PIX, webhooks, deploy, CI/CD, observabilidade | `integration-expert`, `devops-expert`, `observability-expert` | `product-expert`, `governance` | **5** | Testes específicos + smoke test em staging |

### Gate Final por Camada (obrigatório antes de avançar)

```bash
# Backend completo (camadas 1-6)
cd backend && ./vendor/bin/pest --parallel --processes=16 --no-coverage

# Frontend completo (camadas 7-9)
cd frontend && npm run lint && npm run typecheck && npm run test && npm run build
```

**Suite tem que estar 100% verde.** Não pode haver teste skip/markIncomplete novo.

---

## 🔁 Fluxo Rigoroso por Camada (Para o Orchestrator Seguir)

### Pseudocódigo (modo autônomo, sem perguntar ao usuário no meio)

```
PARA CADA camada N de 1 a 10:

  PUBLICAR início da camada em docs/handoffs/inicio-camada-N-YYYY-MM-DD.md
  CONSULTAR auditorias prévias relevantes em docs/audits/ (reaproveitar
    contexto onde fizer sentido, mas NÃO confiar — re-auditar do zero)

  PARA cada rodada r de 1 a 10:

    ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    PASSO A — Auditoria COMPLETA paralela (sempre do zero)
    ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    orchestrator dispara em paralelo (Agent tool) TODOS os especialistas
    atribuídos à camada N. Cada especialista:
      - Lê seu escopo da camada (arquivos relevantes)
      - Produz lista de findings com severidade S0-S4 (TODAS as severidades)
      - Reporta no formato: { id, severity, file:line, motivo, sugestão }
      - NÃO confia em "já estava bom na rodada anterior" — re-audita
    IMPORTANTE: a partir da rodada 2, é AUDITORIA COMPLETA — não delta.
    Toda correção pode ter introduzido novo problema OU exposto problema
    encoberto. Re-auditar do ZERO.

    ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    PASSO B — Consolidação + Revisão Cruzada
    ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    orchestrator agrega findings, deduplica.
    Para cada finding marcado como "falso positivo" pelo especialista:
      - Designar OUTRO especialista da camada como revisor cruzado
      - Revisor concorda? finding sai da contagem
      - Revisor discorda? finding permanece e vai para correção
    Salva relatório em docs/audits/camada-N-rodada-r-YYYY-MM-DD.md
    com TODAS as severidades + status (open/fixed/false-positive-confirmed).

    ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    PASSO C — Decisão
    ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    SE S0 > 0:
      ESCALAR usuário imediatamente (não mexer em nada)
      AGUARDAR decisão
    SE S0 + S1 + S2 + S3 + S4 == 0 (excluindo falsos positivos confirmados):
      APROVAÇÃO UNÂNIME → ir para PASSO E
    SENÃO:
      continuar PASSO D

    ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    PASSO D — Correção autônoma (todos os findings)
    ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    Ordenar findings por severidade (S1 → S2 → S3 → S4) — corrigir todos.
    Para cada finding:
      invocar builder com prompt: "/fix <finding>"
      builder:
        - Corrige seguindo as 5 leis e formato Harness 6+1
        - Cria/atualiza teste de regressão para o finding
        - Roda gate da camada (--filter ou --testsuite específico)
      SE teste verde: marcar finding como corrigido
      SE teste vermelho:
        builder analisa e corrige novamente (tentativa 2)
        SE ainda vermelho na tentativa 3:
          ESCALAR usuário (caso (b) do princípio 5)
          Salvar em docs/audits/IMPASSE-finding-X-rodada-r.md

    Após corrigir todos os findings da rodada:
      Voltar para PASSO A (rodada r+1) — RE-AUDITAR DO ZERO.

    ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    PASSO E — Gate Final da camada
    ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    Rodar SUITE COMPLETA relevante (backend e/ou frontend):
      Backend: cd backend && ./vendor/bin/pest --parallel --processes=16 --no-coverage
      Frontend: cd frontend && npm run lint && npm run typecheck && npm run test && npm run build
    SE 100% verde (zero teste falhando, zero skip novo, zero markIncomplete novo):
      Salvar APROVACAO em docs/audits/camada-N-APROVACAO-YYYY-MM-DD.md
      Salvar handoff em docs/handoffs/fim-camada-N-YYYY-MM-DD.md
      Commit: "stabilize(layer-N): camada N aprovada por unanimidade após r rodadas"
      AVANÇAR para camada N+1
    SENÃO:
      Voltar para PASSO A (rodada r+1) — algo na correção quebrou suite
      LOG do que quebrou em docs/audits/regressao-camada-N-rodada-r.md

  FIM DO LOOP DE RODADAS

  SE rodada 10 atingida E findings ainda > 0:
    Salvar relatório bloqueio em docs/audits/BLOQUEIO-camada-N-YYYY-MM-DD.md
    ESCALAR usuário (caso (c) do princípio 5) com:
      - Findings remanescentes (todos, com severidade)
      - O que foi tentado em cada rodada (resumo do log)
      - Recomendação técnica do orchestrator
      - Opções (a/b/c) com tradeoffs
    AGUARDAR decisão antes de prosseguir
    NÃO AVANÇAR para camada N+1 sem decisão explícita

FIM DO LOOP DE CAMADAS

PUBLICAR docs/handoffs/ESTABILIZACAO-COMPLETA-YYYY-MM-DD.md
COMMIT FINAL: "stabilize: estabilização completa, todas as 10 camadas aprovadas"
```

### Resumo das 3 únicas situações que interrompem o modo autônomo

| Caso | Quando | O que fazer |
|---|---|---|
| (a) Finding S0 | Detectado em qualquer rodada | Parar tudo, escalar imediatamente, aguardar decisão |
| (b) Impasse técnico | 3 tentativas falhas no MESMO finding | Escalar finding específico, aguardar decisão (não bloqueia outros findings) |
| (c) Rodada 10 esgotada | Findings ainda > 0 após 10 rodadas | Escalar relatório consolidado da camada, aguardar decisão |

**Em qualquer outra situação, o orchestrator decide e executa autonomamente. NÃO consulta o usuário no meio do processo.**

---

## ✅ Critérios de Aprovação por Especialista

### `data-expert`
- 0 N+1 query detectado nos endpoints da camada
- Todas as FK têm índice
- `BelongsToTenant` aplicado em 100% dos models de tenant
- Migrations idempotentes (`hasTable`/`hasColumn` guards) — Lei H3
- Schema dump (`backend/database/schema/sqlite-schema.sql`) consistente

### `security-expert`
- 0 vazamento cross-tenant (testes 404 confirmam) — Lei H1/H2
- 0 SQL raw com interpolação (sempre bindings)
- 0 `FormRequest::authorize()` com `return true` sem lógica
- 0 secrets hardcoded (busca por `password|secret|api_key|token` literal)
- 0 input não validado em controller
- LGPD: dados sensíveis não logados

### `qa-expert`
- Cobertura adaptativa: features 8+, CRUDs 4-5, bugs regressão+afetados
- 5 cenários (quando aplicável): sucesso, 422 validação, 404 cross-tenant, 403 permissão, edge case
- 0 `assertTrue(true)`, `assertEquals(true, true)`, `markIncomplete`, `skip`, comentário de teste
- `assertJsonStructure` obrigatório em endpoints
- Teste cross-tenant existe e passa

### `architecture-expert`
- 0 violação de camada (controller chamando model direto, model fazendo HTTP)
- 0 código duplicado >5 linhas (DRY)
- 0 dependência circular
- Services com responsabilidade única
- Eager loading consistente (`->with([...])`)

### `integration-expert` (camada 10)
- NFS-e: emissão, cancelamento, consulta funcionam para municípios suportados
- Boleto/PIX: registro, baixa, conciliação funcionam
- Webhooks: idempotência, retry, error handling, timeout
- Documentação de setup em `deploy/SETUP-*.md` atualizada

### `devops-expert` (camada 10)
- CI passa em 100% das branches relevantes (sharding 4-way)
- Deploy reproduzível e documentado em `deploy/DEPLOY.md`
- Secrets em variáveis de ambiente, não no código
- Health check funcional em produção

### `observability-expert` (camada 10)
- Logs estruturados em pontos críticos (controllers, integrações, jobs)
- Erros em produção captáveis (`storage/logs/`, Sentry/Bugsnag se houver)
- Métricas básicas (latência endpoint, error rate)

### `ux-designer` (camada 9)
- 0 formulário sem validação inline
- Mensagens de erro úteis (linguagem de negócio, não stack trace)
- Acessibilidade básica (labels, ARIA, contraste)
- Feedback visual em ações (loading, success, error)
- Conformidade com `docs/design-system/`

### `product-expert` (TRANSVERSAL — entra em TODA camada)
- Aderência a `docs/PRD-KALIBRIUM.md` v3.2+ no escopo da camada
- Gaps PRD vs implementação documentados em `docs/audits/gap-camada-N-rodada-r.md`
- Terminologia consistente com domínio do negócio (OS, Calibração, Financeiro, etc)
- Regras de negócio respeitadas (não basta código tecnicamente correto)

### `governance` (TRANSVERSAL — entra em TODA camada)
- 5 leis CLAUDE.md respeitadas no escopo da camada
- Padrões obrigatórios Controller/FormRequest respeitados (quando aplicável)
- Iron Protocol H1/H2/H3/H7/H8 respeitados
- Sem proibições absolutas violadas (`--no-verify`, mascarar teste, `markIncomplete`, `assertTrue(true)`, comentar código pra "desativar", etc)
- Sem TODO/FIXME deixados na camada
- Sem código morto ou função criada e não usada

---

## 📁 Documentação Gerada por Camada

```
docs/
├── plans/
│   └── estabilizacao-bottom-up.md       (este documento)
├── audits/
│   ├── camada-1-rodada-1-2026-04-17.md
│   ├── camada-1-rodada-2-2026-04-17.md
│   ├── ...
│   ├── camada-1-APROVACAO-2026-04-17.md  (último — aprovação unânime)
│   └── BLOQUEIO-camada-N-YYYY-MM-DD.md   (se rodada 10 esgotar)
├── handoffs/
│   ├── inicio-camada-N-YYYY-MM-DD.md
│   ├── fim-camada-N-YYYY-MM-DD.md
│   └── ESTABILIZACAO-COMPLETA-YYYY-MM-DD.md
```

---

## 🚨 Escalação para Usuário (somente nestes 3 casos)

**Modo padrão é AUTÔNOMO.** Não consultar usuário no meio do processo. Disparar escalação SOMENTE SE:

1. **Caso (a) — Finding S0 detectado.** Sistêmico, exige decisão arquitetural ou de produto que orchestrator não pode tomar (ex: PRD ambíguo, conflito entre regulamentações, decisão de breaking change).
2. **Caso (b) — Impasse técnico.** 3 tentativas falhas do builder no mesmo finding (mesmo após especialista refinar diagnóstico). Escalar finding específico — não bloqueia outros findings da rodada.
3. **Caso (c) — Rodada 10 esgotada.** Após 10 rodadas completas (auditoria + correção + re-auditoria), ainda existem findings não resolvidos. Escalar relatório consolidado.

**NÃO escalar por:**
- Conflito entre especialistas (orchestrator decide com base em hierarquia: security > governance > qa > architecture > demais; tenant safety vence qualquer outra preocupação)
- Suite quebrou após correção (orchestrator volta para auditoria — é nova rodada, não escalação)
- Especialista relutante em aprovar (orchestrator pede justificativa; se justificativa for inválida, ignora)
- Cansaço do processo (não existe — modo autônomo)

**Formato de escalação:**
```markdown
## 🚨 ESCALAÇÃO — Camada N (Rodada r)

**Bloqueio:** <descrição em 1 frase>

**O que foi tentado:**
- <ação 1, resultado>
- <ação 2, resultado>
- ...

**Findings remanescentes:**
- <id, severidade, arquivo:linha, motivo>

**Opções:**
- (a) <opção técnica A com tradeoff>
- (b) <opção técnica B com tradeoff>
- (c) <pular finding e marcar como S3 — backlog>

**Recomendação:** <opção e por quê>

Aguardando decisão.
```

---

## 🛠️ Comandos por Fase

| Fase | Comando |
|---|---|
| Validação inicial | `/where-am-i` |
| Início de camada | Invocar `orchestrator` com prompt: `"Iniciar estabilização camada N seguindo docs/plans/estabilizacao-bottom-up.md"` |
| Bug fix individual dentro de camada | `/fix <descrição>` |
| Verificar mudança | `/verify` |
| Auditar testes | `/test-audit` |
| Auditoria de segurança | `/security-review` |
| Code review interno | `/review-pr` |
| Auditoria funcional | `/functional-review` |
| Status macro | `/project-status` |
| Status detalhado | `/where-am-i` |
| Fechar camada (handoff) | `/checkpoint` |
| Próxima sessão (restaurar) | `/resume` |
| Saúde do contexto | `/context-check` |

---

## 📊 Estimativa de Tamanho (referência, não promessa)

| Camada | Escopo | Estimativa | Risco se quebrar |
|---|---|---|---|
| 1 | Schemas (~50 tabelas) | 1-2 dias | 🔴 Catastrófico (vazamento tenant) |
| 2 | Models (~50) | 2-3 dias | 🔴 Tudo desmorona |
| 3 | Services | 3-5 dias | 🟠 Regras de negócio erradas |
| 4 | FormRequests | 2-3 dias | 🔴 Cross-tenant + permissões |
| 5 | Controllers | 3-5 dias | 🟠 API quebrada |
| 6 | API contracts | 1-2 dias | 🟠 Frontend quebra |
| 7 | TS types | 1-2 dias | 🟡 Build falha |
| 8 | API client | 2-3 dias | 🟡 Dados não chegam |
| 9 | Componentes + UX | 5-10 dias | 🟡 Usuário sofre |
| 10 | Integrações | 5-7 dias | 🔴 Receita afetada (NFS-e, Boleto) |
| **Total** | | **25-42 dias** | |

---

## 🏁 Como Iniciar

```
1. Validar harness:        /where-am-i  ✅ (já feito 2026-04-17)
2. Aprovar este playbook:  usuário lê este documento e confirma versão 1.1
3. Iniciar camada 1:       invocar orchestrator com:

   "Inicie a estabilização AUTÔNOMA da camada 1 (Schema + Migrations)
    seguindo docs/plans/estabilizacao-bottom-up.md (versão 1.2).

    Regras inegociáveis:
    - Auditoria COMPLETA paralela com 4 auditores:
        Dedicados:    data-expert, security-expert
        Transversais: product-expert, governance
    - Re-auditoria do ZERO após qualquer correção
    - Aprovação unânime exige: 0 findings de QUALQUER severidade (S0-S4)
    - Loop até 10 rodadas
    - Modo autônomo — não me consulte no meio
    - Escalar SÓ em 3 casos: S0, 3 tentativas falhas no mesmo finding,
      ou rodada 10 esgotada
    - Aproveitar contexto de docs/audits/audit-models-schema-2026-04-10.md
      e docs/audits/audit-security-2026-04-10.md como ponto de partida,
      mas RE-AUDITAR do zero (não confiar)
    - Gerar relatório por rodada em docs/audits/camada-1-rodada-N-YYYY-MM-DD.md
    - Documentar handoffs em docs/handoffs/
    - Commit por rodada: 'stabilize(layer-1): rodada N - X findings corrigidos'
    - Commit final da camada: 'stabilize(layer-1): camada aprovada por unanimidade após N rodadas'

    Gate Final obrigatório antes de avançar:
    cd backend && ./vendor/bin/pest --parallel --processes=16 --no-coverage
    Tem que estar 100% verde."
```

### Auditorias prévias relevantes (consultar como ponto de partida)

Existem auditorias antigas em `docs/audits/` que podem acelerar a primeira rodada — orchestrator deve **lê-las como contexto, mas NÃO confiar** (re-auditar do zero). Lista por camada:

| Camada | Auditorias prévias relevantes |
|---|---|
| 1 (Schema) | `audit-models-schema-2026-04-10.md` |
| 2 (Models) | `audit-models-schema-2026-04-10.md` |
| 4-5 (Auth/Controllers) | `audit-controllers-2026-04-10.md`, `AUDIT_WORKORDER_CONTROLLERS.md` |
| 4-5 (Security) | `audit-security-2026-04-10.md` |
| 6-9 (Frontend) | `audit-frontend-2026-04-10.md`, `QUOTE_FRONTEND_ANALYSIS.md`, `AUDIT_WORK_ORDER_UI.md` |
| Transversal | `RELATORIO-AUDITORIA-SISTEMA.md`, `WORK_ORDER_AUDIT.md`, `QUOTE_INTEGRATION_AUDIT.md` |

---

## 📜 Histórico

- **2026-04-17 v1.2** — Padronização de auditores (Roldão):
  - `product-expert` e `governance` agora explicitamente TRANSVERSAIS — entram em TODA camada (sem exceção)
  - Tabela de camadas reorganizada com colunas separadas para Dedicados/Transversais/Total
  - Total de auditores por camada: 4 ou 5 (todos com transversais incluídos)
  - Critérios de aprovação dos transversais expandidos (regras de negócio + governança)
- **2026-04-17 v1.1** — Endurecimento das regras pelo Roldão:
  - Aprovação unânime agora exige **0 findings de QUALQUER severidade** (S0-S4), não apenas S1/S2
  - Re-auditoria COMPLETA do zero após qualquer correção (não delta)
  - Modo autônomo: orchestrator NÃO consulta usuário no meio do processo
  - Escalação só em 3 casos específicos (S0, impasse técnico, rodada 10 esgotada)
  - Adicionada regra de revisão cruzada para falsos positivos
- **2026-04-17 v1.0** — Criação do playbook (Roldão + Claude). Estabilização bottom-up com 10 camadas por dependência. Loop de 10 rodadas por camada, aprovação unânime obrigatória.
