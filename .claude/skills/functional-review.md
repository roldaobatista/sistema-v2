---
name: functional-review
description: Revisao funcional independente de uma mudanca recente no Kalibrium ERP. Avalia se a cadeia end-to-end (DB -> backend -> frontend) esta completa e sem regressao visivel. Uso: /functional-review [PR# ou area].
argument-hint: "[PR# ou descricao da mudanca]"
---

# /functional-review

## Uso

```
/functional-review #145
/functional-review "novo endpoint de boleto PIX"
/functional-review "modulo Calibracao"
```

## Por que existe

Testes verdes nao garantem que o usuario consegue usar a feature. Esta skill avalia do ponto de vista do produto: a cadeia DB -> backend -> frontend esta completa? UX consistente com o resto do ERP? Regras de negocio respeitadas?

## Quando invocar

- Apos `/verify` aprovado
- Antes de mergear PR
- Apos implementar feature listada no PRD
- Para auditar area inteira (ex: revisao funcional do modulo Financeiro)

## Pre-condicoes

- Mudanca identificada (PR ou commits especificos)
- `docs/PRD-KALIBRIUM.md` acessivel para checar ACs
- Suite da area verde

## O que faz — checklist

### 1. Mapear escopo da mudanca

```bash
git log --oneline -20
git diff main...HEAD --stat
gh pr view <PR#>
```

Listar:
- Commits incluidos
- Arquivos alterados (backend, frontend, migrations, testes)
- Issue/PR de referencia

### 2. Verificar cadeia end-to-end

Para cada feature tocada, conferir presenca de TODOS os elos:

| Camada | Esperado |
|---|---|
| Migration | Existe e foi rodada (`migrations` table) |
| Model | Tem `BelongsToTenant`, fillable correto, relacionamentos eager-loadable |
| FormRequest | `authorize()` com logica real (Spatie/Policy), regras `exists:` validam tenant |
| Controller | Usa `current_tenant_id`, paginacao no index, eager loading |
| Route | Registrada com middleware adequado |
| Policy | Existe se feature tem permissao granular |
| Test | Cobre 5 cenarios (sucesso/422/cross-tenant/403/edge) |
| Tipo TS | `interface` definida em `frontend/src/types/` |
| API client | Funcao em `frontend/src/api/` |
| Componente | Pagina/form/list em `frontend/src/pages/` ou `components/` |

### 3. Validar AC por AC

Para cada AC do PRD relacionado:

- AC-001: descricao literal — comportamento confirmado? (testar manualmente ou via Playwright)
- Status code retornado bate com o esperado?
- Texto exibido no frontend bate com PRD/design system?

### 4. UX e consistencia

- [ ] Mensagens de erro amigaveis (nao 401/500 cru)
- [ ] Loading state presente onde demora
- [ ] Pagination funcional
- [ ] Filtros respeitam tenant
- [ ] Botoes/labels consistentes com `docs/design-system/`

### 5. Regressao visivel

- [ ] Areas adjacentes ainda funcionam (smoke check via testes ou navegacao)
- [ ] Migration nao quebra dados existentes
- [ ] Permissions seeder atualizado se rota nova

### 6. Multi-tenant (nao-negociavel)

- [ ] Tenant_id nunca do body (H1)
- [ ] Toda query respeita `BelongsToTenant` (H2)
- [ ] Teste cross-tenant existe (404 em recurso de outro tenant)

### 7. Reportar no formato Harness 6+1

```
1. Resumo — escopo da mudanca e cobertura funcional vs ACs
2. Arquivos auditados — path por camada
3. Findings — gaps de cadeia, regressao, UX, ACs nao atendidos (severidade S1-S4)
4. Comandos rodados — git log/diff, pest da area, route:list
5. Resultado — tabela: AC x atendido?, camada x presente?
6. Riscos remanescentes — areas frageis, dependencias externas
7. (opcional) Como desfazer — se mudanca envolve migration/contrato API
```

## Severidade dos findings

- **S1 critico:** AC nao atendido, regressao, vazamento entre tenants
- **S2 alto:** elo da cadeia faltando (ex: rota sem teste, controller sem policy)
- **S3 medio:** UX divergente, mensagem tecnica em vez de amigavel
- **S4 baixo:** label inconsistente, falta de loading state

## Erros e recuperacao

| Cenario | Acao |
|---|---|
| Cadeia incompleta (ex: backend ok mas sem frontend) | Listar elo faltante, sugerir implementacao ponta a ponta |
| AC nao atendido | Reprovar revisao, sugerir `/fix` |
| Regressao visivel | Reprovar imediato, escalar para usuario |
| Nao consegue acessar PR | Pedir referencia (commits, branch, descricao) |

## Handoff

- Aprovado -> seguir para `/security-review` ou merge
- Reprovado S1/S2 -> `/fix` antes de prosseguir
- Reprovado S3/S4 -> criar issue de melhoria, decidir com usuario se bloqueia merge
