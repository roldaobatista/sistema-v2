# AGENTS.md — Kalibrium ERP

## Idioma (OBRIGATÓRIO)
- Comunicação entre agentes + board: **PT-BR**
- Issues, PRs, comentários, descrições: **PT-BR**
- Docs (ADRs, specs, docstrings "why"): **PT-BR**
- Código-fonte (nomes, classes, métodos): **inglês**
- Commits + branches: **inglês** (Conventional Commits)

## Fonte-de-verdade
1. `docs/PRD.md` — escopo, features, stack
2. `docs/adr/*.md` — decisões arquiteturais aceitas
3. `docs/HIRING_SPEC.md` — agentes a contratar
4. `docs/runbooks/ENVIRONMENT.md` — ambiente do VPS (o que já existe)
5. `docs/runbooks/AUTONOMY.md` — regras de autonomia

## Time (21 agentes — com 2 novos críticos)
- 1 CEO (Opus 4.6)
- 1 Tech Lead
- 1 UX Designer
- 1 Implementer
- 1 Fixer
- 1 PR-Approver
- 1 **Build Verifier** (novo — roda `composer install`, `npm install`, `docker compose build` de verdade)
- 1 **CI Guardian** (novo — monitora Actions; bloqueia slices se CI vermelho)
- 13 auditores especialistas (incluindo metrologista, fiscalista, red team)

---

# 🛑 HARD GATES — NUNCA IGNORAR

## Gate 1 — Implementer antes de `git push`

**OBRIGATÓRIO rodar localmente:**
```bash
composer install --no-interaction       # backend deps
npm ci                                   # frontend deps
docker compose -f docker-compose.yml up -d --wait  # infra sobe
php artisan migrate --force              # migrations ok
php artisan test --parallel              # suite passa
npm run build                            # frontend builda
```

**Se qualquer comando retornar exit code != 0:**
- **NÃO push**. Crie issue pra você mesmo `fix: <descrição>` e resolva antes.
- Nunca "vou commitar e a CI vai me dizer" — isso gasta tokens e tempo.

## Gate 2 — Build Verifier (agente novo)

- Roda em cada PR **em container limpo** (sem cache)
- Executa a mesma sequência do Gate 1 + build Docker production
- Se falhar, **bloqueia** merge automaticamente (comentário "❌ build-verifier: <erro>")
- É o único agente que executa o projeto de ponta-a-ponta

## Gate 3 — CI Guardian (agente novo)

- Monitora `gh run list --limit 20 --json conclusion,headBranch` a cada heartbeat
- Se qualquer run em `main` estiver `failure` → cria issue **P0** no Paperclip `ci-red: <razão>` e delega ao Fixer
- **Pausa** criação de novas slices (comenta no CEO) até CI verde
- Verifica a cada 3min; se `main` verde por 15min seguidos, libera novas slices

## Gate 4 — Auditor-DevOps (reforço)

Além do estático, ele DEVE:
- `gh pr checks <pr> --required` — todos OK?
- Validar que workflows **realmente executam** (não só "linter OK" — tem que ter `composer install` + test real)
- Validar SHAs de actions externas existem: `gh api /repos/<owner>/<repo>/commits/<sha>`
- Finding automático se CI vermelho

## Gate 5 — PR-Approver (reforço — AUTO_MERGE)

**Pré-merge check MATA-MUSCA:**
```bash
# 1. Todos os 13+ auditores aprovaram?
gh pr view <n> --json reviews -q '.reviews | map(select(.state=="APPROVED")) | length'  # >= 13

# 2. CI do PR está verde?
gh pr checks <n>  # todos success

# 3. Build Verifier comentou "✅ build ok"?
gh pr view <n> --json comments | grep -q "build-verifier: OK"

# 4. Branch atualizada com main?
gh pr update-branch <n>

# 5. Re-roda CI pra confirmar pós-rebase
gh pr checks <n> --watch

# SÓ ENTÃO mergear
gh pr merge <n> --squash --delete-branch
```

**Se QUALQUER dos 5 falhar: NÃO merga. Comenta PT-BR explicando o que falta.**

---

## Workflow completo por slice

```
Tech Lead cria spec → Implementer codifica
  → Gate 1 local (build verifier local) → push
  → Gate 2 Build Verifier em container limpo no PR
  → 13 auditores em paralelo
  → Gate 4 Auditor-DevOps checa CI/SHAs
  → Findings → Fixer → loop até zero
  → Gate 5 PR-Approver (5 checks mata-musca)
  → merge em main
  → Gate 3 CI Guardian confirma main verde
```

**Qualquer falha em qualquer gate = merge bloqueado. Zero tolerância.**
