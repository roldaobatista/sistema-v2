# Autonomous Harness Orchestrator

**Protocol version:** 0.1.0
**Dependency matrix version:** 0.1.0

Este documento e o contrato operacional canonico do harness autonomo do Kalibrium ERP.

## Principio

O harness executa auditoria, correcao, reauditoria e verificacao por camada de dependencia. A autonomia significa executar um workflow previsivel dentro de guardrails versionados, nao inventar criterio ad hoc.

O orquestrador nao e auditor, corretor nem verificador. Ele coordena estado, delega trabalho, valida artefatos e decide a proxima transicao com base em relatorios de agentes/subagentes independentes. Qualquer auditoria ou correcao feita pelo proprio orquestrador invalida o ciclo.

O quorum de cinco auditores e uma malha de reducao de risco, nao uma prova formal de ausencia de bugs. Cada ciclo deve registrar explicitamente o raio de impacto, as limitacoes de cada auditor e a evidencia deterministica usada para sustentar a decisao.

Precedencia:

1. `AGENTS.md` e Iron Protocol.
2. `.agent/rules/harness-engineering.md`.
3. `.agent/rules/test-policy.md` e `.agent/rules/test-runner.md`.
4. Este documento.
5. `docs/harness/dependency-layers.md`.

## Boot de Camada

Para `iniciar camada N`, o orquestrador carrega:

1. `AGENTS.md`.
2. `.agent/rules/iron-protocol.md`.
3. `.agent/rules/harness-engineering.md`.
4. `.agent/rules/test-policy.md`.
5. `.agent/rules/test-runner.md`.
6. `.agent/rules/kalibrium-context.md`.
7. `docs/harness/autonomous-orchestrator.md`.
8. `docs/harness/dependency-layers.md`.
9. `docs/harness/harness-state.json`.
10. docs canonicas da camada alvo e dependencias anteriores.
11. ultimo diretorio de ciclo da camada, quando existir.

Se matriz, manifesto ou criterios canonicos estiverem ausentes, registrar `blocked_missing_context`.

## Perfis

| Perfil | Mandato | Escrita |
| --- | --- | --- |
| Orquestrador | coordena ciclo, cria prompts minimos, dispara agentes/subagentes, consolida decisoes e roteia | proibido auditar codigo, corrigir codigo ou fabricar parecer de auditor |
| Auditor read-only | audita com contexto limpo e gera exatamente um `auditor-*.json` | proibido |
| Consolidador | deduplica e decide proxima acao | proibido |
| Corretor | corrige findings consolidados | permitido dentro do escopo |
| Verificador | executa comandos e evidencia | nao corrige |
| LLM CLI | executa comandos autorizados, inclusive deploy autorizado | apenas via comandos e guardrails |

## Fluxo Multiagente Obrigatorio por Camada

Toda camada deve passar pelo fluxo abaixo. Nao existe aprovacao de camada por auditoria feita pelo orquestrador.

1. O orquestrador inicia a camada e cria uma run/ciclo.
2. O orquestrador gera `impact-manifest.json` com `node scripts/harness-cycle.mjs generate-impact --run-id RUN_ID` antes de disparar auditores.
3. O orquestrador dispara cinco auditores read-only diferentes, cada um com contexto limpo da conversa principal.
4. Cada auditor recebe somente o contexto minimo necessario: `AGENTS.md` compacto, fontes canonicas da camada, matriz de dependencias, manifesto da run, `impact-manifest.json` e escopo do seu perfil.
5. Cada auditor procura problemas, erros, inconsistencias, pendencias, risco de seguranca, regressao, quebra de contrato e lacunas de teste dentro da camada.
6. Cada auditor escreve somente o seu `auditor-*.json`, com `agent_provenance.context_mode = "clean"`, `agent_provenance.orchestrator_generated = false` e `audit_limitations` declarando `not_inspected`, `assumptions` e `required_verifications_not_executed`.
7. O consolidador deduplica findings dos cinco auditores e produz `consolidated-findings.json`, incluindo `audit_coverage` com auditores executados, agent_ids distintos correspondentes exatamente aos auditores reais, lacunas de cobertura e verificacoes obrigatorias ainda nao executadas.
8. Se o consolidado nao for `approved/approve`, o orquestrador dispara um corretor separado, com mandato de escrita restrito aos findings consolidados.
9. Se o corretor alterar qualquer arquivo, registrar `changed-files.txt`, regenerar `impact-manifest.json`, registrar evidencias de comandos e retornar para reauditoria pelos cinco auditores, novamente com contexto limpo.
10. O ciclo se repete ate aprovacao ou ate a camada atingir 10 rodadas.
11. Na rodada 10 ainda reprovada, marcar `escalated` com motivo explicito e aguardar decisao humana.

Os cinco auditores obrigatorios por rodada completa sao:

| Auditor | Arquivo | Foco obrigatorio |
| --- | --- | --- |
| `architecture-dependencies` | `auditor-architecture-dependencies.json` | dependencias de camada, ownership, contratos canonicos e efeitos em cascata |
| `security-tenant` | `auditor-security-tenant.json` | auth, tenant isolation, permissoes, dados sensiveis e superficies publicas |
| `code-quality` | `auditor-code-quality.json` | qualidade, padroes do projeto, inconsistencias, bugs e manutencao |
| `tests-verification` | `auditor-tests-verification.json` | cobertura, lacunas de teste, comandos obrigatorios, evidencias e regressao |
| `ops-provenance` | `auditor-ops-provenance.json` | proveniencia, Git, lock, comandos, deploy/rollback quando aplicavel e integridade dos artefatos |

Os cinco auditores devem ser instancias/agentes diferentes. Reutilizar a mesma instancia, herdar historico da conversa do orquestrador ou preencher artefatos manualmente como substituto de agente e `blocked_policy`.

## Contexto Limpo dos Agentes

Contexto limpo significa:

- `fork_context=false` ou equivalente ao disparar subagente;
- prompt autocontido com escopo, fontes canonicas e formato de saida;
- sem historico completo da conversa principal;
- sem conclusoes preliminares do orquestrador sobre aprovacao/reprovacao;
- sem permissao de editar para auditores, consolidadores e verificadores;
- identificador distinto por agente registrado em `agent_provenance.agent_id`.

Se a ferramenta disponivel nao permitir contexto limpo verificavel, o ciclo deve ser marcado como `blocked_environment`, nao aprovado por aproximacao.

## Limitacoes Obrigatorias dos Auditores

Todo `auditor-*.json` deve conter `audit_limitations`:

- `not_inspected`: superficies, modulos, dependencias transitiveis, ambientes ou integracoes que o auditor nao conseguiu inspecionar.
- `assumptions`: premissas usadas para decidir, incluindo dependencia em `impact-manifest.json`, comandos ja registrados ou escopo informado.
- `required_verifications_not_executed`: comandos, reexecucoes, smokes ou checks que o auditor considera obrigatorios e que ainda nao aparecem como evidencia.

Auditor com `status=approved` deve deixar `required_verifications_not_executed` vazio. Se houver qualquer item nessa lista, o fechamento da camada como `approved` e bloqueado ate a evidencia existir ou ate o consolidado escalar formalmente o bloqueio.

## Run ID

`run_id` canonico: `YYYYMMDDTHHmmssZ-layer-N-cycle-M`, sempre em UTC.

Regras:

- `cycle` e incremental por camada.
- ciclo fechado nunca e reaberto.
- nova tentativa cria novo ciclo com `supersedes_run_id` quando aplicavel.
- relatorio final fechado e imutavel.
- artefato invalido antes do fechamento pode ser regenerado com `artifact_revision` incrementado.

## Estados

Estados de camada:

- `not_started`
- `in_progress`
- `approved`
- `blocked`
- `escalated`

Fases globais:

- `idle`
- `audit`
- `fix`
- `reaudit`
- `verification`
- `closed`

Estados de ciclo:

- `idle`
- `audit`
- `fix`
- `reaudit`
- `verification`
- `closed`
- `blocked`
- `escalated`

Transicoes de camada validas:

- `not_started -> in_progress`
- `in_progress -> approved`
- `in_progress -> blocked`
- `in_progress -> escalated`
- `blocked -> in_progress`
- `blocked -> escalated`
- `escalated -> in_progress` somente com `start --human-decision "<referencia da decisao humana>"`
- `approved -> in_progress` somente com `start --invalidation-reason "<matriz, protocolo ou dependencia anterior mudou>"`

Transicoes de ciclo validas:

| Transicao | Regra |
| --- | --- |
| `idle -> audit` | run criada, lock criado, `base_commit` capturado |
| `audit -> fix` | ha finding corrigivel e nenhum bloqueio terminal |
| `audit -> verification` | os cinco auditores independentes aprovaram e o consolidado esta `approved/approve` |
| `audit -> blocked` | ha bloqueio sem acao local segura |
| `fix -> verification` | corretor registrou `changed-files.txt` e `head_commit` |
| `verification -> fix` | comando falhou por codigo corrigivel |
| `verification -> reaudit` | comando passou, mas houve correcao ou mudanca que exige nova rodada dos cinco auditores |
| `verification -> blocked` | comando nao executou por ambiente, politica ou contexto |
| `verification -> closed` | evidencias e manifesto persistidos |
| `reaudit -> fix` | novo finding corrigivel |
| `reaudit -> verification` | reauditoria aprovou auditores obrigatorios |
| `reaudit -> escalated` | ciclo 10, conflito insoluvavel ou acao humana requerida |
| `closed -> idle` | lock removido e manifesto persistido |

## Invariantes do Manifesto

- Se `current_phase != "idle"`, `active_layer`, `active_cycle` e `active_run_id` nao podem ser `null`.
- Se `cycle_state == "idle"`, `current_phase` deve ser `idle`.
- Se `lock.active == true`, `lock.run_id == active_run_id`.
- Se ha run ativa, `docs/harness/.lock` deve existir e apontar para o mesmo `run_id`.
- Camada `approved` exige `approved_report_provenance.report`, `approved_commit` e `approved_at`.
- `blocking_reason != null` exige `cycle_state` `blocked` ou `escalated`, ou `current_phase` `closed` com decisao nao aprovada.
- `deployment_authorization.authorized == true` exige `run_id`, `layer`, `cycle`, `authorized_head_commit`, alvo, commit, comandos, autorizador, data e `migration_diff_checked`.
- Producao exige `requires_backup == true`, `migration_diff_checked == true` e health checks nao vazios.

## Atomicidade e Concorrencia

A primeira implementacao permite uma unica run ativa global.

Regras:

- lock canonico: `docs/harness/.lock`;
- `start` falha se `current_phase != idle` ou se houver lock ativo;
- `start` falha para camada `approved` sem `--invalidation-reason` e para camada `escalated` sem `--human-decision`;
- `start` cria o lock antes dos artefatos da run e falha se o arquivo de lock ja existir;
- escritas do manifesto usam arquivo temporario e rename atomico;
- `record`, `close`, `authorize-deploy`, `deploy`, `record-deploy`, `record-command` e regeneracao exigem `run_id` igual ao lock ativo no manifesto e no arquivo `.lock`;
- lock orfao so pode ser removido por comando explicito com `reason`, registro no relatorio e ciclo marcado como bloqueado quando houver run ativa;
- run fechada nao pode ser sobrescrita.

## Aprovacao

Uma camada so pode virar `approved` quando:

- dependencias anteriores estao `approved`;
- a rodada de aprovacao esta em modo `full`;
- cinco auditores read-only diferentes foram executados com contexto limpo;
- `impact-manifest.json` existe, corresponde ao HEAD atual e foi considerado pelos auditores;
- nenhum artefato de auditor foi criado pelo orquestrador como substituto de agente;
- todos os auditores declararam `audit_limitations` e nao ha `required_verifications_not_executed` pendente em auditor aprovado;
- `consolidated-findings.json` inclui `audit_coverage.quorum_met=true`, cinco `executed_auditors`, cinco `distinct_agent_ids`, `commands_log_present=true`, `impact_manifest_present=true` e nenhuma `unresolved_required_verifications`;
- nao ha `critical`/`high` bloqueante aberto;
- `medium` bloqueante foi corrigido, reclassificado ou escalado;
- comandos obrigatorios passaram com evidencia real;
- os cinco auditores obrigatorios aprovaram;
- relatorio consolidado fechou decisao explicita;
- nao ha falha interna do harness aberta;
- `approved_commit == head_commit` verificado no fechamento;
- manifesto aponta para o relatorio final.

O CLI so pode persistir `approved` quando os artefatos canonicos concordarem e passarem nos schemas formais: `consolidated-findings.json` deve estar `approved/approve`, os cinco auditores obrigatorios devem estar `approved`, cada auditor deve declarar `agent_provenance.context_mode = "clean"`, `agent_provenance.orchestrator_generated = false` e `audit_limitations`, `impact-manifest.json` deve estar atualizado para o HEAD, `commands.log.jsonl` deve conter evidencia essencial `passed` e nao pode haver `harness_errors`, findings bloqueantes ou verificacoes obrigatorias pendentes.

Entradas de `commands.log.jsonl` tambem devem registrar `actor_role`, `agent_id`, `review_round`, `context_fingerprint` e `source_bundle_hash`. Comando essencial de aprovacao nao pode ter `actor_role = "orchestrator"`; verificacao deve vir de verificador, auditor, corretor autorizado ou LLM CLI conforme o mandato.

Entradas de `commands.log.jsonl` tambem devem registrar `environment` e `output_hash`. O `environment` identifica o runtime local usado para produzir a evidencia; o `output_hash` permite detectar divergencia entre o trecho persistido e a evidencia registrada.

Fechamento `blocked` ou `escalated` exige `reason` explicito no historico da run.

## Modos de Auditoria

| Modo | Uso |
| --- | --- |
| `full` | unico modo permitido para aprovar camada; exige cinco auditores independentes com contexto limpo |
| `targeted` | correcao localizada de `medium` ou dominio claro |
| `verification_only` | `low`, docs neutras ou mudanca minima sem comportamento |

`targeted` e `verification_only` sao modos auxiliares. Eles podem gerar evidencia ou preparar uma correcao, mas nao podem fechar uma camada como `approved`.

`verification_only` e proibido para auth, tenant, permissao, contrato API, migration, fonte canonica, deploy real, arquivo compartilhado ou qualquer finding vindo de `critical/high`.

## Consolidacao

O consolidador agrupa findings por `root_cause_key`, preserva evidencias, usa maior severidade provisoria e registra conflitos. Se nao houver fonte canonica ou evidencia deterministica para resolver conflito, usar `blocked_conflict`.

O consolidador tambem deve registrar `audit_coverage`:

- `executed_auditors`: cinco auditores obrigatorios com `auditor`, `agent_id` e `artifact`;
- `distinct_agent_ids`: cinco ids distintos correspondentes exatamente aos `agent_id` dos cinco `auditor-*.json`;
- `quorum_met`: `true` somente quando o quorum completo foi executado;
- `coverage_gaps`: lacunas conhecidas que nao bloqueiam, mas devem ficar visiveis;
- `unresolved_required_verifications`: qualquer verificacao obrigatoria ainda nao executada;
- `commands_log_present`: `true` quando ha `commands.log.jsonl` com evidencia;
- `impact_manifest_present`: `true` quando `impact-manifest.json` valido foi usado.

Ordem de decisao:

1. Fonte canonica explicita.
2. Evidencia deterministica executada.
3. Maior severidade temporaria.
4. Regra mais restritiva para escopo.
5. Escalacao para seguranca, tenant, producao ou dados quando a fonte nao resolver.

## Deploy pela LLM CLI

A LLM CLI pode executar deploy quando `deployment_authorization.authorized == true` e houver run ativa da camada 7.

Pre-condicoes:

- run ativa de camada 7 com lock valido no manifesto e em `docs/harness/.lock`;
- `deployment_authorization.run_id`, `layer` e `cycle` batem com a run ativa;
- `deployment_authorization.authorized_head_commit` bate com o HEAD atual;
- repositorio Git valido com HEAD conhecido;
- `target_environment` definido;
- `target_commit` definido e conferido contra HEAD/branch remota;
- `deploy_command` definido;
- `deploy_command` deve usar caminho de chave SSH ja expandido; exemplos com variavel de ambiente PowerShell para USERPROFILE ou outra expansao com `$` sao invalidos para o CLI;
- `rollback_command` definido;
- `requires_backup == true` para producao;
- `migration_diff_checked == true` para producao;
- `required_health_checks` nao vazio para producao;
- `deploy_command` com `$`, backticks, `migrate:fresh`, `migrate:reset`, `migrate:refresh`, `migrate:rollback` ou `db:wipe` bloqueia sempre;
- se `allow_migrations == false`, `deploy_command` com `migrate`, subcomando `migrate:*`, `--migrate` ou `--migrate=*` tambem bloqueia;
- se `allow_migrations == true`, regras de migration de producao continuam obrigatorias.

Durante e apos deploy:

- registrar comando e saida em `commands.log.jsonl`;
- o `deploy_command` autorizado ou comandos registrados separadamente devem produzir evidencia de backup, health checks e commit remoto quando aplicavel;
- se essas evidencias nao forem registradas para producao, o consolidado deve marcar `blocked_policy`;
- nao gravar segredos, `.env`, tokens ou dumps.

Qualquer divergencia vira `blocked_policy`.
