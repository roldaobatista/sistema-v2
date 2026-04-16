# Harness Run: layer N cycle M

## Escopo

- `run_id`:
- camada:
- ciclo:
- `audit_mode`:
- `target_scope`:

## Proveniencia Git

- `base_commit`:
- `head_commit`:
- `approved_commit`:
- `working_tree_clean_at_start`:
- `working_tree_clean_at_close`:
- `dirty_paths_at_start`:

## Modo de Auditoria

- modo:
- auditores obrigatorios:
- justificativa para modo menor que `full`:

## Manifesto de Impacto

Fonte canonica: `impact-manifest.json`.

- manifesto gerado:
- `head_commit` do manifesto:
- superficies afetadas:
- riscos por path:
- premissas do manifesto:

## Proveniencia dos Agentes

- orquestrador auditou/corrigiu codigo: NAO
- contexto limpo exigido: SIM
- rodada de review:
- limite de rodadas da camada: 10

| Papel | Agent ID | Contexto limpo | Arquivo | Status |
| --- | --- | --- | --- | --- |
| arquitetura/dependencias | | | `auditor-architecture-dependencies.json` | |
| seguranca/tenant | | | `auditor-security-tenant.json` | |
| qualidade de codigo | | | `auditor-code-quality.json` | |
| testes/QA | | | `auditor-tests-verification.json` | |
| operabilidade/proveniencia | | | `auditor-ops-provenance.json` | |

## Auditores Executados

| Auditor | Arquivo | Status | Cegueiras declaradas | Verificacoes obrigatorias pendentes |
| --- | --- | --- | --- | --- |
| arquitetura/dependencias | `auditor-architecture-dependencies.json` | | | |
| seguranca/tenant | `auditor-security-tenant.json` | | | |
| qualidade de codigo | `auditor-code-quality.json` | | | |
| testes/QA | `auditor-tests-verification.json` | | | |
| operabilidade/proveniencia | `auditor-ops-provenance.json` | | | |

## Cobertura de Auditoria

Fonte canonica: `consolidated-findings.json` campo `audit_coverage`.

- quorum completo:
- agent_ids distintos:
- lacunas de cobertura:
- verificacoes obrigatorias nao executadas:
- `commands_log_present`:
- `impact_manifest_present`:

## Findings Consolidados

| ID | Root cause | Severidade | Bloqueia | Owner | Status |
| --- | --- | --- | --- | --- | --- |

## Divergencias e Resolucao

- fonte canonica usada:
- evidencia deterministica:
- conflitos remanescentes:

## Arquivos Alterados

Ver `changed-files.txt`.

## Comandos e Evidencias

Fonte canonica: `commands.log.jsonl`.

| Status | Comando | Exit code | Ambiente | Output hash |
| --- | --- | --- | --- | --- |

## Deploy

- `deployment_authorization`:
- backup:
- rollback:
- health checks:
- commit remoto apos deploy:

## Decisao Final

- decisao:
- motivo:
- `blocking_reason`:

## Riscos Remanescentes

-

## Proximo Passo

-
