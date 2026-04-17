---
description: Verifica saude do contexto da sessao e sugere checkpoint proativo quando necessario. Uso: /context-check.
allowed-tools: Read, Bash, Grep, Glob
---

# /context-check

## Uso

```
/context-check
```

## Por que existe

Sessoes longas causam compressao de contexto, perda de detalhes e drift silencioso. Este comando detecta sinais de degradacao e sugere checkpoint antes que o problema aconteca.

## Quando invocar

- Apos sub-agentes pesados (builder, qa-expert, security-expert).
- Quando o usuario perceber respostas menos precisas.
- Antes de iniciar tarefas complexas (refactor amplo, migration grande).
- Periodicamente durante sessoes longas.

## Pre-condicoes

- Nenhuma. Sempre disponivel.

## O que faz

### 1. Verificar sinais de contexto grande

- Numero de mensagens na conversa (acima de 40 = alerta).
- Sub-agentes invocados na sessao (cada um consome budget).
- Arquivos lidos/editados (muitos = contexto poluido).
- Se houve compressao automatica de mensagens anteriores.

### 2. Verificar estado salvo

- Existe handoff recente em `docs/handoffs/`?
- Quando foi o ultimo `/checkpoint`?

### 3. Emitir recomendacao

**Contexto saudavel:**
```
Contexto da sessao esta saudavel.
- Mensagens: ~N
- Sub-agentes usados: N
- Ultimo checkpoint: HH:MM

Pode continuar normalmente.
```

**Contexto degradado:**
```
A sessao esta ficando longa. Recomendo:

1. Salvar o estado atual -> /checkpoint
2. Abrir nova sessao
3. Retomar com -> /resume

Quer que eu salve o checkpoint agora?
```

**Critico (compressao detectada):**
```
O contexto da sessao ja foi comprimido.
Detalhes de mensagens antigas podem ter sido perdidos.

Acao imediata:
1. Vou salvar checkpoint agora
2. Por favor abra nova sessao e use /resume
```

## Erros e Recuperacao

| Cenario | Acao |
|---|---|
| `docs/handoffs/` nao existe | Sugerir `/checkpoint` para criar primeiro. |
| Checkpoint falha ao salvar | Reportar erro e sugerir salvamento manual. |
| Sessao muito curta (< 5 mensagens) | Informar que nao ha necessidade de checkpoint. |

## Handoff

- Contexto saudavel -> continuar trabalho normal.
- Contexto degradado -> `/checkpoint` e sugerir nova sessao.
- Contexto critico -> `/checkpoint` automatico.
