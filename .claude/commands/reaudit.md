---
description: Re-auditoria isolada e sem viés de uma Camada/Wave após correção. Invoca especialistas em paralelo com prompt padronizado neutro. Uso: /reaudit <camada> [<range-commits>].
allowed-tools: Read, Bash, Grep, Glob, Agent
---

# /reaudit

## Uso

```
/reaudit "Camada 1"
/reaudit "Camada 1" 38fea08..HEAD
/reaudit "Wave 6" bffe8a1
```

## Propósito

Re-auditoria **pós-correção** de uma Camada/Wave. Responde duas perguntas, nessa ordem:

1. Os findings originais da camada foram **efetivamente resolvidos**?
2. A correção introduziu **novos findings**?

**Suite verde NÃO basta para fechamento** (CLAUDE.md §Fechamento). Este comando é a rodada de auditoria que confirma fechamento.

## Por que existe

Proteção contra viés de confirmação. Quando o mesmo agente que corrigiu também audita, tende a aprovar. Especialistas em subagents começam com contexto fresco, MAS o prompt que carrega bias anula essa isolação. Este comando garante que o prompt enviado aos especialistas seja **neutro**: lista findings + range de commits + checklist do domínio. Proibido vazar narrativa, conclusões, ou "já foi feito".

## Pré-condições

- Documento de auditoria original existe (formato padrão: `docs/audits/RELATORIO-AUDITORIA-SISTEMA.md` ou equivalente listando findings com ID).
- Range de commits da correção identificável (handoff/log).
- Agentes `data-expert`, `security-expert`, `governance`, `qa-expert` disponíveis em `.claude/agents/`.

## O que faz

### 1. Coletar inputs neutros

- **Findings originais:** ler documento de auditoria e extrair lista de IDs com status (S0/S1/S2 + descrição 1-linha). Se ausente, abortar.
- **Range de commits:** `git log --oneline <range>` — lista crua, sem interpretação.
- **Arquivos tocados:** `git diff --name-only <range>` — lista crua.
- **Schema dump** (se camada é DB): `backend/database/schema/sqlite-schema.sql`.

### 2. Montar prompt neutro padronizado

Template rígido (um por especialista). Proibido adicionar: "já foi feito X", "confirme que Y", "aprove se Z", "validamos W", resumo da correção.

```
Re-auditoria de <camada>.

Seu domínio: <data|security|governance|qa>

Findings originais listados no documento:
<lista de IDs sem descrição de correção>

Range de commits a auditar:
<git log cru>

Arquivos tocados:
<git diff --name-only cru>

Suas tarefas:
1. Para CADA finding do seu domínio: verifique se está resolvido no código atual. Evidência via grep/read com file:line.
2. Procure novos findings introduzidos pelos commits do range. Aplique seu checklist completo sem viés do que "deve estar bom".
3. Devolva em formato estruturado:
   - RESOLVIDOS: [ID] + arquivo:linha de evidência
   - NÃO RESOLVIDOS: [ID] + arquivo:linha + por quê
   - NOVOS: [severidade] + arquivo:linha + descrição

Proibido assumir que correções foram bem feitas. Se tiver dúvida, rejeitar e pedir evidência. Sua função é ENCONTRAR, não aprovar.
```

### 3. Rodar 4 especialistas em paralelo

Spawn simultâneo via Agent tool (única mensagem com múltiplas invocações):

- `data-expert` — schema, índices, integridade, N+1, migrations
- `security-expert` — tenant isolation, OWASP, LGPD, secrets, PII
- `governance` — padrões/convenções, consistência, LGPD governança, retenção
- `qa-expert` — cobertura testes, edge cases, anti-patterns, regressão

Cada um recebe o MESMO template com filtro de domínio diferente.

### 4. Consolidar resultado

Após os 4 retornarem, agregar:

```
Re-auditoria <camada>

FINDINGS ORIGINAIS (<total>):
  ✅ Resolvidos: X (IDs)
  ❌ Não resolvidos: Y (IDs)
  ⚠️ Parcialmente resolvidos: Z (IDs)

NOVOS FINDINGS (introduzidos pela correção): N
  S1: ...
  S2: ...
  S3: ...

VEREDITO: <FECHADA | REABERTA | CONDICIONAL>
```

**Critério de fechamento:**
- FECHADA: 100% originais resolvidos + zero novos S1/S2
- REABERTA: ≥ 1 original não resolvido OU ≥ 1 novo S1
- CONDICIONAL: originais resolvidos + apenas novos S3/S4 (camada pode prosseguir com dívida documentada)

### 5. Registrar no histórico

Salvar output consolidado em `docs/audits/reaudit-<camada>-<YYYY-MM-DD>.md` com links aos 4 relatórios individuais dos especialistas.

## Erros e recuperação

| Cenário | Recuperação |
|---|---|
| Documento de auditoria original ausente | Abortar. Não adivinhar findings. |
| Range de commits inválido | Pedir ao usuário. |
| Agente retorna "aprovado" sem evidência | Rejeitar output. Re-invocar com pedido de file:line explícito. |
| Desacordo entre 2 especialistas | Ambos findings permanecem no output. Usuário decide. |

## Proibições

- **Nunca** enviar ao agente: "eu acho que", "já foi corrigido", "confirme que", "validar fechamento", "aprovar se", resumo narrativo da correção.
- **Nunca** pular especialista relevante pelo atalho "o escopo é pequeno".
- **Nunca** rodar um especialista único e extrapolar para fechamento multi-domínio.
- **Nunca** declarar fechamento se algum agente retornou veredito de bloqueio.

## Handoff

- Todos verdes → fechamento legítimo. Atualizar handoff de camada com link ao relatório de re-auditoria.
- Algum bloqueio → etapa volta ao `builder`. Re-rodar `/reaudit` após correção.
- Novos findings S3/S4 → documentar como dívida em TECHNICAL-DECISIONS.md, decidir se bloqueia avanço de camada.
