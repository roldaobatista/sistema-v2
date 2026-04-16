# context-mode — MANDATORY routing rules

You have context-mode MCP tools available. These rules are NOT optional — they protect your context window from flooding. A single unrouted command can dump 56 KB into context and waste the entire session. Antigravity does NOT have hooks, so these instructions are your ONLY enforcement mechanism. Follow them strictly.

## CRITICAL: AGENT & SKILL PROTOCOL (START HERE)

> **MANDATORY:** You MUST read the appropriate agent file and its skills BEFORE performing any implementation. This is the highest priority rule.

### 0. AIDD Framework (MANDATORY BOOT)
Before ANY action, you MUST read the `docs/BLUEPRINT-AIDD.md` to understand the 6 Phases of this project's architecture, and use the templates in `prompts/`. Ignoring this will cause architectural hallucinations.

### 1. Modular Skill Loading Protocol

Agent activated → Check frontmatter "skills:" → Read SKILL.md (INDEX) → Read specific sections. Antigravity does NOT have hooks, so these instructions are your ONLY enforcement mechanism. Follow them strictly.

## BLOCKED commands — do NOT use these

### curl / wget — FORBIDDEN
Do NOT use `curl` or `wget` via `run_command`. They dump raw HTTP responses directly into your context window.
Instead use:
- `mcp__context-mode__ctx_fetch_and_index(url, source)` to fetch and index web pages
- `mcp__context-mode__ctx_execute(language: "javascript", code: "const r = await fetch(...)")` to run HTTP calls in sandbox

### Inline HTTP — FORBIDDEN
Do NOT run inline HTTP calls via `run_command` with `node -e "fetch(..."`, `python -c "requests.get(..."`, or similar patterns. They bypass the sandbox and flood context.
Instead use:
- `mcp__context-mode__ctx_execute(language, code)` to run HTTP calls in sandbox — only stdout enters context

### Direct web fetching — FORBIDDEN
Do NOT use `read_url_content` for large pages. Raw HTML can exceed 100 KB.
Instead use:
- `mcp__context-mode__ctx_fetch_and_index(url, source)` then `mcp__context-mode__ctx_search(queries)` to query the indexed content

## REDIRECTED tools — use sandbox equivalents

### Shell (>20 lines output)
`run_command` is ONLY for: `git`, `mkdir`, `rm`, `mv`, `cd`, `ls`, `npm install`, `pip install`, and other short-output commands.
For everything else, use:
- `mcp__context-mode__ctx_batch_execute(commands, queries)` — run multiple commands + search in ONE call
- `mcp__context-mode__ctx_execute(language: "shell", code: "...")` — run in sandbox, only stdout enters context

### File reading (for analysis)
If you are reading a file to **edit** it → `view_file` / `replace_file_content` is correct (edit needs content in context).
If you are reading to **analyze, explore, or summarize** → use `mcp__context-mode__ctx_execute_file(path, language, code)` instead. Only your printed summary enters context. The raw file stays in the sandbox.

### Search (large results)
Search results can flood context. Use `mcp__context-mode__ctx_execute(language: "shell", code: "grep ...")` to run searches in sandbox. Only your printed summary enters context.

## Tool selection hierarchy

1. **GATHER**: `mcp__context-mode__ctx_batch_execute(commands, queries)` — Primary tool. Runs all commands, auto-indexes output, returns search results. ONE call replaces 30+ individual calls.
2. **FOLLOW-UP**: `mcp__context-mode__ctx_search(queries: ["q1", "q2", ...])` — Query indexed content. Pass ALL questions as array in ONE call.
3. **PROCESSING**: `mcp__context-mode__ctx_execute(language, code)` | `mcp__context-mode__ctx_execute_file(path, language, code)` — Sandbox execution. Only stdout enters context.
4. **WEB**: `mcp__context-mode__ctx_fetch_and_index(url, source)` then `mcp__context-mode__ctx_search(queries)` — Fetch, chunk, index, query. Raw HTML never enters context.
5. **INDEX**: `mcp__context-mode__ctx_index(content, source)` — Store content in FTS5 knowledge base for later search.

## Output constraints

- Keep responses under 500 words.
- Write artifacts (code, configs, PRDs) to FILES — never return them as inline text. Return only: file path + 1-line description.
- When indexing content, use descriptive source labels so others can `search(source: "label")` later.

## Formato de Resposta Obrigatório (HARNESS ENGINEERING)

> **Fonte canônica:** `.agent/rules/harness-engineering.md` — regra H5. Consistente com `CLAUDE.md`, `AGENTS.md` e `.agent/rules/iron-protocol.md`.

Toda resposta final que envolva alteração de código DEVE conter, nesta ordem, os **6 itens obrigatórios**:

1. **Resumo do problema** — sintoma + causa raiz (1-2 frases)
2. **Arquivos alterados** — lista com `path:LN` quando pertinente
3. **Motivo técnico de cada alteração** — POR QUÊ, não O QUÊ (o diff mostra o quê)
4. **Testes executados** — comando exato, copiável, pirâmide de escalação
5. **Resultado dos testes** — output real com contagem passed/failed (proibido parafrasear)
6. **Riscos remanescentes** — o que não foi coberto, efeitos colaterais, pontos de atenção

**Item 7 OPCIONAL — obrigatório** para: migrations, alteração de contrato de API, rota pública, deploy/infra, remoção de feature, ou risco alto:

7. **Como desfazer** — passos exatos de rollback

**Proibições críticas (H7):** usar "pronto", "funcionando", "testes passando", "validado" SEM evidência objetiva de comando executado no mesmo turno. **H8:** qualquer falha de teste/lint/build/typecheck é bloqueante.

**Fluxo Harness (7 passos):** entender → localizar → propor (mínimo + correto) → implementar → verificar → corrigir → evidenciar.

## Regra de Idioma

> **Idioma:** Português (pt-BR) para explicações e comunicação.
> Inglês para nomes técnicos (variáveis, funções, classes, colunas, status).
> Esta regra é consistente com CLAUDE.md e AGENTS.md.

## ctx commands

| Command | Action |
|---------|--------|
| `ctx stats` | Call the `stats` MCP tool and display the full output verbatim |
| `ctx doctor` | Call the `doctor` MCP tool, run the returned shell command, display as checklist |
| `ctx upgrade` | Call the `upgrade` MCP tool, run the returned shell command, display as checklist |
