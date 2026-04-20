---
description: Faz revisao de codigo de um PR ou diff local contra criterios do Kalibrium ERP (5 leis AGENTS.md, padroes de Controller/FormRequest, tenant safety, testes). Uso: /review-pr [<numero-do-PR>].
allowed-tools: Read, Bash, Grep, Glob
---

# /review-pr

## Uso

```
/review-pr           # revisa diff local (HEAD vs main)
/review-pr 123       # revisa PR #123 via gh
```

## Quando invocar

- Antes de mergear um PR.
- Apos terminar uma mudanca local, antes de abrir PR.
- Para revisar uma branch de outro dev.

## Pre-condicoes

- Diff local existente OU `gh` configurado para PR remoto.
- Suite de testes acessivel.

## O que faz

### 1. Coletar diff

- Local: `git diff main...HEAD`.
- PR remoto: `gh pr diff <num>`.
- `git diff main...HEAD --name-only` -> lista de arquivos.

### 2. Cross-check criterios

Avaliar contra:

- **5 leis AGENTS.md**: evidencia antes de afirmacao, causa raiz, completude end-to-end, tenant safety absoluto, sequenciamento + preservacao.
- **Iron Protocol H1-H8**: tenant_id nunca do body, escopo BelongsToTenant em tudo, migrations imutaveis, falha de verificacao bloqueante.
- **Padrao de Controllers**: FormRequest com authorize() real (nao `return true`), endpoints index com `paginate(15)`, eager loading com `with([...])`, tenant_id atribuido no controller.
- **Padrao de testes**: 5 cenarios obrigatorios, cross-tenant 404, validacao 422, assertJsonStructure().
- **Sincronia frontend/backend**: tipo TS atualizado quando campo muda.
- **Sem dead code**: nada comentado para "desativar", sem TODO/FIXME novos.

### 3. Rodar verificacoes mecanicas

```bash
cd backend && ./vendor/bin/pest --filter="<arquivos-do-PR>"
```

(Pode escalar para suite completa se mudanca for ampla.)

### 4. Apresentar findings

Cada finding com: severidade (critico/alto/medio/baixo) + file:LN + criterio violado + sugestao.

```
Revisao de codigo: REPROVADO

3 findings:

[critico] backend/app/Http/Controllers/Foo.php:42 (H1)
  tenant_id sendo lido de $request->input('tenant_id')
  -> usar $request->user()->current_tenant_id

[alto] backend/app/Http/Requests/FooRequest.php:18
  authorize() retorna true sem checar permissao
  -> usar $this->user()->can('foo.create')

[medio] frontend/src/types/foo.ts:15
  campo new_field do backend ausente no tipo TS
  -> adicionar new_field: string
```

### 5. Veredito

- `approved` -> pode mergear.
- `needs-changes` -> usuario corrige e roda `/review-pr` novamente.

## Erros e Recuperacao

| Cenario | Recuperacao |
|---|---|
| Diff vazio | Avisar e pedir referencia explicita. |
| `gh` nao configurado para PR remoto | Pedir ao usuario para passar diff local. |
| Suite quebra durante revisao | Reportar como finding critico (H8 - falha bloqueante). |

## Handoff

- `approved` -> mergear PR.
- `needs-changes` -> `/fix <finding>` -> re-rodar `/review-pr`.

## Referências

- `AGENTS.md` — 5 leis invioláveis, padrões Controller/FormRequest, proibições absolutas.
- `.agent/rules/iron-protocol.md` — regras H1/H2/H3/H7/H8.
- `.claude/agents/governance.md` — conformidade com padrões.
- `.claude/agents/architecture-expert.md` — acoplamento/camadas.
