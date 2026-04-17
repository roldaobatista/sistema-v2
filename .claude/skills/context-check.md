---
name: context-check
description: Verifica saude do contexto da sessao no Kalibrium ERP e sugere checkpoint proativo quando necessario. Monitora sinais de contexto grande (muitas mensagens, sub-agents pesados, compactacao). Uso: /context-check.
---

# /context-check

## Uso

```
/context-check
```

## Por que existe

Sessoes longas causam compactacao de contexto, perda de detalhes e drift silencioso. Este skill detecta sinais de degradacao e sugere checkpoint antes que o problema aconteca.

## Quando invocar

- Apos sub-agents pesados (orchestrator, builder, master-audit, security-expert)
- Quando o usuario perceber respostas menos precisas
- Antes de iniciar tarefas complexas (auditoria multi-dominio, refactor amplo)
- Periodicamente durante sessoes longas

## Pre-condicoes

Nenhuma (sempre disponivel).

## O que faz

### 1. Verificar sinais de contexto grande

Checar indicadores:
- Numero de mensagens na conversa (acima de 40 = alerta)
- Sub-agents invocados na sessao (cada um consome budget)
- Arquivos lidos/editados (muitos = contexto poluido)
- Se houve compactacao automatica de mensagens anteriores

### 2. Verificar estado salvo

- `docs/handoffs/latest.md` existe e esta atualizado?
- Ultimo checkpoint: quando foi?

### 3. Emitir recomendacao

**Se contexto saudavel:**
```
Contexto saudavel.
- Mensagens: ~N
- Sub-agents usados: N
- Ultimo checkpoint: <quando>

Pode continuar normalmente.
```

**Se contexto degradado (>80% do limite):**
```
A sessao esta ficando longa. Recomendo:

1. Salvar o estado atual -> /checkpoint
2. Encerrar sessao
3. Retomar com -> /resume

Isso garante que nenhum detalhe se perca.
Quer que eu salve o checkpoint agora?
```

**Se critico (>90% ou compactacao detectada):**
```
ATENCAO: O contexto da sessao ja foi comprimido.
Detalhes de mensagens antigas podem ter sido perdidos.

Acao imediata:
1. Vou salvar checkpoint agora
2. Por favor abra nova sessao e use /resume

[executa /checkpoint automaticamente]
```

## Tabela de severidade

| Cenario | Severidade | Acao |
|---|---|---|
| `docs/handoffs/latest.md` nao existe | S4 | Sugerir `/checkpoint` para criar baseline. |
| Sessao muito curta (<5 mensagens) | S5 | Informar que nao ha necessidade de checkpoint. |
| Contexto >80% do limite | S3 | Recomendar `/checkpoint` + nova sessao via `/resume`. |
| Contexto >90% do limite | S2 | Acao automatica: rodar `/checkpoint` sem perguntar. Pedir nova sessao. |
| Compactacao automatica detectada | S2 | Detalhes anteriores foram comprimidos. Executar `/checkpoint` imediato. |
| Sub-agent falhou silenciosamente | S3 | Registrar nota. Nao reinvocar sem decisao do usuario. Salvar checkpoint. |
| MCP desconectou durante operacao | S3 | Alertar usuario. Sugerir `/mcp-check` apos reconexao. |

## Output esperado no chat

Toda invocacao emite uma das tres mensagens (nunca output tecnico cru):

**Saudavel:**
```
Contexto saudavel. Mensagens: N. Sub-agents usados: N. Ultimo checkpoint: <quando>.
Pode continuar normalmente.
```

**Degradado (>80%):**
```
A sessao esta ficando longa. Recomendo salvar o estado e abrir nova sessao.
Proximo passo sugerido: /checkpoint -> fechar sessao -> /resume.
```

**Critico (>90% ou compactacao):**
```
O contexto ja foi comprimido. Detalhes antigos podem ter sido perdidos.
Acao automatica: salvei checkpoint agora. Por favor abra nova sessao e use /resume.
```

## Agentes

Nenhum — executada pelo orquestrador.

## Handoff

- Saudavel -> continuar trabalho normal
- Degradado -> `/checkpoint` e sugerir nova sessao
- Critico -> `/checkpoint` automatico, pedir nova sessao
