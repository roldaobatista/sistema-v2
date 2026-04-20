# Git Hooks — Kalibrium ERP

Hooks committed no repo para enforçar as Leis 1 e 2 do `AGENTS.md` **mecanicamente**, independente do agente (Claude, Codex, humano, CI). Garantem que nenhum commit passa sem lint/types/testes verdes.

## Ativação (uma vez por clone)

```bash
git config core.hooksPath .githooks
```

Windows (git bash / PowerShell): mesmo comando. O executável do hook é shell script; em Windows funciona via `C:\Program Files\Git\bin\bash.exe` que o Git usa automaticamente.

## Hooks incluídos

### `pre-commit`

Roda **antes** de qualquer `git commit`. Valida apenas o que está **staged**:

- Se tocou `backend/`: `pint --test` → `composer analyse` → `pest --dirty --parallel`.
- Se tocou `frontend/`: `npm run typecheck` → `npm run lint`.
- Se tocou só docs (`docs/`, `*.md`, `.githooks/`, etc.): pulado (passa direto).

**Falha em qualquer gate = commit bloqueado.** Mensagem em PT-BR explica qual gate e como corrigir.

**Bypass é proibido** (violação da Lei 2 do `AGENTS.md`). `--no-verify` nunca deve ser usado. Se o gate está bloqueando legitimamente, é porque o código tem problema real.

## Emergência real

Se houver incidente em produção e algum gate estiver bloqueado por falha de infraestrutura, trate como bloqueio B6 do modo autônomo: documente logs completos, pare o loop e peça decisão explícita. Não desative hooks localmente e nunca use `--no-verify` — é explicitamente proibido pelo `AGENTS.md`.

## Manutenção

Ao adicionar nova linguagem/ferramenta ao projeto, atualize o `pre-commit` pra rodar o gate correspondente. Exemplo: se adicionar um serviço Go, adicione `go vet` + `go test`.
