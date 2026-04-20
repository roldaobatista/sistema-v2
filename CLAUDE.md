# CLAUDE.md — Kalibrium ERP (wrapper Claude-específico)

> **Source-of-truth: [`AGENTS.md`](./AGENTS.md)** — este projeto suporta Claude Code **e** Codex CLI (+ outros agentes). As regras do projeto, 5 Leis, Harness 7 passos, formato 6+1, proibições, quality gates e critério de fechamento estão em `AGENTS.md`. **Leia primeiro.**
>
> Este arquivo só documenta as capacidades **exclusivas do Claude Code** (sub-agents, slash commands, skills, hooks, MCP).

---

## 🎯 Sub-Agentes Disponíveis (`.claude/agents/`)

Use a `Agent` tool para invocar. Para auditoria multi-perspectiva, rodar em paralelo.

| Agente | Quando usar |
|---|---|
| `orchestrator` | Coordenar auditoria multi-especialista; tarefa que envolve 3+ domínios |
| `builder` | Implementar correção/feature após análise dos especialistas |
| `architecture-expert` | Acoplamento, débito técnico, violação de camadas |
| `data-expert` | Schema, índices, N+1, integridade referencial, queries |
| `devops-expert` | CI/CD, deploy, infra, secrets, pipelines |
| `integration-expert` | NFS-e, Boleto/PIX, webhooks, APIs externas |
| `observability-expert` | Logs, métricas, falhas silenciosas em produção |
| `security-expert` | Vazamento de tenant, SQL injection, auth, vulnerabilidades |
| `qa-expert` | Cobertura, testes superficiais, regressões, flakiness |
| `product-expert` | Gap funcional (PRD vs. código real) |
| `ux-designer` | Frontend: formulários confusos, fluxos quebrados, acessibilidade |
| `governance` | Conformidade, padrões, consistência |

**Padrão de auditoria:** orchestrator coordena → especialistas analisam em paralelo → builder corrige → qa-expert valida regressão.

**Agente Codex equivalente:** leia o arquivo `.claude/agents/<nome>.md` e execute o checklist manualmente. Os arquivos são legíveis por qualquer ferramenta.

---

## 🛠️ Slash Commands (`.claude/commands/`)

| Comando | Função |
|---|---|
| `/fix <bug>` | Workflow completo de bug fix: reproduzir → diagnosticar → corrigir → testar → evidenciar |
| `/verify` | Roda lint + types + testes relevantes; reporta status |
| `/test-audit` | Audita cobertura e qualidade de testes em uma área |
| `/audit-spec <feature>` | Audita um requisito vs implementação real |
| `/functional-review` | Revisão funcional de mudança recente |
| `/security-review` | Auditoria de segurança das mudanças |
| `/review-pr` | Code review estruturado |
| `/project-status` | Estado atual do projeto/sprint |
| `/where-am-i` | Contexto rápido: branch, mudanças, próximo passo |
| `/checkpoint` | Salvar estado da sessão |
| `/resume` | Restaurar contexto da sessão anterior |
| `/context-check` | Saúde do contexto, sugere checkpoint |
| `/mcp-check` | Lista MCPs ativos, valida autorização |
| `/reaudit <camada>` | Re-auditoria neutra multi-especialista após correção (ver `AGENTS.md` §Fechamento) |
| `/camada-auto <camada>` | **Modo autônomo.** Loop auditar → corrigir tudo → reauditar até 0 findings ou bloqueio real (max 10 rodadas). Não pede confirmação no meio. Ver `AGENTS.md` §Modo Autônomo |

**Codex equivalente:** leia `.claude/commands/<nome>.md` e execute o roteiro manualmente.

---

## 🔧 Skills (`.claude/skills/`) — destaques

- **`audit-prompt`** — **OBRIGATÓRIO** antes de invocar qualquer auditor. Define regra anti-bias do prompt (§Fechamento em `AGENTS.md`).
- **`simplify`** — refina código PHP/Laravel preservando funcionalidade.
- **`fewer-permission-prompts`** — otimiza `.claude/settings.json` para reduzir prompts de permissão.
- **`update-config`, `keybindings-help`** — configuração do harness Claude.

---

## ⚙️ Hooks (`.claude/settings.json`)

- **SessionStart** — injeta a REGRA DE FECHAMENTO (camada só fecha após re-auditoria). Codex não tem hook equivalente — leia `AGENTS.md` no início de cada sessão.

---

## 🧩 MCP Servers disponíveis (Claude)

- **`context-mode`** — executa comandos grandes em sandbox isolado do contexto (PRIORITÁRIO para outputs > 20 linhas)
- **`context7`** — docs atualizadas de bibliotecas (React, Laravel, etc.)
- **`github`** — PRs, issues, releases, commits
- **`playwright`** — automação de browser para testes E2E
- **`vitest`** — runner vitest via MCP
- **Google Drive, Gmail, Calendar** — integrações do workspace

**Codex equivalente:** usar `gh`, `curl`, `playwright` via bash diretamente.

---

## 📋 Onde está o resto

**TUDO mais** (5 Leis, Harness, formato 6+1, proibições, stack, testes, fechamento, equivalências com Codex) → `AGENTS.md`.

### Formato Harness obrigatório (6+1)

Toda resposta que altere código deve manter os itens canônicos definidos em `AGENTS.md`:

1. **Resumo do problema**
2. **Arquivos alterados**
3. **Motivo técnico**
4. **Testes executados**
5. **Resultado dos testes**
6. **Riscos remanescentes**
7. **Como desfazer** quando exigido por migration, contrato de API, rota pública, deploy, remoção de feature ou risco alto.

Fluxo operacional: entender → localizar → propor → implementar → verificar → corrigir falhas → evidenciar.

Se você é o Claude, pode invocar agents/commands/skills nativamente.
Se você é Codex ou outro agente, a tabela de equivalências em `AGENTS.md` §Equivalências mostra como fazer o mesmo trabalho manualmente.
