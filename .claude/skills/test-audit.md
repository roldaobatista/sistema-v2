---
name: test-audit
description: Audita cobertura e qualidade dos testes em uma area do Kalibrium ERP. Verifica assertJsonStructure, cross-tenant 404, validacao 422, permissao 403, edge cases. Identifica testes superficiais. Uso: /test-audit "area ou caminho".
argument-hint: "\"area (ex: Calibration), path (backend/tests/Feature/X) ou controller\""
---

# /test-audit

## Uso

```
/test-audit "Calibration"
/test-audit backend/tests/Feature/Schedule
/test-audit InvoiceController
```

## Por que existe

Testes verdes nao significam testes bons. Esta skill audita se a area tem cobertura adequada nos 5 cenarios obrigatorios e nao contem anti-patterns que escondem bugs reais.

## Quando invocar

- Antes de declarar feature "pronta"
- Apos `/fix` para garantir teste de regressao real
- Em revisao de PR para validar que testes adicionados sao serios
- Periodicamente em areas criticas (multi-tenant, financeiro, calibracao)

## Pre-condicoes

- Backend rodavel (`cd backend && ./vendor/bin/pest --version`)
- Diretorio de testes existe (`backend/tests/Feature/<Area>` ou `backend/tests/Unit/<Area>`)
- PRD relevante carregado (`docs/PRD-KALIBRIUM.md` para conferir ACs)

## O que verifica — checklist

### A) Os 5 cenarios obrigatorios

Para cada controller/endpoint na area:

- [ ] **Sucesso CRUD** — happy path com `assertStatus(200|201)` e `assertJsonStructure([...])`
- [ ] **Validacao 422** — input invalido retorna 422 + estrutura de erros
- [ ] **Cross-tenant 404** — recurso de outro tenant_id retorna 404 (NUNCA 403/200)
- [ ] **Permissao 403** — usuario sem permissao retorna 403
- [ ] **Edge cases** — soft-delete, relacionamentos vazios, paginacao no limite

### B) Qualidade das assertions

- [ ] Usa `assertJsonStructure([...])`, nao so `assertStatus`
- [ ] Verifica `assertDatabaseHas` / `assertDatabaseMissing` para side effects
- [ ] Mock so de IO externo (HTTP, fila, e-mail) — nunca de logica de dominio
- [ ] Sem `assertTrue(true)` ou testes sem assertion relevante

### C) Anti-patterns proibidos

- [ ] Sem `markTestSkipped`/`markTestIncomplete` injustificado
- [ ] Sem `try/catch` engolindo excecao esperada
- [ ] Sem dependencia de timestamp/UUID aleatorio (fragil)
- [ ] Sem assertion alterada para aceitar valor errado
- [ ] Sem teste comentado

### D) Padrao adaptativo de quantidade

- Feature com logica complexa: **8+ testes/controller**
- CRUD simples: **4-5 testes** (sucesso + 422 + cross-tenant)
- Bug fix: **regressao + afetados**
- **Menos de 4 testes = SEMPRE insuficiente** (rarissima excecao justificada)

### E) Multi-tenant safety (H1, H2)

- [ ] Toda criacao de recurso usa `current_tenant_id`, nunca body
- [ ] Toda query respeita `BelongsToTenant` global scope
- [ ] Existe ao menos 1 teste cross-tenant por endpoint que toca tenant data

## O que faz — passos

### 1. Listar testes da area

```bash
ls backend/tests/Feature/<Area>/
ls backend/tests/Unit/<Area>/
```

### 2. Listar controllers/services correspondentes

```bash
ls backend/app/Http/Controllers/<Area>/
ls backend/app/Services/<Area>/
```

Detectar gaps: controller existe mas teste nao.

### 3. Para cada arquivo de teste, ler e classificar

Para cada `*Test.php`, marcar:
- Cenarios cobertos (sucesso/422/cross-tenant/403/edge)
- Quantidade de testes
- Anti-patterns detectados
- Qualidade das assertions

### 4. Rodar a suite da area

```bash
cd backend && ./vendor/bin/pest tests/Feature/<Area>
```

Confirmar verde antes de auditar (testes vermelhos invalidam auditoria).

### 5. Reportar no formato Harness 6+1

Output:

```
1. Resumo — N testes, M controllers cobertos, K gaps detectados
2. Arquivos alterados — nenhum (auditoria); ou path:LN dos pendentes
3. Motivo tecnico — gaps por cenario/controller
4. Comandos rodados — pest da area + leitura de testes
5. Resultado — output real do pest + tabela de cobertura por cenario
6. Riscos remanescentes — controllers sem teste, anti-patterns, areas frageis
7. (opcional) Plano de correcao — quais testes criar e em qual ordem
```

## Regras invioláveis

- **Proibido fechar OK com gaps detectados.** Se faltam testes, reportar findings e listar.
- **Proibido aceitar `assertTrue(true)`.** Reescrever ou marcar como insuficiente.
- **Cross-tenant 404 e nao-negociavel** em endpoint multi-tenant.

## Erros e recuperacao

| Cenario | Acao |
|---|---|
| Suite da area falha | Reportar — auditoria nao roda em base vermelha. Sugerir `/fix` antes. |
| Controller sem teste algum | Listar como gap P0. Sugerir `/draft-tests` para criar. |
| Teste superficial (`assertTrue(true)`) | Listar com `path:LN`. Sugerir reescrita prioritaria. |
| Area tem >50 testes | Amostrar (10 mais criticos + 10 aleatorios). Reportar limitacao. |

## Handoff

- Aprovado -> area OK para release
- Reprovado -> `/draft-tests` para gaps, depois `/fix` se houver bugs revelados
