---
description: Lista MCP servers ativos e valida que apenas os autorizados em .claude/allowed-mcps.txt estao em uso. Previne contaminacao de contexto por MCPs desconhecidos. Uso: /mcp-check.
allowed-tools: Read, Bash
---

# /mcp-check

## Uso

```
/mcp-check
```

## O que faz

1. Le `.claude/allowed-mcps.txt` (allowlist; uma entrada por linha).
2. Obtem MCPs ativos no ambiente atual (via `claude mcp list` ou inspecao da sessao).
3. Compara:
   - MCP ativo nao presente na allowlist = alerta.
   - MCP na allowlist ausente do ambiente = aviso.

## Por que importa

MCPs podem injetar system prompts, ferramentas ocultas ou permissoes amplas. Deriva silenciosa. Esta verificacao cobre o vetor de contaminacao via servidor externo.

## Allowlist sugerida para `.claude/allowed-mcps.txt`

```
plugin:context-mode:context-mode
plugin:context7:context7
plugin:github:github
plugin:playwright:playwright
```

Ajustar conforme necessidade do projeto.

## Pre-condicoes

- Nenhuma. Pode rodar a qualquer momento.
- Se `.claude/allowed-mcps.txt` nao existir, alertar que precisa ser criado.

## Erros e Recuperacao

| Cenario | Recuperacao |
|---|---|
| `.claude/allowed-mcps.txt` nao existe | Alertar usuario. Sugerir criar com a allowlist sugerida acima. |
| MCP ativo nao esta na allowlist | Investigar imediatamente. Se nao reconhecido, desativar. |
| Comando `claude mcp list` indisponivel | Usar metodo alternativo (inspecao manual da sessao). Reportar limitacao. |

## Agentes

Nenhum. Executada pelo orquestrador.
