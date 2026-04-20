---
name: audit-spec
description: Audita 1 RF/AC do docs/PRD-KALIBRIUM.md vs implementacao real no codigo (rota, controller, service, model, migration, tipo TS, componente). Identifica gap real vs gap aparente. Uso: /audit-spec "RF-XXX ou descricao".
argument-hint: "\"identificador do RF/AC ou descricao da funcionalidade\""
---

# /audit-spec

## Uso

```
/audit-spec "RF-042"
/audit-spec "emissao de NFS-e por OS concluida"
/audit-spec "AC-007 boleto PIX cobranca"
```

## Por que existe

`docs/PRD-KALIBRIUM.md` e a fonte funcional. O codigo e o juiz final (AGENTS.md). Esta skill audita 1 requisito/AC contra a implementacao real para confirmar gap genuino antes de qualquer trabalho de implementacao ou correcao.

> O `docs/raio-x-sistema.md` foi removido em 2026-04-10 por gerar falsos negativos. Esta skill e a substituta correta: grep no codigo antes de declarar gap.

## Quando invocar

- Antes de implementar feature listada no PRD (verificar se ja existe parcialmente)
- Antes de declarar gap em planejamento
- Em revisao de auditoria (`docs/audits/RELATORIO-AUDITORIA-SISTEMA.md`)
- Quando usuario pergunta "isso ja existe no sistema?"

## Pre-condicoes

- `docs/PRD-KALIBRIUM.md` acessivel
- Repositorio completo clonado
- `cd backend && composer install` ok

## O que faz — protocolo de verificacao

### 1. Localizar o RF/AC no PRD

```bash
# Grep por identificador ou texto
grep -n "RF-042" docs/PRD-KALIBRIUM.md
grep -n "NFS-e" docs/PRD-KALIBRIUM.md
```

Capturar:
- Texto literal do RF
- ACs associados (numerados)
- Status declarado no PRD (ok / gap / parcial)

### 2. Mapear cadeia esperada

Para cada feature, espera-se:

```
Migration -> Model -> FormRequest -> Controller -> Route
       |                                      |
     Policy                            Frontend (TS type -> API client -> componente)
       |                                      |
       Test (Feature + Unit)
```

### 3. Grep estruturado — backend

```bash
# Rota
grep -rn "<keyword>" backend/routes/

# Controller
ls backend/app/Http/Controllers/ | grep -i "<area>"

# Model
ls backend/app/Models/ | grep -i "<entidade>"

# Migration
ls backend/database/migrations/ | grep "<entidade>"

# Service
ls backend/app/Services/ | grep -i "<area>"

# FormRequest
ls backend/app/Http/Requests/ | grep -i "<area>"

# Policy
ls backend/app/Policies/ | grep -i "<entidade>"

# Test
ls backend/tests/Feature/ | grep -i "<area>"
```

### 4. Grep estruturado — frontend

```bash
# Tipo TypeScript
grep -rn "interface.*<Entidade>" frontend/src/types/

# API client
grep -rn "<endpoint>" frontend/src/api/

# Componente / pagina
ls frontend/src/pages/ | grep -i "<area>"
ls frontend/src/components/ | grep -i "<area>"
```

### 5. Verificar funcionalidade real

Se a cadeia parece presente, validar:

```bash
# Endpoint responde?
cd backend && php artisan route:list | grep "<rota>"

# Teste cobre?
cd backend && ./vendor/bin/pest --filter="<Area>"
```

### 6. Classificar resultado

| Status | Criterio |
|---|---|
| **Implementado** | Cadeia completa + teste verde + UI navegavel |
| **Parcial** | Backend ok mas falta UI; ou UI mock-up sem backend |
| **Gap real** | Nenhum elo encontrado |
| **Gap aparente** | PRD diz gap mas grep prova que existe (atualizar PRD) |

### 7. Reportar no formato Harness 6+1

```
1. Resumo — RF-XXX, status declarado vs status real
2. Arquivos encontrados — path:LN de cada elo (rota, controller, model, etc.)
3. Elos faltantes — lista priorizada
4. Comandos rodados — greps, route:list, pest filter
5. Resultado — tabela: cadeia esperada x encontrado
6. Riscos / proximos passos — implementar gap, atualizar PRD, ou fechar como ok
```

## Regras invioláveis

- **Codigo vence PRD.** Se grep mostra que existe e PRD diz que falta, atualizar PRD.
- **Proibido afirmar gap sem grep.** Memoria/suposicao nao basta.
- **Proibido ler `docs/.archive/`.** Conteudo superado, gera alucinacao.
- **Tenant safety:** se feature e multi-tenant, verificar `BelongsToTenant` no model.

## Erros e recuperacao

| Cenario | Acao |
|---|---|
| RF-XXX nao encontrado no PRD | Listar RFs proximos. Pedir clarificacao. |
| Cadeia parcial detectada | Reportar elo faltante exato. Sugerir `/fix` ou implementacao completa. |
| PRD desatualizado vs codigo | Reportar como `gap aparente`. Sugerir atualizacao de PRD. |
| Teste existe mas falha | Sugerir `/fix` para corrigir antes de fechar auditoria. |

## Handoff

- Implementado/ok -> fechar auditoria, atualizar PRD se necessario
- Gap real -> planejar implementacao completa (ponta a ponta)
- Parcial -> `/fix` ou plano de completacao
