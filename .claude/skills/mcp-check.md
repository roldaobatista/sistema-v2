---
name: mcp-check
description: Lista MCP servers ativos no ambiente Claude Code do Kalibrium ERP e valida que apenas os autorizados estao em uso. Previne contaminacao de contexto por MCPs desconhecidos. Uso: /mcp-check.
---

# /mcp-check

## Uso

```
/mcp-check
```

## Por que existe

MCPs podem injetar system prompts, ferramentas ocultas ou permissoes amplas — drift silencioso. Em sistema multi-tenant em producao, qualquer fonte de instrucao nao auditada e risco. Esta skill confere o que esta ativo contra a lista mental de MCPs autorizados (ou `.claude/allowed-mcps.txt` se existir).

## Quando invocar

- Inicio de sessao em maquina nova
- Apos instalar/atualizar plugin
- Quando suspeitar comportamento estranho (ferramentas inesperadas, prompts injetados)
- Periodicamente em auditoria de seguranca

## Pre-condicoes

Nenhuma.

## MCPs autorizados (lista atual do projeto sistema)

Padrao esperado para o Kalibrium ERP:

- `plugin:context-mode:context-mode` — gestao de janela de contexto
- `plugin:context7:context7` — busca de documentacao de libs
- `plugin:github:github` — operacoes em repositorios GitHub
- `plugin:playwright:playwright` — automacao de browser para E2E
- `plugin:claude_ai_Gmail` — integracao Gmail (opcional)
- `plugin:claude_ai_Google_Calendar` — integracao Calendar (opcional)
- `plugin:claude_ai_Google_Drive` — integracao Drive (opcional)
- `mcp__vitest` — runner de testes do frontend

Se existe `.claude/allowed-mcps.txt`, ele e a fonte de verdade. Senao, esta lista mental.

## O que faz

### 1. Obter MCPs ativos

Inspecionar a sessao Claude Code atual (system prompt, ferramentas disponiveis) para listar MCPs ativos. Se disponivel:

```bash
claude mcp list
```

### 2. Comparar com a lista autorizada

```bash
# Se .claude/allowed-mcps.txt existir, le ele
cat .claude/allowed-mcps.txt
```

Senao, usa a lista mental acima.

Categorizar:
- **OK** — ativo e autorizado
- **Faltando** — autorizado mas nao ativo (provavelmente ok)
- **Suspeito** — ativo e nao autorizado (alerta)

### 3. Reportar ao usuario

**Caso saudavel:**
```
MCPs OK.

Ativos e autorizados:
- plugin:context-mode:context-mode
- plugin:context7:context7
- plugin:github:github
- plugin:playwright:playwright

Autorizados nao detectados (ok):
- mcp__vitest (so carrega quando necessario)

Nenhum MCP suspeito.
```

**Caso suspeito:**
```
ATENCAO: MCP nao autorizado detectado.

Suspeito:
- plugin:unknown:foo  -> origem desconhecida

Acao recomendada:
1. Investigar de onde veio (claude mcp list, settings.json)
2. Se nao for reconhecido, desativar
3. Adicionar a `.claude/allowed-mcps.txt` SE for legitimo
```

### 4. (Opcional) Persistir auditoria

Se o usuario quiser registro auditavel:

```bash
mkdir -p docs/audits
# escrever docs/audits/mcp-check-YYYY-MM-DD.json com a comparacao
```

Schema simplificado:

```json
{
  "skill": "mcp-check",
  "timestamp": "YYYY-MM-DDTHH:MM:SSZ",
  "verdict": "pass | fail",
  "findings_count": 0,
  "evidence": {
    "allowlist_source": ".claude/allowed-mcps.txt | mental_list",
    "allowlist": ["plugin:context-mode:context-mode", "..."],
    "detected_mcps": ["plugin:context-mode:context-mode", "..."],
    "missing_from_env": [],
    "contamination_candidates": []
  }
}
```

## Erros e recuperacao

| Cenario | Acao |
|---|---|
| `.claude/allowed-mcps.txt` nao existe | Usar lista mental acima. Sugerir criar arquivo se usuario quer formalizar. |
| Comando `claude mcp list` indisponivel | Usar inspecao manual de ferramentas disponiveis. Reportar limitacao. |
| MCP suspeito detectado | Listar nome + origem. Sugerir desativar e investigar. Nao prosseguir com sub-agents pesados ate resolver. |
| MCP autorizado falhou ao carregar | Reportar como warning. Sugerir reiniciar sessao ou diagnostico (`/context-mode:ctx-doctor`). |

## Agentes

Nenhum — executada pelo orquestrador.

## Handoff

- Tudo OK -> continuar trabalho
- MCP suspeito -> investigar e desativar antes de qualquer trabalho sensivel
- MCP faltando relevante -> instalar/ativar (ex: playwright para E2E)
