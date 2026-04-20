---
description: Modo autônomo — executa o loop auditar → corrigir tudo → reauditar, até zero findings ou 10 rodadas. Só traz o usuário em bloqueio real. Uso: /camada-auto <nome-da-camada>.
allowed-tools: Agent, Read, Edit, Write, Bash, Grep, Glob, Skill
---

# /camada-auto

## Uso

```
/camada-auto "Camada 1"
/camada-auto "Wave 6 — calibração normativa"
```

## Por que existe

O usuário não quer aprovar cada passo. Quer aprovar **uma vez** o início de uma camada e que o orchestrator + builder + experts executem autonomamente até **zero findings** ou até atingirem um **bloqueio real** que exige decisão humana.

Sem este comando, cada rodada de `/reaudit` + `/fix` + `/reaudit` exigia turno novo do usuário. Isto elimina o vai-e-volta.

## Contrato duro do modo autônomo

### Durante o loop é PROIBIDO

1. **Mascarar** teste, assertion, skip, `markIncomplete`, `assertTrue(true)`, `Exception` engolida silenciosamente (viola Lei 2 do `AGENTS.md`).
2. **Documentar dívida técnica** como limitação permanente em `TECHNICAL-DECISIONS.md` **durante o loop** pra zerar findings artificialmente. Dívida só pode ser registrada ANTES do loop começar (e deve estar refletida nos agent files pra experts não reportarem).
3. **Remover ou diminuir funcionalidade** existente (viola Lei 3 + §Proibições Absolutas).
4. **Mudar escopo da camada** silenciosamente.
5. **Aceitar findings S3/S4** sem corrigir.
6. **Usar `--no-verify`**, `--skip-*`, `--ignore-*` (fora das 3 exceções Windows).
7. **Escalar testes** sem tentar nível menor primeiro (viola pirâmide §AGENTS.md).

### Durante o loop é OBRIGATÓRIO

1. **Zero tolerância:** veredito `FECHADA` só sai com 0 findings S1..S4.
2. **Cada rodada deve produzir commit atômico** — mesmo que seja "rodada N não mudou nada" (raro). Rastreabilidade completa.
3. **Cada rodada deve atualizar** `docs/handoffs/auto-<camada>-<rodada>.md` com: commits da rodada, findings fechados, findings novos (se houver — por regressão).
4. **Cada re-auditoria** deve usar prompt neutro (skill `audit-prompt`, §Regra anti-bias em `AGENTS.md`).
5. **Pre-commit hook** (`.githooks/pre-commit`) precisa estar ativo — garantia mecânica contra mascaramento.

## Bloqueios reais — só estes param o loop antes das 10 rodadas

Qualquer um destes **para imediatamente** e traz o usuário:

### B1 — Decisão de produto/arquitetura que builder não pode tomar sozinho

Exemplos:
- Resolução de tenant via subdomain vs header vs path (finding `sec-portal-tenant-enumeration-bypass`).
- Definir política de retenção de logs.
- Trocar biblioteca externa.
- Mudar contrato de API pública.

**Ação:** builder cria `docs/blocks/<camada>-B1-<slug>.md` descrevendo as 2-3 opções e para.

### B2 — Migration destrutiva proposta

DROP COLUMN com dados em produção, DROP TABLE, truncate, data loss irreversível.

**Ação:** para e pergunta. Nunca executa sem OK explícito.

### B3 — Remoção ou redução de funcionalidade

Mesmo que um expert sugira remover, Lei 3 (completude) + §Proibições Absolutas vedam. Só com OK explícito.

**Ação:** para e traz a proposta para decisão.

### B4 — Cascata > 50 arquivos fora do escopo inicial da camada

Já existe regra §Sequenciamento "cascata > 5 arquivos = parar e reportar". Nesse modo autônomo, limiar subido pra 50 (builder tem mais liberdade), mas ainda para e relata.

**Ação:** para, descreve o escopo explodido, traz o usuário.

### B5 — Conflict entre 2+ experts em 2 rodadas seguidas sem convergência

Ex: security quer X, qa quer Y, data quer Z — 2 rodadas seguidas, ambos ainda reportam findings inversos.

**Ação:** para e apresenta o impasse. Usuário decide.

### B6 — Infra quebrada que builder não resolve em 2 tentativas

Ex: schema SQLite não regenera, `composer install` falha, vendor/ corrompido, hook pre-commit quebrando por razão que builder não consegue reparar em 2 tentativas dentro da mesma rodada.

**Ação:** para e traz logs completos.

## Fluxo do loop (pseudocódigo)

```
input: camada (ex: "Camada 1")
max_rounds = 10
round = 0

loop:
  round++

  # 1. Snapshot baseline
  escrever docs/handoffs/auto-<camada>-r<round>.md com header
  git log --oneline -5 >> handoff

  # 2. Re-auditar
  Skill(audit-prompt)  # carrega regra anti-bias
  Agent(security-expert, qa-expert, data-expert, governance) em paralelo
    com prompt neutro (sem narrativa, sem conclusão antecipada)
  consolidar findings

  # 3. Verificar zero
  if findings == 0:
    escrever veredito FECHADA em docs/audits/reaudit-<camada>-<data>.md
    sair com sucesso

  # 4. Verificar bloqueio real
  for finding in findings:
    if finding.trigger_block(B1..B6):
      escrever docs/blocks/<camada>-B<n>-<slug>.md
      PARAR e TRAZER USUÁRIO

  # 5. Limite de rodadas
  if round >= max_rounds:
    escrever docs/blocks/<camada>-max-rounds.md com estado
    PARAR e TRAZER USUÁRIO

  # 6. Corrigir TUDO
  Agent(builder) com todos os findings agrupados por batch lógico
    builder deve rodar pre-commit hook local antes de qualquer git commit
    builder deve produzir 1+ commit por batch (atômico)
    builder proibido de aceitar dívida técnica
    builder proibido de remover funcionalidade
    builder proibido de mascarar teste

  # 7. Evidenciar
  commit final da rodada com resumo
  atualizar docs/handoffs/auto-<camada>-r<round>.md com resultados

  continue loop
```

## Validação pós-commit (após cada rodada)

Em cada commit da rodada, o pre-commit hook (`.githooks/pre-commit`) roda:
- pint + analyse + pest --dirty (backend)
- typecheck + lint (frontend)

Se bloquear → não é mascaramento, é sinal de que builder errou. Corrigir e tentar de novo (**ainda na mesma rodada** — não incrementa contador).

## Saída

### Sucesso (zero findings antes de 10 rodadas)

```
✅ Camada <nome> FECHADA em <N> rodadas.

- Findings fechados total: <X>
- Commits gerados: <Y>
- Última re-auditoria: docs/audits/reaudit-<camada>-<data>.md
- Working tree limpo.

Próxima camada pode iniciar.
```

### Bloqueio (usuário deve entrar)

```
🛑 Camada <nome> BLOQUEADA na rodada <N>/10.

Gatilho: B<n> — <descrição>
Detalhes: docs/blocks/<camada>-B<n>-<slug>.md
Estado atual: docs/handoffs/auto-<camada>-r<N>.md

Ação do usuário necessária antes de continuar.
```

### Esgotou rodadas (10 passaram sem zerar)

```
⚠️ Camada <nome> NÃO fechou em 10 rodadas.

Findings remanescentes: <X> (S1=<a> S2=<b> S3=<c> S4=<d>)
Último estado: docs/audits/reaudit-<camada>-<data>.md
Handoff por rodada: docs/handoffs/auto-<camada>-r1..r10.md

Possíveis causas: oscilação (fix gera finding novo), escopo mal definido,
ou builder atingiu limite. Usuário deve revisar.
```

## Códigos de saída

- `0` — sucesso, zero findings
- `1` — bloqueio real (B1..B6)
- `2` — esgotou 10 rodadas sem zerar

## Como invocar em Codex / outro agente

Codex não tem slash commands nativos, mas o roteiro acima é declarativo. Usuário pode dizer:

> "Execute o loop descrito em `.claude/commands/camada-auto.md` para a Camada <nome>. Só pare se atingir zero findings, bloqueio real (B1..B6), ou 10 rodadas."

E o Codex executa manualmente cada passo (invoca experts via leitura dos `.claude/agents/*.md` + `Skill(audit-prompt)` via leitura do arquivo).

## Observações

- **Custo:** 10 rodadas × 4 experts × ~30k tokens = ~1.2M tokens por camada no pior caso. Vale se substitui horas de vai-e-volta manual.
- **Risco de oscilação:** uma correção pode gerar finding novo. O contrato de "corrige tudo sem mascarar" deveria evitar, mas se oscilar, cai em B5 (conflict) ou max-rounds.
- **Paper trail:** cada rodada deixa handoff + commits. Fácil auditar o que o sistema fez autonomamente.
