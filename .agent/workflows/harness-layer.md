---
description: Execute an autonomous harness cycle by dependency layer, with auditor contracts, consolidation, repair, reauditing, verification, and gated deploy.
---

# Harness Layer Workflow

Use este workflow quando o pedido for iniciar, continuar, auditar, corrigir ou fechar uma camada do harness autonomo.

## Entrada

Argumentos esperados:

```text
$ARGUMENTS
```

Interpretacao padrao:

- `layer`: camada alvo `0..7`.
- `audit_mode`: `full`, salvo se o escopo permitir `targeted` ou `verification_only`.
- `target_scope`: `layer` por padrao; usar `module` ou `finding_set` quando o usuario ou relatorio consolidado restringir a rodada.

## Boot Obrigatorio

Carregar nesta ordem:

1. `AGENTS.md`.
2. `.agent/rules/iron-protocol.md`.
3. `.agent/rules/harness-engineering.md`.
4. `.agent/rules/test-policy.md`.
5. `.agent/rules/test-runner.md`.
6. `.agent/rules/kalibrium-context.md`.
7. `docs/harness/autonomous-orchestrator.md`.
8. `docs/harness/dependency-layers.md`.
9. `docs/harness/harness-state.json`.
10. Docs canonicas da camada alvo e dependencias anteriores.

Se qualquer fonte obrigatoria estiver ausente, registrar `blocked_missing_context`.

## Comandos do CLI

Bootstrap inicial, somente quando `docs/harness/harness-state.json` ainda nao existir:

```bash
node scripts/harness-cycle.mjs init
```

Abrir ciclo:

```bash
node scripts/harness-cycle.mjs start --layer N --mode full
```

Gerar manifesto de impacto antes de acionar auditores:

```bash
node scripts/harness-cycle.mjs generate-impact --run-id RUN_ID
```

Estado:

```bash
node scripts/harness-cycle.mjs status
```

Validar invariantes estruturais do manifesto:

```bash
node scripts/harness-cycle.mjs validate
```

`validate` tambem valida o schema formal de `harness-state.json`.

Registrar fase:

```bash
node scripts/harness-cycle.mjs record --run-id RUN_ID --status verification
```

Registrar evidencia de verificacao:

```bash
node scripts/harness-cycle.mjs record-command --run-id RUN_ID --command "COMANDO" --status passed --exit-code 0
```

Fechar ciclo:

```bash
node scripts/harness-cycle.mjs close --run-id RUN_ID --status approved
```

Fechamento bloqueado ou escalado exige motivo explicito:

```bash
node scripts/harness-cycle.mjs close --run-id RUN_ID --status blocked --reason "MOTIVO"
```

O fechamento `approved` exige `impact-manifest.json`, `consolidated-findings.json`, `auditor-*.json` e `commands.log.jsonl` validos pelos schemas formais, consolidado aprovado com `audit_coverage`, auditores obrigatorios aprovados com `audit_limitations`, evidencia essencial `passed`, zero `harness_errors`, zero findings bloqueantes e zero verificacoes obrigatorias pendentes.

Remover lock manualmente e operacao de bloqueio/escalacao, sempre com motivo:

```bash
node scripts/harness-cycle.mjs unlock --run-id RUN_ID --reason "MOTIVO"
```

Autorizar deploy pela LLM CLI:

```bash
node scripts/harness-cycle.mjs authorize-deploy --run-id RUN_ID --target production --commit COMMIT --migration-diff-checked true
```

Executar deploy real somente apos autorizacao registrada:

```bash
node scripts/harness-cycle.mjs deploy --run-id RUN_ID
```

## Auditores

Em `full audit`, disparar cinco auditorias independentes:

| Auditor | Escopo principal | Deve ignorar como finding principal |
| --- | --- | --- |
| `architecture-dependencies` | fronteiras de camada, ownership, cascata, fontes canonicas | estilo superficial sem impacto |
| `security-tenant` | auth, permissao, tenant isolation, dados sensiveis | cosmetica React |
| `code-quality` | causa raiz, padroes Laravel/React, completude ponta a ponta | deploy real |
| `tests-verification` | testes obrigatorios, regressao, comandos determinantes | opiniao sem comando ou evidencia |
| `ops-provenance` | lock, estado, git, comandos, deploy, rollback, ambiente | regra de negocio de controller sem evidencia operacional |

Cada auditor deve produzir `auditor-*.json` conforme `docs/harness/schemas/auditor-output.schema.json`.

Cada auditor deve preencher `audit_limitations` com `not_inspected`, `assumptions` e `required_verifications_not_executed`. Auditor aprovado deve deixar `required_verifications_not_executed` vazio.

Arquivos canonicos esperados em `full audit`:

- `impact-manifest.json`
- `auditor-architecture-dependencies.json`
- `auditor-security-tenant.json`
- `auditor-code-quality.json`
- `auditor-tests-verification.json`
- `auditor-ops-provenance.json`

## Consolidacao

O consolidador deve gerar `consolidated-findings.json` conforme `docs/harness/schemas/consolidated-findings.schema.json`.

Regras:

- Agrupar por `root_cause_key`, nao por arquivo.
- Preservar evidencias divergentes.
- Se houver conflito de severidade, manter a maior severidade provisoria.
- Se houver conflito de escopo, aplicar a regra mais restritiva.
- Se nao houver fonte canonica ou evidencia deterministica para resolver, usar `blocked_conflict`.
- Nao mudar fonte canonica no mesmo ciclo para aprovar a propria correcao funcional sem decisao humana registrada.
- Registrar `audit_coverage` com cinco `executed_auditors`, cinco `distinct_agent_ids` correspondentes exatamente aos auditores executados, `quorum_met`, `coverage_gaps`, `unresolved_required_verifications`, `commands_log_present` e `impact_manifest_present`.

## Correcao

O corretor so pode atuar sobre findings consolidados e corrigiveis.

Guardrails:

- Maximo padrao: 8 arquivos alterados por ciclo.
- Maximo padrao fora da camada: 5 arquivos.
- Arquivos compartilhados exigem justificativa de ownership e verificacao adicional.
- `verification_only` e proibido para auth, tenant, permissao, contrato API, migration, fonte canonica, deploy real, arquivo compartilhado ou finding `critical/high`.
- Se a correcao exigir acao destrutiva, migration arriscada, alteracao global de auth/permissao ou producao, marcar bloqueio e pedir decisao humana.

## Verificacao

Registrar comandos em `commands.log.jsonl`, nao em markdown livre.

Estados de comando permitidos:

- `passed`
- `failed`
- `not_executed`
- `blocked_environment`
- `blocked_policy`
- `waived_by_policy`
- `replaced_by_equivalent`

Comando equivalente exige:

- `original_command`
- `effective_command`
- `canonical_basis`
- `approved_by`
- `justification`

Comando essencial para aprovacao nao pode ser dispensado por `waived_by_policy`; usar equivalente ou bloquear.

## Reauditoria

Rodar no maximo 10 ciclos por camada antes de escalonar.

Politica:

- `critical/high`: reauditoria completa.
- `medium` bloqueante localizado: `targeted` com consolidacao final.
- `low` ou docs neutras: `verification_only`, se nao tocar zona proibida.
- Se houver falha interna do harness, bloquear o ciclo como problema do harness, nao como falha funcional da camada.
- Apos qualquer correcao, regenerar `impact-manifest.json` antes de chamar os auditores da nova rodada.

## Deploy

Deploy real pertence a camada 7.

A LLM CLI pode executar deploy quando:

- `deployment_authorization.authorized == true`.
- a run ativa da camada 7 possui lock valido em `docs/harness/.lock`.
- `deployment_authorization.run_id`, `layer` e `cycle` batem com a run ativa.
- `deployment_authorization.authorized_head_commit` bate com o HEAD atual.
- repositorio Git valido com HEAD conhecido.
- `target_environment`, `target_commit`, `deploy_command` e `rollback_command` existem.
- `requires_backup == true` em producao.
- `migration_diff_checked == true` em producao.
- `required_health_checks` nao esta vazio em producao.
- `deploy_command` nao contem `$`, backticks, `migrate:fresh`, `migrate:reset`, `migrate:refresh`, `migrate:rollback` ou `db:wipe`.
- se `allow_migrations == false`, `deploy_command` tambem nao contem `migrate`, subcomando `migrate:*`, `--migrate` ou `--migrate=*`.
- As regras de `.cursor/rules/deploy-production.mdc` e `.cursor/rules/migration-production.mdc` foram carregadas.
- O comando e o resultado serao registrados em `commands.log.jsonl`.
- Evidencias de backup, health checks e commit remoto precisam ser emitidas pelo `deploy_command` autorizado ou registradas explicitamente via `record-command`/relatorio antes do fechamento.

Se qualquer pre-condicao falhar, registrar `blocked_policy`.

## Saida Final

Responder no formato HARNESS ENGINEERING quando houver alteracao de codigo ou artefato:

1. Resumo do problema.
2. Arquivos alterados.
3. Motivo tecnico de cada alteracao.
4. Testes executados.
5. Resultado dos testes.
6. Riscos remanescentes.
7. Como desfazer, quando houver deploy/infra/migration/contrato/risco alto.
