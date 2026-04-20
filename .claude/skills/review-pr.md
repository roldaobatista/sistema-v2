---
name: review-pr
description: Code review estruturado de um PR no GitHub do repositorio `sistema` (Kalibrium ERP). Valida arquitetura, padroes, testes, tenant safety, e consistencia com PRD. Uso: /review-pr <PR#>.
argument-hint: "<PR#>"
---

# /review-pr

## Uso

```
/review-pr 145
/review-pr 145 --area backend     # foca em parte especifica
```

## Por que existe

Code review do Kalibrium ERP segue padroes rigidos: tenant safety, FormRequest com logica real, paginacao, eager loading, testes adaptativos, consistencia end-to-end. Esta skill fecha o ciclo de revisao antes de merge.

## Quando invocar

- Apos `/verify` verde no PR
- Apos `/security-review` aprovado
- Antes de aprovar PR no GitHub

## Pre-condicoes

- PR existe no GitHub: `gh pr view <PR#>`
- Acesso ao repositorio local
- `gh` CLI autenticado

## O que faz — passos

### 1. Carregar contexto do PR

```bash
gh pr view <PR#> --json title,body,author,files,additions,deletions
gh pr diff <PR#>
gh pr checks <PR#>
```

Capturar:
- Titulo e descricao
- Arquivos alterados (count + paths)
- CI status (verde antes de revisar)
- Comentarios e reviews anteriores

### 2. Categorizar a mudanca

Tipos:
- **bug fix** — espera teste de regressao
- **feature nova** — espera cadeia ponta a ponta + PRD/RF associado
- **refactor** — espera preservacao 100% (Lei 8) + zero novos testes vermelhos
- **migration** — espera guard `hasTable/hasColumn` + schema dump regenerado
- **chore/docs** — escopo restrito, validacao leve

### 3. Checklist de revisao

#### Arquitetura e padroes (AGENTS.md)

- [ ] Segue padrao dos endpoints existentes (status codes, formato JSON)
- [ ] Controller usa `current_tenant_id`, nunca body
- [ ] FormRequest::authorize() com logica real
- [ ] Index pagina (`->paginate(15)`, nao `->all()`)
- [ ] Eager loading em relacionamentos (`->with([...])`)
- [ ] `tenant_id` e `created_by` setados no controller, nao no FormRequest
- [ ] Models com `BelongsToTenant` se tocam dados de tenant
- [ ] Sem N+1 detectavel

#### Migration

- [ ] Nova migration (nao edicao de antiga) — H3
- [ ] Guard `Schema::hasTable`/`hasColumn`
- [ ] `down()` reversivel
- [ ] `php generate_sqlite_schema.php` rodado e schema atualizado commitado

#### Testes

- [ ] Cobertura adaptativa (feature: 8+, CRUD: 4-5, bug: regressao+afetados)
- [ ] 5 cenarios: sucesso, 422, cross-tenant 404, 403, edge case
- [ ] `assertJsonStructure` usado, nao so status
- [ ] Sem `assertTrue(true)` ou skip injustificado
- [ ] Suite verde (CI passou)

#### Frontend (se aplicavel)

- [ ] Tipo TypeScript definido (sem `any` desnecessario)
- [ ] API client atualizado
- [ ] Componente usa o tipo (sem cast)
- [ ] Sincronia com backend (campo no model = campo no tipo TS)
- [ ] Loading state e error handling

#### PRD / RF

- [ ] Mudanca rastreada para RF/AC do `docs/PRD-KALIBRIUM.md` (se feature)
- [ ] Comportamento bate com AC literal

#### Seguranca

- [ ] Sem secret hardcoded
- [ ] Sem SQL raw com interpolacao
- [ ] Permissao registrada no PermissionsSeeder se nova rota
- [ ] Cross-tenant testado

#### Lei 8 — preservacao na reescrita

Se ha refactor:
- [ ] Cada linha removida (`-`) justificada
- [ ] Comportamentos antigos preservados

### 4. Avaliar comentarios do GitHub

```bash
gh api repos/:owner/:repo/pulls/<PR#>/comments
```

Verificar se feedbacks anteriores foram enderecados.

### 5. Postar review estruturado

```bash
gh pr review <PR#> --comment --body "$(cat <<'EOF'
## Resumo
<2-3 linhas: tipo, escopo, impressao geral>

## Pontos OK
- ...

## Findings
### S1 - Critico
- path:LN — descricao + sugestao

### S2 - Alto
- path:LN — descricao + sugestao

### S3 - Medio
- path:LN — descricao + sugestao

## Proximo passo
- Approve | Request changes | Comment
EOF
)"
```

Decisao final:
- 0 findings ou so S4 -> `gh pr review <PR#> --approve`
- S3 com sugestao -> comment, deixar autor decidir
- S1/S2 -> `gh pr review <PR#> --request-changes`

### 6. Reportar ao usuario no formato Harness 6+1

```
1. Resumo — PR#, tipo, decisao (approve/request changes/comment)
2. Arquivos revisados — count + areas
3. Findings — por severidade, com path:LN
4. Comandos rodados — gh pr view/diff/checks/review
5. Resultado — output do gh pr review
6. Riscos — areas que merecem atencao pos-merge
```

## Regras invioláveis

- **CI vermelho = nao revisar.** Pedir verde primeiro.
- **S1 BLOQUEIA approve.** Request changes obrigatorio.
- **Refactor sem teste = suspeito.** Exigir evidencia de preservacao.
- **Migration mergeada nao pode ser editada.** Se vir, pedir nova migration com guard.

## Erros e recuperacao

| Cenario | Acao |
|---|---|
| `gh` nao autenticado | `gh auth login` antes de continuar |
| PR nao existe | Pedir numero correto |
| PR muito grande (>50 arquivos) | Sugerir decomposicao. Revisar amostra critica + reportar limitacao. |
| CI vermelho | Solicitar correcao, nao revisar agora |

## Handoff

- Approve -> autor pode mergear (apos `/security-review` aprovado)
- Request changes -> autor abre nova rodada, re-revisar delta
- Comment -> autor decide, re-revisar se mudar
