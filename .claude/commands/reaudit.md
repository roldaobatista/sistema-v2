---
description: Re-auditoria isolada e sem viés de uma Camada/Wave após correção. Invoca especialistas em paralelo com prompt neutro (skill audit-prompt). Uso: /reaudit <camada>.
allowed-tools: Read, Bash, Grep, Glob, Agent, Skill
---

# /reaudit

## Uso

```
/reaudit "Camada 1"
/reaudit "Wave 6"
```

> **Não aceitar mais commit range ou lista de arquivos.** Esses inputs enviesam o agente e anulam a isolação de contexto. Ver skill `audit-prompt`.

## Propósito

Re-auditoria **pós-correção** de uma Camada/Wave. Responde em ordem:

1. Os findings originais da camada foram **efetivamente resolvidos**?
2. A correção introduziu **novos findings**?

**Suite verde NÃO basta para fechamento** (AGENTS.md §Fechamento). Este comando é a rodada de auditoria que confirma fechamento.

## Por que existe

Proteção contra viés de confirmação. Subagents iniciam com contexto isolado, MAS o prompt carrega bias que anula essa isolação. Este comando garante que o prompt enviado seja **neutro** — conforme a skill `audit-prompt`. Proibido vazar narrativa da correção, IDs de findings originais, ou arquivos tocados.

## Pré-condições

- **Lista canônica de findings** em `docs/audits/findings-<camada>.md` (formato definido em `audit-prompt`). Se ausente, criar primeiro (extraindo de audit originais + handoffs).
- Agentes relevantes ao escopo disponíveis em `.claude/agents/`.
- Skill `audit-prompt` carregada.

## O que faz

### 1. Validar lista canônica

Ler `docs/audits/findings-<camada>.md`. Se ausente → abortar e pedir que seja consolidada. **Não adivinhar findings nem extrair de memória/handoff no momento da invocação** — isso é trabalho de consolidação prévia.

### 2. Determinar perímetro funcional

A partir do nome da camada, identificar o **domínio** (não os arquivos). Exemplos:
- "Camada 1 — fundação" → entidades centrais de cadastro + financeiro operacional
- "Camada 2 — operacional" → agenda, OS, calibrações, orçamento

O perímetro é **funcional**, não "arquivos do commit X". Ver skill `audit-prompt`.

### 3. Selecionar especialistas

Conjunto mínimo obrigatório (skill `audit-prompt`):
- Qualquer camada: `governance` + `qa-expert`
- Toca DB/schema: `data-expert`
- Toca auth/PII: `security-expert`
- Toca contract/API: `architecture-expert`
- Toca integração externa: `integration-expert`
- Toca frontend: `ux-designer` + `product-expert`

### 4. Invocar em paralelo — prompt neutro via skill

**Obrigatório**: montar o prompt de cada agente usando o template da skill `audit-prompt`. Uma única mensagem com N `Agent` calls paralelos.

**Proibido incluir no prompt do agente:**
- IDs de findings originais
- Commit range, arquivos tocados
- Qualquer narrativa da correção
- Conclusões antecipadas ("valide", "confirme")

**Obrigatório incluir:**
- Camada + perímetro funcional
- Diretórios sugeridos (não arquivos)
- Checklist verbatim do `.claude/agents/<expert>.md`
- Proibições e formato de saída da skill `audit-prompt`

### 5. Coletar achados

Salvar output de cada agente em `docs/audits/reaudit-<camada>-<YYYY-MM-DD>/<expert>.md` verbatim.

### 6. Set-difference mecânico (coordenador, fora do agente)

```
originais     = set(docs/audits/findings-<camada>.md)
encontrados   = união(achados de todos os experts)

resolvidos     = originais \ encontrados
não_resolvidos = originais ∩ encontrados
novos          = encontrados \ originais
```

Match por `arquivo:linha + palavra-chave`. Ambiguidade → mantém em "não resolvido" (conservador).

### 7. Veredito (binário — zero findings)

| Situação | Veredito |
|---|---|
| `encontrados = ∅` (zero findings em todas as severidades S1..S4) | **FECHADA** |
| `encontrados ≠ ∅` (qualquer finding em qualquer severidade) | **REABERTA** |

**Não existe CONDICIONAL.** S3/S4 aceitos como limitação são documentados em `TECHNICAL-DECISIONS.md` **antes** da auditoria (refletidos em agent files/skills para não reaparecerem como finding). Depois da auditoria, não há como "promover" S3/S4 a "aceito" para forçar fechamento.

### 8. Consolidar e registrar

`docs/audits/reaudit-<camada>-<YYYY-MM-DD>.md` no formato definido em `audit-prompt`.

## Erros e recuperação

| Cenário | Recuperação |
|---|---|
| `docs/audits/findings-<camada>.md` ausente | Abortar. Instruir usuário a consolidar. |
| Agente retorna veredito em vez de achados | Rejeitar output. Re-invocar reforçando "reporte, não julgue". |
| Desacordo entre 2 especialistas | Ambos achados permanecem. Usuário decide. |
| Agente tentou ler docs/audits/ ou rodar git log | Rejeitar output. Reforçar proibições da skill. |

## Proibições

- Nunca passar ao agente: findings originais, commit range, arquivos tocados, narrativa da correção, conclusões antecipadas.
- Nunca pular um expert do conjunto mínimo do escopo.
- Nunca rodar 1 expert e extrapolar para fechamento multi-domínio.
- Nunca declarar FECHADA sem set-difference contra lista canônica.
- Nunca resolver desacordo em favor do mais leniente.

## Handoff

- FECHADA → atualizar handoff da camada com link ao relatório de re-auditoria.
- REABERTA → etapa volta ao `builder`. Re-rodar `/reaudit` após correção.
- CONDICIONAL → registrar dívida em `TECHNICAL-DECISIONS.md`, decidir se bloqueia avanço.

## Referências

- `AGENTS.md` §Fechamento de Camada/Wave/Etapa — critério binário de fechamento.
- `.claude/skills/audit-prompt.md` — template neutro obrigatório para prompts de agente.
- `.claude/agents/governance.md` — conformidade sempre obrigatória.
- `.claude/agents/qa-expert.md` — cobertura sempre obrigatória.
- `.claude/agents/data-expert.md` — quando toca DB/schema.
- `.claude/agents/security-expert.md` — quando toca auth/PII.
- `.claude/agents/architecture-expert.md` — quando toca contract/API.
- `.claude/agents/integration-expert.md` — quando toca integração externa.
- `.claude/agents/ux-designer.md` / `.claude/agents/product-expert.md` — quando toca frontend.
- `docs/audits/findings-<camada>.md` — baseline canônica da camada.
- `docs/TECHNICAL-DECISIONS.md` — dívidas aceitas permanentes.
