---
name: audit-prompt
description: Padrão obrigatório para montar prompts de auditoria e re-auditoria do Kalibrium ERP. Sempre usar esta skill antes de invocar qualquer especialista (data-expert, security-expert, governance, qa-expert, architecture-expert, etc.) em contexto de auditoria ou re-auditoria. Garante prompt neutro (sem bias), isolação de contexto do agente, e comparação set-difference mecânica fora do agente. Uso obrigatório em: /reaudit, /master-audit, /audit-spec, /security-review, /functional-review, /test-audit, ou qualquer invocação ad-hoc de especialista para auditar.
---

# audit-prompt — Prompt neutro de auditoria

## Quando usar (obrigatório)

Sempre que for invocar um especialista (`Agent` tool com subagent_type de expert) em contexto de **auditar** ou **re-auditar** — nova auditoria ou pós-correção. Também para `/reaudit`, `/master-audit`, `/audit-spec`, e invocações ad-hoc.

**Não usar para:** implementação (`builder`), correção de bug pontual (`/fix`), revisão de PR de feature nova (`review-pr`) — nesses casos o contexto de mudança é input legítimo.

## Princípio central

O agente audita o **estado atual do código em um perímetro funcional** sem saber o que mudou, quais findings existiam antes, nem quais arquivos foram tocados. A comparação com a lista canônica é **operação mecânica do coordenador, fora do agente**.

**Por que:** subagents iniciam com contexto isolado, MAS o prompt carrega bias que anula a isolação. Passar findings originais = "prove que está OK". Passar arquivos tocados = limita escopo ao que o coordenador já sabe. Resultado: agente confirma em vez de investigar.

## O que o agente recebe (e só isso)

1. **Nome da camada/escopo** — identificador textual (ex: "Camada 1 — fundação do ERP").
2. **Perímetro funcional** — definido por **domínio** (entidades, módulos), **nunca** por arquivos de commit. Ex: "entidades centrais de cadastro + financeiro operacional".
3. **Diretórios sugeridos** — caminhos gerais para explorar (ex: `backend/app/Models/`, `backend/database/migrations/`). Não arquivos específicos.
4. **Checklist do próprio agent file** — copiar verbatim da seção de critérios de `.claude/agents/<expert>.md`.
5. **Proibições explícitas.**
6. **Formato de saída obrigatório.**

## O que o agente NÃO recebe (nunca)

- IDs ou descrições de findings originais.
- `git log`, `git diff`, commit range, arquivos alterados.
- Qualquer resumo de correção anterior ("resolvemos X", "renomeamos Y").
- Conclusões antecipadas ("confirme que X", "valide Y", "aprove se Z").
- Handoffs, planos, audits existentes (proibir consulta explícita).
- Narrativa da Wave/Camada ("Wave 6 focou em PT→EN").

## Template de prompt (copiar e adaptar)

```
Você é o <expert>-expert. Auditoria independente de um perímetro do Kalibrium ERP.

## Missão
Encontrar problemas no ESTADO ATUAL do código. Não é validação
nem aprovação. Não assuma nada. Audite como se fosse a 1ª vez
vendo o sistema.

## Perímetro
Camada/escopo: <nome textual — ex: "Camada 1 — fundação">
Domínio funcional: <ex: entidades centrais cadastro + financeiro operacional>
Diretórios sugeridos (não exaustivos — explore à vontade):
  - backend/app/Models/
  - backend/app/Http/Controllers/Api/
  - backend/database/migrations/
  - frontend/src/features/<área>/

## Checklist obrigatório
<COPIAR VERBATIM da seção de critérios de .claude/agents/<expert>.md>

## O que você NÃO sabe (por design)
- O que foi alterado recentemente
- Quais problemas existiam antes
- Qual era o plano de correção
- Qual commit tocou qual arquivo

Essa isolação é proposital. Sua função é INVESTIGAR, não confirmar.

## Proibições absolutas
- NÃO ler docs/handoffs/, docs/audits/, docs/plans/
- NÃO rodar git log / git diff / git show / git blame
- NÃO especular sem evidência (exigir file:line concreto)
- NÃO aprovar/validar — apenas reportar achados
- NÃO tentar descobrir "o que mudou" — irrelevante para sua tarefa

## Saída obrigatória

Para CADA problema encontrado:
- ID: <domain>-<seq>   (ex: data-01, sec-02, gov-03)
- Severidade: S1 (crítico) / S2 (alto) / S3 (médio) / S4 (baixo)
- Arquivo:linha
- Descrição (o que está errado)
- Evidência (trecho concreto de código, query, schema)
- Impacto (o que pode quebrar)

Se nenhum problema em uma seção do checklist:
"Nada encontrado em <seção>. Verificado: <caminhos que olhou>."

Sua saída deve ser uma lista de achados, não um veredito.
Não diga "aprovado" ou "camada OK" — apenas reporte o que viu.
```

## Processo do coordenador (fora do agente)

### 1. Pré-condição — lista canônica de findings

Antes de rodar re-auditoria, existe `docs/audits/findings-<camada>.md` com lista estruturada. Formato mínimo:

```markdown
# Findings canônicos — <camada>

| ID | Severidade | Arquivo:linha | Descrição | Origem |
|---|---|---|---|---|
| PROD-001 | S2 | ... | ... | auditoria inicial <data> |
```

Se não existe, **criar antes** extraindo de handoffs/audits originais. Sem lista canônica, não há comparação possível.

### 2. Invocação paralela

Invocar N especialistas em **uma única mensagem com múltiplos `Agent` calls** (execução paralela). Cada um recebe o template acima com seu domínio. **Proibido incluir lista canônica no prompt** — ela é usada só na etapa 4.

Seleção mínima obrigatória por escopo:
- Qualquer camada: `governance` + `qa-expert`
- Toca DB/schema: `data-expert`
- Toca auth/permissão/PII: `security-expert`
- Toca contract/API: `architecture-expert`
- Toca integração externa: `integration-expert`
- Toca frontend: `ux-designer` + `product-expert`

### 3. Coletar achados

Salvar output de cada agente em `docs/audits/reaudit-<camada>-<YYYY-MM-DD>/<expert>.md`. Um arquivo por expert, verbatim.

### 4. Set-difference mecânico

Coordenador faz comparação **sem julgamento interpretativo**:

```
originais  = set de findings em docs/audits/findings-<camada>.md
encontrados = união dos achados dos N experts

resolvidos     = originais \ encontrados
não_resolvidos = originais ∩ encontrados
novos          = encontrados \ originais
```

Match por: `arquivo:linha` + palavra-chave da descrição. Se ambíguo, manter no conjunto "não resolvido" (conservador) e anotar.

### 5. Veredito (binário — zero findings)

| Situação | Veredito |
|---|---|
| `encontrados = ∅` (zero findings em todas as severidades S1..S4) | **FECHADA** |
| `encontrados ≠ ∅` (qualquer finding em qualquer severidade) | **REABERTA** |

**Não existe veredito CONDICIONAL.** Camada só fecha com re-auditoria retornando zero findings. S3/S4 aceitos como limitação devem ser documentados em `docs/TECHNICAL-DECISIONS.md` **antes** da re-auditoria e refletidos no agent file ou skill para não serem mais reportados (lista de exceções EN-only, tabelas globais-por-design, fósseis H3, etc.). Tentar documentar **depois** da re-auditoria para forçar fechamento é proibido.

Ao receber output dos experts:
- Se `encontrados ≠ ∅` → **REABERTA**. Triagem com usuário: (a) corrigir via `/fix`, ou (b) aceitar como limitação permanente (atualizar `TECHNICAL-DECISIONS.md` + agent file/skill) e re-rodar `/reaudit`.
- Se `encontrados = ∅` → **FECHADA**. Atualizar handoff com evidência.

### 6. Registrar

Consolidado em `docs/audits/reaudit-<camada>-<YYYY-MM-DD>.md`:

```markdown
# Re-auditoria <camada> — <data>

## Experts invocados
- data-expert → reaudit-<camada>-<data>/data-expert.md
- security-expert → ...

## Originais (N)
- Resolvidos (X): <lista IDs>
- Não resolvidos (Y): <lista IDs + file:line>

## Novos findings (M)
- S1 (A): <lista>
- S2 (B): <lista>
- S3 (C): <lista>

## Veredito: FECHADA | REABERTA | CONDICIONAL
```

## Proibições absolutas (coordenador)

- **Nunca** incluir a lista canônica de findings no prompt do agente.
- **Nunca** passar commit range ou `git diff --name-only` ao agente.
- **Nunca** escrever "já foi corrigido", "validar", "confirme" no prompt.
- **Nunca** pular um especialista do conjunto mínimo do escopo.
- **Nunca** resolver desacordo entre 2 experts em favor do "mais leniente" — ambos permanecem, usuário decide.
- **Nunca** declarar FECHADA sem rodar set-difference — suite verde ≠ fechamento (CLAUDE.md §Fechamento).

## Anti-padrões comuns (o que NÃO fazer)

❌ "Audite a Camada 1. Fizemos Wave 6 que renomeou colunas PT→EN. Confirme que está OK."
❌ "Verifique se os findings PROD-001, PROD-002, GOV-005 estão resolvidos. Arquivos tocados: X.php, Y.php, Z.php."
❌ "A correção foi consolidada nos commits `bffe8a1..5af63c9`. Valide."
❌ "Você vai validar o fechamento da camada. Se tudo estiver OK, aprove."

✅ "Você é o data-expert. Audite o perímetro: entidades centrais do cadastro. Diretórios sugeridos: backend/app/Models/, backend/database/migrations/. Checklist: <copiar do agent file>. Reporte findings no formato estruturado."

## Relação com outros comandos

- **`/reaudit <camada>`** — comando que aplica esta skill automaticamente.
- **`/master-audit`** — auditoria geral multi-domínio; usa esta skill para cada expert invocado.
- **`/audit-spec <spec>`** — auditoria de spec contra código; usa esta skill quando invocar experts.
- **`/security-review`, `/functional-review`, `/test-audit`** — quando rodarem um expert com propósito de auditoria, usar esta skill.
