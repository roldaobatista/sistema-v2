# AGENTS.md — Kalibrium ERP

> **Fonte de verdade única** lida por qualquer agente de IA trabalhando neste repositório (Claude Code, Codex CLI, Aider, Cursor, etc.). `CLAUDE.md` é um wrapper fino que aponta para este arquivo + extensões Claude-específicas.

> **Missão atual:** estabilizar o sistema. Ele tem falhas. Cada mudança = bug fix com teste de regressão. **Não adicionar feature sem pedido explícito.**

---

## ⚔️ As 5 Leis Invioláveis (P-1)

### 1. Evidência antes de afirmação
**Proibido** dizer "pronto", "funcionando", "testes passando", "validado" sem mostrar o output do comando rodado **no mesmo turno**.

### 2. Causa raiz, nunca sintoma
Teste falhou = problema no SISTEMA. Corrige o código, **nunca** mascara o teste (skip, markIncomplete, `assertTrue(true)`, assertion relaxada). Erro de ambiente = corrige o ambiente, **nunca** usa `--no-verify`, `--ignore-platform-reqs`, `--skip-*`.

### 3. Completude end-to-end
Toda mudança percorre a cadeia inteira: **migration → model → service → controller → rota → tipo TypeScript → API client → componente → teste**. Elo faltando = criar. Não deixar TODO. Não comentar código pra "desativar".

### 4. Tenant safety absoluto
- Tenant ID **sempre** `$request->user()->current_tenant_id`. Jamais do body.
- Toda query/persistência respeita `BelongsToTenant`. `withoutGlobalScope` exige justificativa explícita por escrito (comentário `LEI 4 JUSTIFICATIVA:` com a razão).
- `expenses.created_by`, `schedules.technician_id` (não `user_id`).
- Status sempre em inglês lowercase: `'paid'`, `'pending'`, `'partial'`.

### 5. Sequenciamento + preservação
- **Sequenciamento:** etapa N+1 só inicia com etapa N 100% completa, testes verdes, evidência mostrada.
- **Preservação:** ao reescrever, listar comportamentos antes; conferir item por item depois. "Simplificar" não justifica remover comportamento.
- **Cascata > 5 arquivos fora do escopo original:** PARAR e reportar antes de continuar.

---

## ⚙️ Modo Operacional — Harness (7 passos)

```
1. entender → 2. localizar → 3. propor (mínimo + correto) → 4. implementar
→ 5. verificar → 6. corrigir falhas → 7. evidenciar
```

### Formato obrigatório de toda resposta que altere código (6+1 itens)

1. **Resumo do problema** — sintoma + causa raiz (1-2 frases)
2. **Arquivos alterados** — lista com `path:LN`
3. **Motivo técnico** — POR QUÊ (o diff mostra o quê)
4. **Testes executados** — comando exato copiável
5. **Resultado dos testes** — output real, contagem passed/failed/tempo (proibido inventar)
6. **Riscos remanescentes** — não-coberto, side effects, atenção
7. *(obrigatório se: migration, contrato de API, rota pública, deploy, remoção de feature, risco alto)* **Como desfazer**

### Pirâmide de testes (escalada)
**Específico → grupo → testsuite → suite completa.** Nunca rodar suite completa no meio da task. Se falhar, corrigir ali — não escalar.

---

## 🔒 Fechamento de Camada/Wave/Etapa

**Suite verde NÃO é fechamento.** Antes de declarar qualquer Camada/Wave/etapa "fechada", "pronta" ou "concluída":

1. **Re-auditoria obrigatória** por múltiplos especialistas pós-correção (data, security, governance, qa, product conforme escopo).
2. **Zero findings remanescentes** em todas as severidades (S1..S4). Único veredito que permite declarar camada concluída.
3. **Evidenciar** o output da re-auditoria na resposta (não basta afirmar).

Sem re-auditoria = etapa **em progresso**, não fechada.

### Critério absoluto (binário)

- **FECHADA:** zero findings S1..S4. Não existe "quase fechada", "fechada com ressalva" ou CONDICIONAL.
- **REABERTA:** qualquer finding remanescente. Voltar ao builder/fix, re-rodar auditoria até zero.

Dívida técnica aceita como limitação permanente deve ser documentada em `docs/TECHNICAL-DECISIONS.md` **antes** da re-auditoria (refletida nas instruções dos auditores para não reaparecer). Documentar **depois** da re-auditoria para forçar fechamento é proibido.

### Regra anti-bias para prompt de re-auditoria

**PROIBIDO no prompt do agente auditor:**
- Narrativa do que foi feito ("renomeamos X", "Wave N resolveu Y").
- Conclusões antecipadas ("confirme que está OK", "aprove se Y").
- Resumo da correção ou decisões tomadas.
- Lista de findings originais, commit range ou arquivos tocados (bias disfarçado — o auditor deve descobrir o estado atual cegamente).

**OBRIGATÓRIO no prompt:**
- Nome da camada/escopo textual.
- Perímetro funcional (domínio, entidades — nunca arquivos de commit).
- Diretórios sugeridos gerais.
- Checklist do próprio agent file/spec do expert.
- Proibições explícitas (não ler `docs/audits/`, `docs/handoffs/`, `docs/plans/`; não rodar `git log/diff/show/blame`).
- Instrução: "Sua função é INVESTIGAR, não confirmar. Proibido aprovar/validar — apenas reportar achados."

Comparação com baseline = operação mecânica do coordenador, **fora do agente** (set-difference contra `docs/audits/findings-<camada>.md`).

---

## 📚 Documentação — Hierarquia de Verdade

1. **Código-fonte** — sempre vence. Grep/Glob/Read antes de afirmar que existe ou não.
2. **`docs/PRD-KALIBRIUM.md`** — RFs, ACs, gaps (v3.2+, sincronizado contra código em 2026-04-10).
3. **`docs/TECHNICAL-DECISIONS.md`** — decisões arquiteturais.
4. **`docs/audits/RELATORIO-AUDITORIA-SISTEMA.md`** — Deep Audit OS/Calibração/Financeiro.

**Proibido ler `docs/.archive/`** — documentação superada, gera alucinação.

- **Documentação ativa:** `docs/architecture/`, `docs/compliance/`, `docs/operacional/`, `docs/design-system/`, `docs/plans/`
- **Deploy:** `deploy/DEPLOY.md`, `deploy/SETUP-NFSE.md`, `deploy/SETUP-BOLETO-PIX.md`

---

## 🧱 Stack

- **Backend:** Laravel 13 (PHP 8.3+) em `backend/` — Sanctum, Spatie Permission, Horizon (filas Redis), Reverb (websockets), Scramble (OpenAPI).
- **Frontend:** React 19 + TypeScript 5.9 + Vite 8 em `frontend/` — React Router v7, TailwindCSS 4 + Radix UI + shadcn/ui, Zustand, Axios + TanStack Query, Vitest + Playwright.
- **DB:** MySQL 8 (produção), SQLite in-memory (testes via schema dump).
- **Observabilidade:** Sentry + OpenTelemetry SDK + Telescope/Pulse (dev).
- **Multi-tenant:** trait `BelongsToTenant` + middleware `EnsureTenantScope`. Tenant ID sempre em `$request->user()->current_tenant_id` (NUNCA `company_id`).
- **Infra/CI:** Docker Compose + Nginx + Let's Encrypt; GitHub Actions (`.github/workflows/`: `ci.yml`, `deploy.yml`, `security.yml`, `dast.yml`, `nightly.yml`, `performance.yml`).

---

## 🧪 Como Rodar Testes (operacional)

```bash
# Comando principal — 8720 cases em <5 minutos
cd backend && ./vendor/bin/pest --parallel --processes=16 --no-coverage

# Após criar migration, regenerar schema dump
cd backend && php generate_sqlite_schema.php
```

- DB de testes: SQLite in-memory com schema dump (`backend/database/schema/sqlite-schema.sql`)
- Guia completo: `backend/TESTING_GUIDE.md`
- Padrão de teste: `backend/tests/README.md`
- Testsuite `Default` (CI) = `Unit + Feature + Smoke + Arch`. Também `Critical` e `E2E` standalone. Não alterar `defaultTestSuite` em `phpunit.xml` sem atualizar CI + composer scripts em cascata.

### Composer scripts úteis (backend)

| Script | Ação |
|---|---|
| `composer test-fast` | Pest paralelo 16 processos sem cobertura |
| `composer test-dirty` | Só testes afetados pelo diff git — ideal dev diário |
| `composer test-coverage` | Paralelo + cobertura mínima 80% |
| `composer test-profile` | Identifica testes mais lentos |
| `composer analyse` | PHPStan + Larastan com baseline (`phpstan-baseline.neon`) |
| `composer dev` | `artisan serve` + queue + pail + vite em paralelo |

### Quality gates antes de commitar

```bash
# Backend
cd backend && ./vendor/bin/pint                        # formata (Laravel preset)
cd backend && composer analyse                         # static analysis
cd backend && ./vendor/bin/pest --dirty --parallel --no-coverage

# Frontend
cd frontend && npm run typecheck
cd frontend && npm run lint
cd frontend && npm run test
```

### Padrão obrigatório de testes (adaptativo)
- Features com lógica = 8+ testes/controller
- CRUDs simples = 4-5 testes (sucesso + 422 + cross-tenant)
- Bug fixes = teste de regressão + afetados
- < 4 testes = SEMPRE insuficiente
- **5 cenários quando aplicável:** sucesso, 422 validação, 404 cross-tenant, 403 permissão, edge cases
- `assertJsonStructure()` obrigatório — não só status code
- **Teste cross-tenant é OBRIGATÓRIO**

---

## 🔒 Padrões obrigatórios de Controllers/FormRequests

- `FormRequest::authorize()` com `return true` sem lógica = **PROIBIDO**. Verificar permissão real (`$this->user()->can(...)` ou Policy).
- Endpoints `index` **DEVEM** paginar (`->paginate(15)`). Proibido `Model::all()` ou `->get()` sem limite.
- Eager loading obrigatório (`->with([...])`) para qualquer relationship usada no response.
- `tenant_id` e `created_by` atribuídos no controller. **PROIBIDO** expor como campos do FormRequest.
- Relationship validada por `exists:table,id` deve considerar `tenant_id`.

---

## 🚫 Proibições Absolutas

- `--no-verify`, `--ignore-platform-reqs`, `--skip-*`
- `git reset --hard`, `git push --force`, `rm -rf`, `drop table` sem confirmação explícita
- Alterar migrations já mergeadas (criar nova com `hasTable`/`hasColumn` guards)
- Mascarar teste de qualquer forma (ver Lei 2)
- Deixar TODO/FIXME, código comentado pra "desativar", código morto
- Remover ou diminuir funcionalidades existentes
- Vazar dados entre tenants
- Interpolar variáveis em queries raw (sempre bindings)

**Exceção Windows:** `pcntl`, `posix`, `inotify` são Linux-only. Permitido `--ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix` SOMENTE para essas 3.

---

## ✅ Critérios de Aceite (checklist antes de declarar conclusão)

- [ ] Código consistente com arquitetura atual
- [ ] Sem regressão visível (rodar testes afetados)
- [ ] Testes relevantes verdes com evidência de execução
- [ ] Resposta no formato Harness (6+1)
- [ ] Tenant safety verificado
- [ ] Cadeia end-to-end completa (migration→...→frontend→teste)

---

## 🤝 Equivalências Claude ↔ Codex ↔ outros agentes

Este projeto foi desenhado originalmente em torno do Claude Code (com sub-agents, slash commands, skills, hooks). Codex CLI e outros agentes não têm esses conceitos, mas **todas as capacidades continuam acessíveis** como arquivos-lore legíveis por qualquer agente:

| Capacidade Claude | Arquivo-lore (lê qualquer agente) | Equivalente Codex/genérico |
|---|---|---|
| `Agent(security-expert)` | `.claude/agents/security-expert.md` | Ler o checklist e executar como humano (grep/read + report estruturado) |
| `Agent(qa-expert)` | `.claude/agents/qa-expert.md` | Idem |
| `Agent(data-expert)` | `.claude/agents/data-expert.md` | Idem |
| `Agent(governance)` | `.claude/agents/governance.md` | Idem |
| `Agent(builder)` | `.claude/agents/builder.md` | Idem — é o modo default do Codex |
| `Agent(orchestrator)` | `.claude/agents/orchestrator.md` | Executa os 4 auditores em sequência e consolida |
| `/fix <bug>` | `.claude/commands/fix.md` | Seguir o passo-a-passo do arquivo manualmente |
| `/verify` | `.claude/commands/verify.md` | Rodar os comandos descritos no arquivo |
| `/reaudit <camada>` | `.claude/commands/reaudit.md` | Invocar 4 experts em sequência com prompt neutro (§Regra anti-bias acima) |
| `/test-audit`, `/audit-spec`, `/functional-review`, `/security-review`, `/review-pr` | `.claude/commands/*.md` | Executar manualmente o roteiro descrito |
| `/where-am-i`, `/project-status`, `/checkpoint`, `/resume` | `.claude/commands/*.md` | Ler `docs/handoffs/latest.md` + `git log -20` |
| skill `simplify` | `.claude/skills/simplify.md` | Ler e aplicar manualmente |
| skill `audit-prompt` | `.claude/skills/audit-prompt.md` | **OBRIGATÓRIO** ler antes de invocar qualquer auditor — define regra anti-bias |
| `TaskCreate` | N/A | Manter TODO list em bloco markdown no fim da resposta, ou atualizar `docs/handoffs/latest.md` |
| hook SessionStart (CLAUDE.md) | `.claude/settings.json` → `hooks.SessionStart` | Codex deve ler este AGENTS.md inteiro no início da sessão — a REGRA DE FECHAMENTO está no §Fechamento de Camada acima |
| MCP (context-mode, playwright, github) | configurados no Claude | Codex usa `curl`/`gh`/`playwright` via bash diretamente |

### Regras operacionais Codex

1. **Ler `AGENTS.md` + `docs/handoffs/latest.md` no início de cada sessão.** Não pular. É o equivalente ao hook `SessionStart` do Claude.
2. **Antes de auditar algo**, ler `.claude/skills/audit-prompt.md` e `.claude/agents/<expert-relevante>.md`.
3. **Responder no formato 6+1** (§Modo Operacional acima) para qualquer mudança de código.
4. **Nunca declarar Camada/Wave fechada** sem re-auditoria multi-expert com zero findings (§Fechamento).
5. **Checkpoint de sessão:** atualizar `docs/handoffs/latest.md` ao final de cada batch significativo (equivalente ao `/checkpoint`).

### Handoff entre agentes (Claude ↔ Codex ↔ humano)

Para qualquer agente pegar o trabalho do outro:
1. Ler `docs/handoffs/latest.md` — fonte canônica de onde paramos.
2. Rodar `git log --oneline -20` — últimos commits.
3. Ler `AGENTS.md` (este arquivo) — regras do projeto.
4. Ler `docs/audits/findings-<camada-ativa>.md` + `docs/audits/reaudit-<camada>-<data>.md` — baseline de qualidade em aberto.
5. Continuar do ponto descrito em `latest.md`.

---

## 🤖 Modo Autônomo — `/camada-auto`

Modo de operação em que o usuário aprova **uma vez** o início de uma camada e o sistema (orchestrator + experts + builder) executa autonomamente o loop **auditar → corrigir tudo → reauditar** até **zero findings** ou **bloqueio real**.

### Regras invioláveis durante o loop

**PROIBIDO:**
- Mascarar testes (skip, `markIncomplete`, `assertTrue(true)`, assertions relaxadas) — Lei 2.
- Aceitar S3/S4 como dívida técnica documentada em `TECHNICAL-DECISIONS.md` durante o loop. Dívida só pode ser registrada **antes** do loop começar.
- Remover ou diminuir funcionalidade — Lei 3 + §Proibições Absolutas.
- Mudar escopo da camada silenciosamente.
- Escalar testes antes de tentar nível menor — §Pirâmide de testes.
- Usar `--no-verify`, `--skip-*`, `--ignore-*` (fora das 3 exceções Windows).

**OBRIGATÓRIO:**
- Veredito `FECHADA` só com 0 findings S1..S4.
- Cada rodada = commit(s) atômico(s) + atualização de `docs/handoffs/auto-<camada>-r<N>.md`.
- Re-auditoria sempre com prompt neutro (skill `audit-prompt`, §Regra anti-bias).
- Pre-commit hook ativo (`.githooks/pre-commit`).

### Bloqueios reais (param o loop antes das 10 rodadas)

| Código | Gatilho | Ação |
|---|---|---|
| **B1** | Decisão produto/arquitetura que builder não pode tomar sozinho | Escrever `docs/blocks/<camada>-B1-<slug>.md` com 2-3 opções e parar |
| **B2** | Migration destrutiva proposta (DROP, truncate, data loss irreversível) | Parar e perguntar — nunca executar sem OK |
| **B3** | Remoção ou redução de funcionalidade | Parar, apresentar proposta |
| **B4** | Cascata > 50 arquivos fora do escopo inicial | Parar e relatar explosão de escopo |
| **B5** | Conflict entre 2+ experts em 2 rodadas seguidas sem convergência | Parar e apresentar impasse |
| **B6** | Infra quebrada que builder não resolve em 2 tentativas | Parar com logs completos |

### Limite de rodadas

**Max 10 rodadas** por `/camada-auto`. Se na rodada 10 ainda houver findings, parar e trazer usuário — sinal de oscilação, escopo mal definido, ou limite do builder.

### Códigos de saída

- `0` — sucesso, zero findings
- `1` — bloqueio real (B1..B6)
- `2` — esgotou 10 rodadas sem zerar

### Invocação

- **Claude Code:** `/camada-auto "<nome>"` — ver `.claude/commands/camada-auto.md`.
- **Codex / outro agente:** ler `.claude/commands/camada-auto.md` e executar o roteiro manualmente. O arquivo é auto-contido e agnóstico à ferramenta.

**Decisão do usuário no contrato:** aprovou iniciar a camada = aprovou o ciclo completo. Orchestrator só volta a consultar em bloqueio real.

---

## 🛡️ Enforcement mecânico (pre-commit hook)

O repositório tem um pre-commit hook committed em `.githooks/pre-commit` que enforça Leis 1 e 2 **independente do agente** (Claude, Codex, Aider, humano). Funciona pra qualquer um que rodar `git commit`.

### Ativar (uma vez por clone)

```bash
git config core.hooksPath .githooks
```

### O que o hook faz

- Detecta arquivos staged.
- Se tocou `backend/`: `pint --test` → `composer analyse` → `pest --dirty --parallel`.
- Se tocou `frontend/`: `npm run typecheck` → `npm run lint`.
- Se tocou só `docs/` ou `.githooks/`: pula (passa direto).
- **Falhou em qualquer gate = commit bloqueado.** Mensagem em PT-BR explica o gate e como corrigir.

### Regra estrita

- **`--no-verify` é proibido** (já no §Proibições Absolutas). Bypassar o hook é violação da Lei 2.
- Hook sujo = código sujo. Corrija a causa raiz.
- Documentação completa: [`.githooks/README.md`](.githooks/README.md).

### Camadas de enforcement do projeto

1. **Soft (AGENTS.md + agent files):** contrato textual — agente lê e segue na medida do contexto.
2. **Médio (pre-commit hook):** mecânico, bloqueia commit local. Agnóstico ao agente.
3. **Duro (GitHub Actions):** CI roda em cada push; PR não merge se falhar.

Só a camada 1 pode ser ignorada por um agente distraído. **2 e 3 são invioláveis.**

---

## 📜 Complementos legados (`.agent/`)

Existem arquivos pré-AGENTS.md que preservam nomenclatura histórica do Harness/Iron Protocol. **Não substituem este arquivo** — mas podem ser úteis pra contexto em commits antigos ou se um agent file citar H1..H8:

- `.agent/rules/iron-protocol.md` — 5 Leis na forma original + boot sequence histórica.
- `.agent/rules/harness-engineering.md` — H1..H8 (equivalente às 5 Leis + Harness 7 passos + formato 6+1 deste arquivo).
- `.agent/skills/end-to-end-completeness/SKILL.md` — skill legada que cobre Lei 3.
- `.agent/skills/iron-protocol-bootstrap/SKILL.md` — bootstrap histórico.

**Mapa H → Lei/seção:** H1+H7 ≡ Lei 1 · H2+H8 ≡ Lei 2 · H3 ≡ Harness 7 passos · H4 ≡ Lei 5 · H5 ≡ formato 6+1 · H6 ≡ pirâmide de testes. Lei 3 (completude e2e) e Lei 4 (tenant safety) só existem aqui.

Em conflito entre `.agent/rules/*` e este `AGENTS.md`: **este arquivo vence.**

---

## 📂 Estrutura de Diretórios Relevantes

```
sistema/
├── AGENTS.md                     # ← este arquivo (source-of-truth dual)
├── CLAUDE.md                     # wrapper Claude-específico → aponta pra AGENTS.md
├── backend/                      # Laravel 13 — código PHP
├── frontend/                     # React 19 + Vite
├── deploy/                       # scripts e docs de deploy
├── docs/
│   ├── PRD-KALIBRIUM.md          # escopo / RFs / ACs
│   ├── TECHNICAL-DECISIONS.md    # ADRs aceitos
│   ├── architecture/             # diagramas, fluxos
│   ├── audits/                   # findings e re-auditorias
│   ├── handoffs/                 # checkpoints de sessão
│   │   └── latest.md             # ← estado atual sempre
│   ├── compliance/, operacional/, design-system/, plans/
│   └── .archive/                 # PROIBIDO ler — docs superadas
├── .claude/                      # Claude-specific (agents, skills, commands, hooks, MCP)
│   ├── agents/*.md               # checklists dos auditores (legíveis por qualquer agente)
│   ├── commands/*.md             # roteiros dos slash commands (legíveis por qualquer agente)
│   ├── skills/*.md               # skills reutilizáveis (legíveis por qualquer agente)
│   └── settings.json             # hooks (Claude-only)
└── .agent/rules/                 # complementos legados (harness-engineering, iron-protocol)
```

---

**Resumo em uma frase:** qualquer agente (Claude Code, Codex CLI, humano) que ler este arquivo + `docs/handoffs/latest.md` tem contrato completo pra trabalhar no Kalibrium ERP. Extensões Claude-específicas estão em `.claude/` mas sua **lógica** (checklists, roteiros) é legível por qualquer ferramenta.
