# Autonomous Harness Orchestrator Design

**Data:** 2026-04-14
**Status:** aprovado para planejamento de implementacao
**Projeto:** Kalibrium ERP

## Objetivo

Criar um harness autonomo para auditoria e correcao incremental do sistema por camadas de dependencia, reduzindo a interacao humana ao minimo sem violar o Iron Protocol, o Harness Engineering ou as regras de seguranca de producao.

O operador humano deve poder pedir algo como "iniciar camada 0" e o orquestrador deve executar ciclos de auditoria, correcao e reauditoria ate aprovar a camada ou escalar bloqueadores apos 10 ciclos.

## Escopo

O primeiro entregavel sera um harness persistente no repositorio, composto por:

- protocolo operacional versionado;
- matriz de camadas e dependencias;
- workflow de agente para iniciar e controlar ciclos por camada;
- template de relatorio por ciclo;
- CLI local leve apenas para tarefas deterministicas de relatorio e execucao de comandos.

O CLI nao sera o cerebro do sistema. Ele nao tentara substituir o orquestrador nem simular capacidade de spawnar subagentes/modelos. A orquestracao de auditores e corretores continua sob responsabilidade da plataforma de IA.

Autonomia, neste desenho, significa executar um workflow previsivel dentro de guardrails versionados. Nao significa julgamento irrestrito nem permissao para improvisar criterios. O sistema depende de criterios canonicos humanos previamente versionados.

Comandos minimos esperados:

- `node scripts/harness-cycle.mjs start --layer N --mode full|targeted|verification_only`: cria a rodada, o lock e os artefatos iniciais.
- `node scripts/harness-cycle.mjs record --run-id <run_id> --status <status>`: registra status consolidado da rodada ativa.
- `node scripts/harness-cycle.mjs record-command --run-id <run_id> --command <cmd> --status <status>`: registra evidencia em `commands.log.jsonl`; comando equivalente exige `--justification`.
- `node scripts/harness-cycle.mjs close --run-id <run_id> --status approved|blocked|escalated`: encerra a rodada com decisao final; `blocked` e `escalated` exigem `--reason`.
- `node scripts/harness-cycle.mjs status`: imprime o ultimo estado conhecido a partir do manifesto.
- `node scripts/harness-cycle.mjs authorize-deploy --run-id <run_id> --target production --commit <sha> --migration-diff-checked true`: registra autorizacao humana para deploy pela LLM CLI, sem executar deploy.
- `node scripts/harness-cycle.mjs deploy --run-id <run_id>`: executa o deploy autorizado apenas na camada 7 com lock valido.
- `node scripts/harness-cycle.mjs record-deploy --run-id <run_id> --status <status>`: registra evidencia de deploy executado pela LLM CLI.
- `node scripts/harness-cycle.mjs unlock --run-id <run_id> --reason <motivo>`: remove lock manualmente, registra motivo e bloqueia a run ativa.

Limite do CLI: ele pode criar estrutura, registrar eventos, validar schema basico, ler manifesto, imprimir status e rejeitar transicoes formais invalidas. Ele pode validar schema, coerencia estrutural e transicao sintatica do estado; nao deve inferir se uma dependencia foi aprovada semanticamente, se uma justificativa e valida no merito, nem se um finding deve bloquear. A decisao de `approved`, `blocked` ou `escalated` vem do orquestrador e o CLI apenas persiste ou valida consistencia estrutural.

Excecao de execucao: a LLM CLI pode executar deploy quando houver `deployment_authorization` valida no manifesto e houver run ativa da camada 7. Mesmo assim, ela nao decide que o deploy e seguro; ela executa o procedimento autorizado, registra evidencias e para em qualquer divergencia.

Politica inicial de concorrencia: apenas uma run ativa global por workspace. `start` deve falhar se `current_phase != idle` ou se existir lock ativo de outra run.

## Nao Escopo

- Nao executar auditoria completa de todas as camadas nesta entrega inicial.
- Nao executar deploy real nesta entrega documental. O contrato pode permitir deploy futuro pela LLM CLI quando houver autorizacao registrada.
- Nao mexer em banco real ou migrations destrutivas sem regra de producao, backup e autorizacao explicita.
- Nao criar automacao que masque testes ou aceite falhas.
- Nao adicionar dependencia externa sem necessidade e sem ADR.
- Nao substituir o Iron Protocol; apenas integrar o novo harness a ele quando necessario.

## Relacao com Iron Protocol e Harness Engineering

O novo harness sera uma extensao operacional do fluxo atual, nao um protocolo concorrente.

Regras de precedencia:

1. Iron Protocol continua sendo a regra de seguranca e completude de maior prioridade.
2. Harness Engineering continua definindo o fluxo de trabalho e o formato final de evidencia.
3. Autonomous Harness Orchestrator define como coordenar multiplos agentes por camada.
4. Se houver conflito, prevalece a regra mais restritiva.

Alteracoes no Iron Protocol sao permitidas apenas se forem necessarias para:

- registrar o novo workflow como ponto de entrada oficial;
- definir que auditoria por camada usa o ciclo autonomo;
- deixar explicito que deploy pela LLM CLI exige autorizacao registrada, regras de producao, backup, rollback e health checks;
- deixar explicito que autonomia nao autoriza acoes destrutivas, migrations de risco ou relaxamento de testes.

Nao deve haver reescrita ampla do Iron Protocol na primeira implementacao.

## Boot do Fluxo "Iniciar Camada N"

Quando o operador pedir `iniciar camada N`, o orquestrador deve carregar, nesta ordem:

1. `AGENTS.md`, como ponto de entrada do workspace.
2. `.agent/rules/iron-protocol.md`, para precedencia, mandato e limites.
3. `.agent/rules/harness-engineering.md`, para fluxo de trabalho e evidencia.
4. `docs/harness/autonomous-orchestrator.md`, para contrato operacional do harness.
5. `docs/harness/dependency-layers.md`, para matriz de dependencias, criterios e ownership.
6. `docs/harness/harness-state.json`, para estado persistente e proveniencia.
7. docs canonicas da camada alvo e das dependencias anteriores.
8. ultimo diretorio de ciclo da camada, quando existir, apenas como contexto historico.

O boot deve falhar com `blocked_missing_context` se a matriz, o manifesto ou os criterios canonicos da camada estiverem ausentes. O orquestrador nao deve substituir arquivos canonicos ausentes por criterio improvisado.

## Camadas de Dependencia

As camadas seguem ordem de dependencia. Uma camada posterior nao deve ser tratada como aprovada se uma camada anterior estiver falhando em requisito bloqueante.

| Camada | Nome | Depende de | Foco |
| --- | --- | --- | --- |
| 0 | Baseline Operacional | nenhuma | boot, ambiente local, scripts, dependencias, memoria, comandos de teste, sanidade Git, regras carregaveis |
| 1 | Fundacao, Auth e Tenant | 0 | autenticacao, permissao, Spatie, tenant isolation, BelongsToTenant, policies, FormRequest authorize |
| 2 | API e Backend | 0, 1 | rotas, controllers, FormRequests, resources, paginacao, eager loading, contratos REST |
| 3 | Frontend SPA | 0, 2 | React 19, TypeScript strict, API clients, hooks, forms, estados, acessibilidade basica |
| 4 | Modulos e E2E | 0, 1, 2, 3 | fluxos de negocio, Playwright, integracao entre frontend/backend |
| 5 | Infra e CI/CD | 0, 2, 3 | artefatos e pipelines nao-produtivos, Docker, Nginx, scripts, CI, build e health checks locais |
| 6 | Qualidade Global | 0-5 | fechamento transversal: cobertura, analise estatica, lint, duplicacao, performance e seguranca sem redesign estrutural |
| 7 | Producao e Deploy | 0-6 | execucao real em producao, rollback, backups, migrations sensiveis, health checks e pos-deploy |

Fronteiras importantes:

- Camada 5 trata artefatos, scripts, configuracoes e pipelines antes de producao. Ela nao executa deploy real.
- Camada 7 trata execucao em ambiente produtivo. A LLM CLI pode executar deploy real apenas com `deployment_authorization` valida, seguindo regras de producao, backup, rollback e health checks.
- Camada 6 e uma camada de fechamento. Ela pode apontar achados pertencentes as camadas 1-4, mas nao deve virar uma camada de redesign estrutural; esses achados devem ser reclassificados para a camada proprietaria.

## Criterios Canonicos de Aceite por Camada

O plano de implementacao deve materializar esta matriz em `docs/harness/dependency-layers.md`. O orquestrador nao pode inventar criterios ad hoc durante uma rodada. Se um criterio precisar mudar, a matriz versionada deve ser alterada primeiro.

| Camada | Criterios bloqueantes | Fonte canonica |
| --- | --- | --- |
| 0 | boot carrega regras; comandos locais resolvem runtime; scripts de teste/build conhecidos; Git sem sujeira causada pelo ciclo; memoria lida | `AGENTS.md`, `.codex/memory.md`, `.agent/rules/test-runner.md`, `docs/operacional/mapa-testes.md` |
| 1 | tenant isolation preservado; auth/sanctum/permissoes consistentes; FormRequests com `authorize()` real; auditor de permissoes passa quando aplicavel | `.agent/rules/kalibrium-context.md`, `docs/auditoria/CAMADA-1-FUNDACAO.md`, `backend/tests/README.md` |
| 2 | rotas/controllers/FormRequests/resources aderentes; listagens paginadas; eager loading; contratos REST e tenant no controller | `.agent/rules/kalibrium-context.md`, `docs/auditoria/CAMADA-2-API-BACKEND.md`, `docs/architecture/15-15-api-versionada.md` |
| 3 | TypeScript sem `any` novo; React Query para server state; forms com RHF/Zod; API clients com unwrap correto; build/typecheck/lint afetados passam | `.cursor/rules/frontend-type-consistency.mdc`, `docs/auditoria/CAMADA-3-FRONTEND.md`, `docs/design-system/` |
| 4 | fluxos de negocio preservados; E2E afetados cobrem caminho principal; regressao de modulo com evidencia; contratos frontend-backend coerentes | `docs/auditoria/CAMADA-4-MODULOS-E2E.md`, `docs/PRD-KALIBRIUM.md`, codigo do modulo alvo |
| 5 | Docker/scripts/CI nao quebram; configs sem segredo; build e health checks locais/documentados; sem mudanca de producao sem regra especifica | `docs/auditoria/CAMADA-5-INFRA-DEPLOY.md`, `.cursor/rules/integration-safety.mdc`, `.cursor/rules/deploy-production.mdc` |
| 6 | analise estatica/lint/testes relevantes passam; achados de seguranca transversal triados; cobertura e qualidade de testes nao pioram | `docs/auditoria/CAMADA-6-TESTES-QUALIDADE.md`, `.agent/rules/test-policy.md`, `.agent/rules/test-runner.md` |
| 7 | backup/rollback definidos; deploy pela LLM CLI somente com autorizacao registrada; health checks e pos-deploy documentados; migrations seguras e reversiveis | `docs/auditoria/CAMADA-7-PRODUCAO-DEPLOY.md`, `.cursor/rules/deploy-production.mdc`, `.cursor/rules/migration-production.mdc` |

Precedencia dentro das fontes da camada:

1. `AGENTS.md` e Iron Protocol vencem regras operacionais duplicadas.
2. `.agent/rules/harness-engineering.md`, `.agent/rules/test-policy.md` e `.agent/rules/test-runner.md` vencem guias antigos de auditoria quando o assunto for fluxo, teste ou evidencia.
3. `kalibrium-context.md` vence docs de auditoria quando houver divergencia sobre stack e padroes atuais.
4. Codigo atual vence documentacao funcional apenas quando a pergunta for estado real do sistema.
5. Criterio canonico da camada vence quando a pergunta for conformidade esperada.
6. PRD, blueprint e docs funcionais orientam gaps de produto; divergencia entre doc e codigo deve virar finding, nao implementacao automatica fora do escopo.

Na camada 0, separar baseline em:

- Obrigatorio: regras carregaveis, runtime minimo para comandos principais, Git sem sujeira criada pelo ciclo, memoria consultada, comando de teste conhecido.
- Recomendado: otimizacoes locais, scripts auxiliares, atalhos de DX e melhorias de documentacao que nao bloqueiam auditoria das camadas seguintes.

## Definicao Formal de Aprovacao

Uma camada so pode ser marcada como `approved` quando todos os criterios abaixo forem verdadeiros:

- todas as dependencias anteriores da camada estao em `approved`;
- nao ha finding `critical` ou `high` bloqueante em aberto;
- findings `medium` bloqueiam apenas se violarem item explicitamente bloqueante da matriz da camada, impedirem verificacao deterministica obrigatoria ou comprometerem evidencia minima de aprovacao;
- findings `medium` bloqueantes foram corrigidos, reclassificados com justificativa ou escalados;
- todas as verificacoes deterministicas obrigatorias da camada passaram com evidencia real no ciclo atual;
- em modo `full`, os cinco auditores retornaram `approved` ou tiveram divergencia resolvida no relatorio consolidado;
- em modo `targeted`, todos os auditores obrigatorios da rodada focal aprovaram e o consolidado declarou ausencia de regressao nos dominios nao reauditados;
- em modo `verification_only`, os comandos obrigatorios e a validacao consolidada aprovaram uma mudanca minima `low` sem alterar estrutura;
- o relatorio consolidado esta fechado com decisao final explicita;
- nao ha falha interna do harness aberta;
- `approved_commit` corresponde ao `head_commit` verificado no fechamento;
- o manifesto `harness-state.json` aponta para o relatorio final do ciclo aprovado.

Se qualquer criterio falhar, a camada deve ficar em `in_progress`, `blocked` ou `escalated`, nunca em `approved`.

## Ciclo Autonomo por Camada

Entrada: `iniciar camada N`.

Fluxo:

1. O orquestrador carrega o escopo da camada, dependencias e criterios de aceite.
2. O orquestrador cria um diretorio de ciclo para evidencias.
3. O orquestrador dispara cinco auditores independentes, todos read-only.
4. O orquestrador consolida achados em severidades e decide se ha correcao local segura.
5. Se houver achados corrigiveis, dispara um agente corretor com modelo `gpt-5.4` e reasoning `xhigh`.
6. O corretor implementa seguindo Iron Protocol, regras de teste/TDD deste documento e Harness Engineering.
7. O orquestrador roda verificacoes deterministicas da camada.
8. Se houve correcao, o orquestrador retorna para auditoria da mesma camada.
9. O ciclo encerra quando todos os auditores aprovam e os comandos de verificacao passam.
10. Se o ciclo chegar a 10 iteracoes sem aprovar, o orquestrador para e escala para o humano.

Convencao de identidade da run:

- `run_id` canonico: `YYYYMMDDTHHmmssZ-layer-N-cycle-M`, sempre em UTC.
- `cycle` e incremental por camada, nao global.
- ciclo fechado nunca e reaberto; nova tentativa cria novo ciclo com referencia a `supersedes_run_id`.
- artefato invalido antes do fechamento pode ser regenerado dentro da mesma run apenas com `artifact_revision` incrementado e registro no relatorio.
- relatorio final fechado e imutavel; qualquer correcao posterior cria nova run.

Transicoes validas do ciclo:

| Transicao | Regra |
| --- | --- |
| `idle -> audit` | `start --layer N` criou diretorio do ciclo, `base_commit` foi registrado e dependencias anteriores estao aprovadas. |
| `audit -> fix` | ha findings corrigiveis (`blocked_code`) e nenhum `blocked_policy` ou `blocked_missing_context` terminal. |
| `audit -> verification` | auditores aprovaram ou apontaram apenas findings `low` sem correcao de codigo. |
| `audit -> closed` | auditores obrigatorios aprovaram, comandos obrigatorios ja possuem evidencia no ciclo e manifesto foi atualizado. |
| `audit -> blocked` | ha `blocked_environment`, `blocked_missing_context`, `blocked_policy` ou `blocked_conflict` sem acao local segura. |
| `fix -> verification` | corretor alterou arquivos, atualizou `changed-files.txt` e registrou `head_commit` ou estado dirty controlado. |
| `verification -> fix` | comando obrigatorio falhou por causa de codigo corrigivel. |
| `verification -> reaudit` | comandos passaram, mas houve mudanca estrutural, cross-layer ou finding `high/critical` previamente corrigido. |
| `verification -> blocked` | comando obrigatorio nao executou por ambiente, politica ou contexto ausente. |
| `verification -> closed` | comandos obrigatorios passaram, relatorio consolidado fechou decisao e state foi persistido. |
| `reaudit -> fix` | reauditoria encontrou novo finding corrigivel no escopo. |
| `reaudit -> verification` | reauditoria aprovou os auditores obrigatorios e requer apenas comandos finais. |
| `reaudit -> escalated` | conflito insoluvavel, ciclo 10 reprovado ou correcao exige acao humana. |
| `closed -> idle` | apenas apos persistencia do manifesto, `report.md`, `consolidated-findings.json`, `commands.log.jsonl` e, quando houve mudanca, `changed-files.txt`. |

Estados terminais do ciclo (`closed`, `blocked`, `escalated`) devem registrar `blocking_reason` quando nao houver aprovacao.

## Modos de Auditoria

O harness usa cinco auditores como regra-base, mas a rodada declara explicitamente um `audit_mode`:

| Modo | Quando usar | Requisito de aprovacao |
| --- | --- | --- |
| `full` | abertura de camada, mudanca estrutural, finding `critical/high`, mudanca cross-layer relevante ou fechamento da camada | os cinco auditores aprovam ou tem divergencia resolvida |
| `targeted` | correcao localizada de finding `medium` ou mudanca pequena com dominio claro | todos os auditores selecionados aprovam e o consolidado declara ausencia de regressao nos demais dominios |
| `verification_only` | finding `low`, documentacao neutra ou mudanca minima sem comportamento | comandos obrigatorios passam e o consolidado registra por que auditoria completa nao foi necessaria |

O `audit_mode` deve aparecer em `harness-state.json`, nos JSONs dos auditores, no consolidado e no `report.md`.

`verification_only` e proibido quando houver:

- qualquer arquivo de auth, permissao, tenant ou policy;
- qualquer controller, FormRequest, resource, rota ou cliente de API com mudanca de contrato;
- qualquer mudanca de comportamento observavel pelo usuario ou pela API;
- qualquer arquivo compartilhado por multiplas camadas funcionais;
- qualquer finding originado de severidade `critical` ou `high`;
- qualquer mudanca em fonte canonica de criterio;
- qualquer migration ou arquivo de producao/deploy real, salvo ciclo de camada 7 com `deployment_authorization` valida.

Quando permitido, `verification_only` deve declarar no consolidado por que a mudanca e `low`, qual evidencia demonstra ausencia de mudanca comportamental e quais comandos cobrem o risco residual.

## Auditores Padrao por Camada

Cada rodada usa cinco perspectivas independentes. Os prompts finais podem variar por camada, mas os papeis base sao:

- Auditor de arquitetura e dependencias: verifica ordem de camadas, acoplamento, arquivos canonicos e aderencia ao Blueprint AIDD.
- Auditor de seguranca e tenant: verifica auth, permissoes, tenant isolation, secrets, headers, webhooks e riscos de privilege escalation.
- Auditor de qualidade de codigo: verifica padroes Laravel/React, duplicacao, dead code, debug code, `any`, `console.log`, controllers e FormRequests.
- Auditor de testes e QA: verifica cobertura, regressao, comandos minimos, qualidade das asserts, cross-tenant, 422, 403, E2E quando aplicavel.
- Auditor de operabilidade e integracao: verifica scripts, build, ambiente local, CI, logs, docs operacionais e riscos de deploy conforme camada.

Contrato minimo de independencia por auditor:

| Auditor | Escopo principal | Fontes prioritarias | Findings principais permitidos | Secundario apenas |
| --- | --- | --- | --- | --- |
| Arquitetura e dependencias | camadas, acoplamento, ownership, blueprint, fronteiras 5/6/7 | `docs/harness/dependency-layers.md`, `docs/BLUEPRINT-AIDD.md`, docs de arquitetura | dependencia invertida, cross-layer indevido, criterio ausente, ownership ambiguo | estilo local de codigo, micro-otimizacao |
| Seguranca e tenant | auth, permissao, tenant isolation, secrets, dados sensiveis | Iron Protocol, `kalibrium-context.md`, docs da camada 1, regras de producao quando aplicavel | tenant escape, auth bypass, permissao incorreta, segredo exposto, producao sem confirmacao | estilo React, duplicacao menor |
| Qualidade de codigo | padroes Laravel/React, debug code, `any`, FormRequest, legibilidade, duplicacao | Iron Protocol, rules frontend/backend, docs da camada ativa | violacao de padrao obrigatorio, codigo morto, debug, tipo inseguro | decisao de deploy, tenant isolation sem evidencia |
| Testes e QA | cobertura proporcional, regressao, comandos, asserts, E2E afetado | `test-policy.md`, `test-runner.md`, mapa de testes, docs de auditoria | teste ausente, teste mascarado, comando obrigatorio falho, cobertura insuficiente | arquitetura de modulo sem impacto de teste |
| Operabilidade e integracao | ambiente, scripts, CI, build, logs, Docker, deploy seguro | docs da camada 5/7, regras de deploy, integration-safety | script quebrado, secret em config, build/health check, risco de deploy | detalhes de controller sem impacto operacional |

Cada auditor deve responder as perguntas obrigatorias do seu escopo, declarar fontes carregadas e registrar como `secondary_signal` qualquer problema fora do seu dominio principal. `secondary_signal` nao vira finding bloqueante sem confirmacao do auditor proprietario ou do consolidado.

Os auditores nao editam codigo. Eles retornam relatorio estruturado com:

- status: `approved`, `issues_found` ou `blocked`;
- findings com severidade `critical`, `high`, `medium`, `low`;
- arquivos e linhas quando possivel;
- comando de verificacao recomendado;
- decisao sobre se o achado bloqueia a camada.

Cada auditor deve gravar saida propria no diretorio do ciclo, em JSON, com schema minimo:

```json
{
  "schema_version": 1,
  "run_id": "20260414T040000Z-layer-1-cycle-3",
  "layer": 1,
  "cycle": 3,
  "audit_mode": "full",
  "auditor": "security-tenant",
  "status": "issues_found",
  "generated_at": "2026-04-14T00:00:00-04:00",
  "summary": "Encontradas 3 falhas bloqueantes",
  "scope": {
    "allowed_paths": ["backend/app/Http/**", "backend/tests/Feature/Auth/**"],
    "readonly": true,
    "canonical_sources": ["docs/auditoria/CAMADA-1-FUNDACAO.md"]
  },
  "provenance": {
    "base_commit": "abc123",
    "head_commit": "abc123",
    "working_tree_state": "clean"
  },
  "findings": [
    {
      "id": "SEC-TENANT-001",
      "root_cause_key": "tenant-id-from-request",
      "title": "Controller sem garantia de tenant isolation",
      "severity": "high",
      "blocking": true,
      "block_type": "blocked_code",
      "files": [
        {
          "path": "backend/app/Http/Controllers/Api/V1/ExampleController.php",
          "line": 42
        }
      ],
      "evidence": "Metodo store aceita tenant_id vindo do request.",
      "canonical_criterion": "tenant_id deve ser atribuido pelo usuario autenticado",
      "recommended_verifications": [
        "cd backend && ./vendor/bin/pest --filter=ExampleController --no-coverage"
      ],
      "confidence": "high"
    }
  ],
  "harness_errors": []
}
```

Se o auditor aprovar, `findings` deve ser lista vazia e `status` deve ser `approved`.

Se o auditor nao conseguir produzir JSON valido, o orquestrador deve registrar falha interna `harness_output_invalid` e bloquear o ciclo ate a saida ser corrigida ou regenerada.

## Taxonomia de Severidade e Bloqueios

Severidade:

- `critical`: risco de tenant escape, auth bypass, perda de dados, segredo exposto, migracao destrutiva, build/teste essencial da camada quebrado.
- `high`: quebra funcional central, contrato REST incorreto, permissao incorreta, teste bloqueante falhando, padrao obrigatorio do Iron Protocol violado.
- `medium`: inconsistencia relevante, violacao de padrao sem quebra total, cobertura insuficiente para alteracao, risco de manutencao que afeta a camada.
- `low`: melhoria, limpeza, documentacao menor ou oportunidade sem impacto bloqueante.

Tipos de bloqueio:

- `blocked_environment`: ambiente local ou runtime impede verificacao.
- `blocked_missing_context`: criterio canonico ou informacao necessaria nao existe.
- `blocked_policy`: correcao exigiria violar regra, producao sem `deployment_authorization`, migration sensivel ou acao destrutiva.
- `blocked_code`: bug ou inconsistencia de codigo corrigivel localmente.
- `blocked_conflict`: auditores divergem e nao ha criterio objetivo para resolver.

Findings `critical` sempre bloqueiam. Findings `high` bloqueiam por padrao; so podem ser nao-bloqueantes com justificativa explicita no relatorio consolidado. Findings `medium` bloqueiam apenas quando violam item explicitamente bloqueante da matriz da camada, impedem verificacao deterministica obrigatoria ou comprometem evidencia minima de aprovacao. Findings `low` nao bloqueiam.

Status de execucao de comandos:

- `passed`: comando executado e saiu com codigo esperado.
- `failed`: comando executado e falhou por comportamento do sistema auditado.
- `not_executed`: comando nao foi rodado e ainda nao ha justificativa aceita.
- `blocked_environment`: ambiente, dependencia local ou runtime impede execucao.
- `blocked_policy`: comando exigiria producao sem `deployment_authorization`, acao destrutiva ou permissao proibida.
- `waived_by_policy`: comando foi dispensado por regra canonica registrada, com responsavel e motivo.
- `replaced_by_equivalent`: comando foi substituido por equivalente mais especifico; deve registrar comando original, comando substituto e justificativa.

`not_executed` nao pode fechar camada como `approved`. `waived_by_policy` e `replaced_by_equivalent` so sao aceitos quando o relatorio consolidado apontar a regra canonica que autoriza a dispensa ou substituicao.

Campos obrigatorios para `waived_by_policy` e `replaced_by_equivalent`:

- `original_command`;
- `effective_command`, quando houver substituicao;
- `canonical_basis`;
- `approved_by`;
- `justification`;
- `essential_for_approval`.

Comandos essenciais para aprovacao funcional da camada nao podem usar `waived_by_policy`; nesses casos, so e permitido `passed`, `failed`, `blocked_environment`, `blocked_policy` ou `replaced_by_equivalent` com base canonica explicita.

## Consolidacao de Findings

A consolidacao e componente de primeira classe do harness. O orquestrador deve:

- agrupar findings pelo `root_cause_key`, nao apenas por arquivo;
- preservar evidencias de todos os auditores que apontaram a mesma causa raiz;
- escolher a maior severidade entre findings duplicados;
- manter divergencias explicitas quando dois auditores discordarem de severidade ou bloqueio;
- gerar `consolidated-findings.json` antes de acionar o corretor;
- escalar `blocked_conflict` quando nao houver fonte canonica para decidir.

Algoritmo minimo de resolucao de conflitos:

1. Se houver fonte canonica explicita para o criterio, vence a fonte canonica.
2. Se houver evidencia deterministica executada, ela prevalece sobre julgamento textual.
3. Se o conflito for apenas de severidade, usar temporariamente a maior severidade ate justificativa consolidada.
4. Se o conflito for sobre existencia do problema e nao houver criterio objetivo, marcar `blocked_conflict`.
5. Se o conflito for sobre escopo de correcao, vence a regra mais restritiva.
6. Se o conflito envolver seguranca, tenant isolation, producao ou dados, escalar salvo se a fonte canonica resolver sem ambiguidade.

Schema minimo de `consolidated-findings.json`:

```json
{
  "schema_version": 1,
  "layer": 1,
  "cycle": 3,
  "audit_mode": "full",
  "status": "issues_found",
  "decision": "fix",
  "target_scope": {
    "type": "finding_set",
    "paths": ["backend/app/Http/Controllers/Api/V1/ExampleController.php"],
    "finding_ids": ["SEC-TENANT-001"],
    "context": "tenant isolation in ExampleController"
  },
  "base_commit": "abc123",
  "head_commit": "def456",
  "findings": [
    {
      "id": "CONS-001",
      "root_cause_key": "tenant-id-from-request",
      "source_finding_ids": ["SEC-TENANT-001", "ARCH-004"],
      "title": "tenant_id aceito do cliente",
      "severity": "high",
      "blocking": true,
      "block_type": "blocked_code",
      "owning_layer": 1,
      "allowed_write_paths": ["backend/app/Http/Controllers/**", "backend/tests/Feature/Auth/**"],
      "evidence_summary": "Dois auditores apontaram tenant_id vindo do request.",
      "conflicts": [],
      "required_verifications": [
        "cd backend && ./vendor/bin/pest --filter=ExampleController --no-coverage"
      ]
    }
  ],
  "harness_errors": [],
  "next_action": "spawn_fixer"
}
```

## Agente Corretor

O agente corretor recebe apenas os findings consolidados, contexto minimo necessario e arquivos relevantes.

Regras:

- usar `gpt-5.4` com reasoning `xhigh`;
- aplicar correcao de causa raiz;
- nao mascarar testes;
- se houver bug reproduzivel ou lacuna de cobertura em comportamento bloqueante, criar ou ajustar teste antes ou junto da correcao;
- em auth, tenant, permissao, API publica ou contrato REST, teste de regressao e obrigatorio quando a mudanca for comportamental ou de risco relevante;
- em refactor neutro sem mudanca observavel, novo teste pode ser dispensado apenas se testes existentes cobrirem o comportamento preservado e o relatorio justificar;
- nao ampliar escopo sem necessidade;
- se a cascata ultrapassar cinco arquivos fora do escopo da camada, parar e reportar;
- justificar qualquer mudanca cross-layer no relatorio consolidado;
- respeitar limite recomendado de ate oito arquivos alterados por ciclo; acima disso, escalar salvo quando a correcao for mecanica e claramente delimitada;
- nao alterar mais de duas areas fora da camada ativa sem escalacao;
- se a correcao exigir acao destrutiva, migration com risco de perda de dados, auth global sensivel ou producao fora da autorizacao de deploy, parar e pedir aprovacao humana;
- gerar evidencia de comandos executados.

## Deploy pela LLM CLI

A LLM CLI pode executar deploy quando o manifesto tiver `deployment_authorization` valida. Essa permissao existe para reduzir interacao humana repetitiva na camada 7, mas nao transforma deploy em acao cega.

Campos minimos da autorizacao:

```json
{
  "deployment_authorization": {
    "authorized": true,
    "run_id": "20260414T000000Z-layer-7-cycle-1",
    "layer": 7,
    "cycle": 1,
    "authorized_by": "human",
    "authorized_at": "2026-04-14T00:00:00-04:00",
    "authorized_head_commit": "def456",
    "target_environment": "production",
    "target_commit": "def456",
    "deploy_command": "ssh -i \"$env:USERPROFILE\\.ssh\\id_ed25519\" -o ServerAliveInterval=15 -o ServerAliveCountMax=20 -o StrictHostKeyChecking=no deploy@203.0.113.10 'cd /srv/kalibrium && bash deploy/deploy.sh'",
    "allow_migrations": false,
    "migration_diff_checked": true,
    "requires_backup": true,
    "rollback_command": "ssh -i \"$env:USERPROFILE\\.ssh\\id_ed25519\" -o ServerAliveInterval=15 -o ServerAliveCountMax=20 -o StrictHostKeyChecking=no deploy@203.0.113.10 'cd /srv/kalibrium && bash deploy/deploy.sh --rollback'",
    "required_health_checks": [
      "https://app.example.test",
      "https://app.example.test/up",
      "http://203.0.113.10"
    ]
  }
}
```

Regras:

- a LLM CLI pode executar o `deploy_command` autorizado e registrar saida em `commands.log.jsonl`;
- `deployment_authorization.run_id`, `layer` e `cycle` devem bater com a run ativa da camada 7;
- `deployment_authorization.authorized_head_commit` deve bater com o HEAD atual;
- o workspace deve ser um repositorio Git valido com HEAD conhecido;
- antes do deploy, deve registrar commit local, commit remoto alvo, diff de migrations verificado e plano de rollback;
- backup e obrigatorio quando `target_environment = production`;
- `migration_diff_checked` e obrigatorio quando `target_environment = production`;
- `deploy_command` contendo `$`, backticks, `migrate:fresh`, `migrate:reset`, `migrate:refresh`, `migrate:rollback` ou `db:wipe` deve parar antes de executar;
- se `allow_migrations = false`, deploy com migration pendente ou `deploy_command` contendo `migrate`, subcomando `migrate:*`, `--migrate` ou `--migrate=*` tambem deve parar antes de executar;
- se `allow_migrations = true`, as regras de migration de producao continuam obrigatorias;
- rollback real tambem exige autorizacao registrada, salvo quando o proprio deploy autorizado falhar e o rollback estiver explicitamente previsto em `rollback_command`;
- qualquer divergencia entre `target_commit`, branch remota e estado do servidor vira `blocked_policy`;
- evidencias de backup, health checks pos-deploy e commit remoto devem ser produzidas pelo `deploy_command` autorizado ou registradas explicitamente via `record-command`/relatorio antes do fechamento;
- credenciais, tokens, `.env` e dumps nunca podem ser gravados nos relatorios.

## Ownership por Camada e Guardrails de Escopo

O limite de "oito arquivos por ciclo" e "cinco arquivos fora da camada" depende de ownership declarada, nao apenas de diretorio fisico. A matriz final deve existir em `docs/harness/dependency-layers.md` e iniciar com este mapa:

| Padrao | Camada proprietaria | Observacao |
| --- | --- | --- |
| `AGENTS.md`, `.agent/rules/**`, `.agent/skills/**`, `.agent/workflows/**` | governanca sensivel | neutro para produto, mas exige justificativa e nao conta como correcao funcional comum |
| `.codex/memory.md`, `docs/harness/**` | governanca/harness | permitido quando a tarefa e protocolo do harness |
| `backend/app/Models/**`, `backend/database/migrations/**`, `backend/database/factories/**` | 1 ou 2 conforme dominio | auth/tenant/permissao tende a camada 1; API/dominio geral tende a camada 2; migration sensivel pode exigir escalacao |
| `backend/app/Http/Middleware/**`, `backend/app/Policies/**`, permissoes e auth | 1 | alteracao global de auth/permissao exige cautela alta |
| `backend/app/Http/Controllers/**`, `backend/app/Http/Requests/**`, `backend/app/Http/Resources/**`, `backend/routes/**` | 2 | controllers e contratos REST |
| `backend/tests/Feature/Auth/**`, testes de tenant/permissao | 1 | teste acompanha a camada funcional auditada |
| `backend/tests/Feature/**`, `backend/tests/Unit/**` | 2 ou camada funcional afetada | teste nao e neutro; herda a camada do comportamento coberto |
| `frontend/src/**` | 3 | componentes, hooks, clients, tipos e forms |
| `frontend/tests/**`, `frontend/src/**/*.test.*` | 3 ou 4 | unit/integration frontend e camada 3; fluxo E2E/modulo e camada 4 |
| `e2e/**`, `frontend/e2e/**`, `playwright.config.*` | 4 | fluxos de negocio e E2E |
| `Dockerfile*`, `docker-compose*`, `.github/**`, `nginx/**`, scripts de build/deploy nao produtivo, `.env.example` | 5 | sem execucao real de producao |
| `deploy.sh`, docs/scripts de rollback, producao, backup e health check real | 7 | acao real exige `deployment_authorization` valida |
| `docs/auditoria/**`, `docs/architecture/**`, `docs/PRD-KALIBRIUM.md` | fonte canonica | mudanca de criterio deve atualizar versao da matriz |

Definicoes:

- **Ownership primario:** camada responsavel pelo comportamento principal do arquivo.
- **Ownership compartilhado:** arquivo usado por duas ou mais camadas funcionais; qualquer alteracao exige verificacao dos consumidores relevantes.
- **Cross-layer support file:** arquivo de suporte necessario para corrigir a causa raiz de outra camada, mas que nao pertence a camada ativa.
- **Dentro da camada:** arquivo cujo ownership principal e a camada ativa ou uma dependencia direta necessaria para corrigir a causa raiz consolidada.
- **Fora da camada:** arquivo de outra camada sem dependencia tecnica direta registrada em `consolidated-findings.json`.
- **Neutro/documentacao:** docs de evidencia do ciclo e relatorios; nao contam como mudanca funcional, mas docs canonicas de criterio contam como governanca e exigem justificativa.
- **Teste:** conta na camada do comportamento coberto, nao como arquivo neutro.
- **Arquivo compartilhado:** conta como cross-layer se for usado por mais de uma camada funcional; o corretor deve justificar impacto e verificacao.
- **Migration:** se for corretiva e idempotente pode pertencer a camada 1/2 conforme dominio; se houver risco de perda de dados, vira `blocked_policy` ate decisao humana.

Quando `consolidated-findings.json` autorizar arquivo compartilhado ou cross-layer support file, deve declarar:

- `ownership_type`: `primary`, `shared` ou `cross_layer_support`;
- `why_required`: por que o arquivo e necessario para a causa raiz;
- `affected_layers`: camadas potencialmente impactadas;
- `risk_verifications`: comandos ou auditorias que cobrem o risco da mudanca.

Mudanca em fonte canonica de criterio nunca deve ocorrer no mesmo ciclo que corrige produto para aprovar a mesma camada, salvo decisao humana registrada em `approved_by`. Se o criterio mudar, incrementar `protocol_version` ou `dependency_matrix_version`, invalidar camadas afetadas e abrir nova run.

## Politica de Reauditoria

Apos correcao, a reauditoria segue estas regras:

- finding `critical` ou `high`: modo `full` da camada com os cinco auditores.
- finding `medium`: modo `targeted` dos auditores afetados, seguida de validacao consolidada.
- finding `low`: `verification_only` com validacao deterministica e registro no relatorio, sem obrigar reauditoria completa.
- mudanca cross-layer: reauditoria focal da camada afetada e verificacao de que a dependencia anterior/posterior nao piorou.
- qualquer falha de comando obrigatorio volta o ciclo para correcao ou bloqueio, conforme causa.
- fechamento de camada: sempre exige modo `full` ou justificativa canonica registrada para modo menos amplo.

Politica de reset e reabertura:

- mudanca de `protocol_version` ou `dependency_matrix_version`: invalida runs abertas e reinicia contagem da camada afetada em novo ciclo 1 da nova versao.
- mudanca em dependencia anterior aprovada: invalida a camada dependente e abre novo ciclo com `supersedes_run_id`.
- decisao humana apos `escalated`: abre novo ciclo com referencia ao ciclo anterior; nao reabre ciclo fechado.
- falha interna do harness antes do fechamento: pode continuar a mesma run com `artifact_revision` incrementado, se `base_commit` e `head_commit` forem preservados.
- falha interna apos fechamento: cria nova run de correcao de harness; relatorio fechado permanece imutavel.

Camada 6 nao autoriza redesign estrutural amplo no mesmo ciclo. Ela pode abrir finding transversal, classificar owner e redirecionar a causa raiz para a camada proprietaria; refactor estrutural deve ocorrer em ciclo da camada proprietaria ou via decisao humana registrada.

## Limite de Autonomia

O orquestrador pode agir autonomamente em:

- leitura de codigo e documentacao;
- auditorias read-only;
- correcao local de codigo;
- criacao de testes;
- execucao de lint, typecheck, build e testes;
- documentacao do proprio ciclo;
- deploy pela LLM CLI quando houver `deployment_authorization` valida e run ativa da camada 7.

O orquestrador deve escalar para o humano antes de:

- deploy em producao sem `deployment_authorization` valida;
- rollback real nao previsto na autorizacao de deploy;
- migration com risco de perda de dados;
- alteracao sensivel global de auth/permissao;
- exclusao irreversivel;
- rotacao de segredo externo;
- ciclo 10 ainda reprovado;
- conflito tecnico entre auditores sem decisao objetiva.

## Artefatos de Implementacao

Arquivos planejados:

- `docs/harness/autonomous-orchestrator.md`: protocolo principal.
- `docs/harness/dependency-layers.md`: matriz de camadas, dependencias e criterios por camada.
- `docs/harness/schemas/harness-state.schema.json`: schema formal do manifesto.
- `docs/harness/schemas/auditor-output.schema.json`: schema formal dos auditores.
- `docs/harness/schemas/consolidated-findings.schema.json`: schema formal da consolidacao.
- `docs/harness/schemas/commands-log.schema.json`: schema formal de evidencia de comandos.
- `docs/harness/cycle-report-template.md`: template de evidencia por ciclo.
- `docs/harness/harness-state.json`: manifesto versionado com estado atual do harness.
- `.agent/workflows/harness-layer.md`: workflow para "iniciar camada N".
- `scripts/harness-cycle.mjs`: CLI leve para criar pasta de ciclo e imprimir comandos recomendados.

Alteracoes possiveis:

- `AGENTS.md`: adicionar referencia curta ao novo harness se a implementacao precisar torna-lo discoverable no boot.
- `.agent/rules/iron-protocol.md`: adicionar extensao curta apenas se necessario para formalizar precedencia e limites de autonomia.
- `.codex/memory.md`: atualizar somente no final, registrando decisao operacional e caminho dos artefatos.

## Estado e Versionamento

`docs/harness/harness-state.json` sera a unica fonte de verdade do estado persistente do harness. O orquestrador e o CLI devem ler e escrever esse arquivo, evitando estado paralelo em memoria de conversa.

Contrato minimo do manifesto:

```json
{
  "schema_version": 1,
  "protocol_version": "0.1.0",
  "dependency_matrix_version": "0.1.0",
  "updated_at": "2026-04-14T00:00:00-04:00",
  "last_updated_by": "orchestrator",
  "active_layer": null,
  "active_cycle": null,
  "active_run_id": null,
  "active_audit_mode": null,
  "target_scope": {
    "type": "layer",
    "paths": [],
    "finding_ids": [],
    "context": "full layer audit"
  },
  "current_phase": "idle",
  "cycle_state": "idle",
  "escalation_required": false,
  "blocking_reason": null,
  "reports_root": "docs/harness/runs",
  "lock": {
    "active": false,
    "path": null,
    "run_id": null,
    "created_at": null,
    "owner": null
  },
  "deployment_authorization": {
    "authorized": false,
    "run_id": null,
    "layer": null,
    "cycle": null,
    "authorized_by": null,
    "authorized_at": null,
    "authorized_head_commit": null,
    "target_environment": null,
    "target_commit": null,
    "deploy_command": null,
    "allow_migrations": false,
    "migration_diff_checked": false,
    "requires_backup": true,
    "rollback_command": null,
    "required_health_checks": []
  },
  "git": {
    "base_commit": null,
    "head_commit": null,
    "approved_commit": null,
    "working_tree_clean_at_start": null,
    "working_tree_clean_at_close": null,
    "dirty_paths_at_start": []
  },
  "history_summary": [
    {
      "run_id": "20260414T040000Z-layer-0-cycle-1",
      "layer": 0,
      "cycle": 1,
      "decision": "blocked",
      "report": "docs/harness/runs/20260414T040000Z-layer-0-cycle-1/report.md",
      "reason": "blocked_environment: lock divergente"
    }
  ],
  "layers": {
    "0": {
      "status": "not_started",
      "last_cycle": null,
      "last_report": null,
      "approved_report_provenance": {
        "cycle": null,
        "report": null,
        "base_commit": null,
        "head_commit": null,
        "approved_commit": null,
        "approved_at": null
      },
      "depends_on": [],
      "layer_dependencies_resolved": true,
      "invalidated_by": null
    }
  }
}
```

Regras:

- `schema_version` muda apenas quando o formato JSON muda de forma incompativel.
- `protocol_version` muda quando o protocolo ou matriz de aceite muda.
- relatorios historicos sao imutaveis; uma nova rodada cria novo arquivo.
- o manifesto aponta para o ultimo relatorio, mas nao duplica findings completos.
- todas as entradas em `layers` devem seguir o mesmo schema demonstrado na camada 0, incluindo `approved_report_provenance`.
- `base_commit` e capturado ao iniciar o ciclo; `head_commit` e capturado apos cada correcao/verificacao; `approved_commit` so e preenchido no fechamento aprovado.
- se `head_commit` mudar fora do ciclo ou uma dependencia anterior mudar apos aprovacao, a camada afetada deve voltar para `in_progress` e registrar `invalidated_by`.
- um ciclo pode atravessar multiplos commits, mas o relatorio aprovado deve apontar exatamente para o `approved_commit` final.
- `working_tree_clean_at_start` e `working_tree_clean_at_close` devem ser registrados; sujeira preexistente entra em `dirty_paths_at_start` e nao pode ser atribuida ao ciclo sem evidencia.
- se o manifesto estiver inconsistente com os relatorios, o orquestrador deve parar e escalar.

Invariantes semanticas do manifesto:

- se `current_phase != "idle"`, entao `active_layer`, `active_cycle` e `active_run_id` nao podem ser `null`;
- se `cycle_state == "idle"`, entao `current_phase` deve ser `idle`;
- se `lock.active == true`, entao `lock.run_id` deve ser igual a `active_run_id`;
- se uma camada esta `approved`, entao `approved_report_provenance.approved_commit`, `approved_at` e `report` nao podem ser `null`;
- se `blocking_reason != null`, entao `cycle_state` deve ser `blocked` ou `escalated`, ou `current_phase` deve ser `closed` com decisao nao aprovada registrada no relatorio;
- se `escalation_required == true`, entao deve haver `blocking_reason` e proximo passo humano no `report.md`;
- se `active_audit_mode == "verification_only"`, entao o consolidado deve provar que nenhum gatilho proibido desse modo foi tocado;
- `target_scope.paths` nao pode incluir arquivo fora do ownership permitido sem justificativa cross-layer no consolidado.
- se `deployment_authorization.authorized == true`, entao `run_id`, `layer`, `cycle`, `authorized_head_commit`, `target_environment`, `target_commit`, `deploy_command`, `rollback_command`, `authorized_by`, `authorized_at` e `migration_diff_checked` nao podem ser `null`.
- se `deployment_authorization.target_environment == "production"`, entao `requires_backup` e `migration_diff_checked` devem ser `true` e `required_health_checks` nao pode ser lista vazia.

Politica de dirty working tree:

- se o workspace estiver sujo em arquivo da camada ativa, fonte canonica, `AGENTS.md`, `.agent/rules/**`, `docs/harness/**`, `scripts/harness-cycle.*` ou arquivo compartilhado relevante, `start` deve bloquear com `blocked_environment` ou `blocked_missing_context` ate o estado ser reconciliado;
- sujeira fora do escopo pode prosseguir apenas se registrada em `dirty_paths_at_start` e excluida explicitamente do diff do ciclo;
- sujeira criada pelo ciclo deve aparecer em `changed-files.txt` ou o ciclo vira `harness_provenance_mismatch`.

`target_scope.type` deve ser um de: `layer`, `module` ou `finding_set`. Para `target_scope.type = "module"` ou `"finding_set"`, `paths` ou `finding_ids` nao podem ficar vazios.

Estados de camada validos:

- `not_started`
- `in_progress`
- `approved`
- `blocked`
- `escalated`

Fases globais validas:

- `idle`
- `audit`
- `fix`
- `reaudit`
- `verification`
- `closed`

`current_phase` usa apenas fases operacionais globais. `cycle_state` acompanha essas fases e tambem pode assumir terminais `blocked` e `escalated`.

Estados validos do ciclo:

- `idle`
- `audit`
- `fix`
- `reaudit`
- `verification`
- `closed`
- `blocked`
- `escalated`

Transicoes validas de camada:

- `not_started -> in_progress`
- `in_progress -> approved`
- `in_progress -> blocked`
- `in_progress -> escalated`
- `blocked -> in_progress`
- `blocked -> escalated`
- `escalated -> in_progress` apenas apos decisao humana registrada
- `approved -> in_progress` apenas se a matriz, protocolo ou dependencia anterior mudar

O CLI deve rejeitar transicoes invalidas e registrar `blocking_reason` quando o estado final for `blocked` ou `escalated`.

## Atomicidade, Lock e Concorrencia

A primeira implementacao deve assumir uma unica run ativa global no repositorio.

Regras do CLI:

- `start` cria lock antes de escrever manifesto ou diretorio de run.
- o lock canonico fica em `docs/harness/.lock` e registra `run_id`, `created_at`, `owner` e `pid` quando disponivel.
- escritas em `harness-state.json` devem usar arquivo temporario no mesmo diretorio e rename atomico.
- `start`, `record` e `close` nao podem sobrescrever run fechada.
- se uma operacao falhar no meio, o CLI deve remover artefatos temporarios ou marcar `harness_incomplete_run`; nao deve deixar manifesto aparentando sucesso.
- tentativa paralela de `start` com lock ativo deve falhar com `blocked_environment`.
- comandos de continuacao (`record`, `close`, `authorize-deploy`, `deploy`, `record-deploy`, `record-command` e regeneracao de artefato pre-fechamento) devem exigir `run_id` igual ao lock ativo no manifesto e no arquivo `.lock`.
- lock orfao so pode ser removido com comando explicito, `reason`, registro no `report.md` e bloqueio da run ativa quando existir.

## Falhas Internas do Harness

Falhas do processo nao devem ser confundidas com falhas funcionais da camada. Quando ocorrerem, o ciclo fica bloqueado por falha interna ate o proprio harness ser corrigido ou o artefato ser regenerado.

Classes minimas:

- `harness_state_corruption`: `harness-state.json` invalido, inconsistente ou impossivel de reconciliar com os relatorios.
- `harness_output_invalid`: auditor, consolidador ou corretor gerou JSON fora do schema.
- `harness_incomplete_run`: diretorio de ciclo ou arquivo obrigatorio ausente.
- `harness_provenance_mismatch`: `base_commit`, `head_commit`, `approved_commit`, `changed-files.txt` ou `commands.log.jsonl` nao correspondem entre si.
- `harness_command_log_mismatch`: `report.md` declara comando que nao aparece em `commands.log.jsonl`, ou `commands.log.jsonl` registra resultado diferente do relatorio.

Essas falhas devem aparecer em `harness_errors` no auditor/consolidado ou em `blocking_reason` no manifesto. Elas bloqueiam o ciclo, mas nao provam que a camada funcional esta incorreta.

## Estrutura de Relatorio

Cada camada deve gerar relatorios em um caminho previsivel, por exemplo:

`docs/harness/runs/YYYYMMDDTHHmmssZ-layer-N-cycle-M/`

Arquivos minimos por ciclo:

- `report.md`: relatorio consolidado em formato humano.
- `consolidated-findings.json`: findings deduplicados por causa raiz.
- `auditor-architecture-dependencies.json`: saida do auditor de arquitetura e dependencias.
- `auditor-security-tenant.json`: saida do auditor de seguranca e tenant.
- `auditor-code-quality.json`: saida do auditor de qualidade de codigo.
- `auditor-tests-verification.json`: saida do auditor de testes e verificacao.
- `auditor-ops-provenance.json`: saida do auditor de operabilidade e proveniencia.
- `commands.log.jsonl`: comandos deterministicos executados e outputs relevantes em formato JSON Lines.
- `changed-files.txt`: arquivos alterados pelo corretor no ciclo, quando houver.

Campos minimos:

- camada e ciclo;
- agentes auditores usados;
- modo de auditoria;
- `base_commit`, `head_commit` e, se aprovado, `approved_commit`;
- findings por severidade;
- decisao consolidada;
- arquivos alterados, se houver;
- comandos executados;
- output real dos comandos;
- motivo para aprovacao, reauditoria ou escalacao;
- riscos remanescentes;
- proximo passo.

Formato minimo de `report.md`:

```markdown
# Harness Run: layer N cycle M

## Escopo
## Proveniencia Git
## Modo de Auditoria
## Auditores Executados
## Findings Consolidados
## Divergencias e Resolucao
## Arquivos Alterados
## Comandos e Evidencias
## Decisao Final
## Riscos Remanescentes
## Proximo Passo
```

Formato canonico de `commands.log.jsonl`: uma linha JSON por execucao, contendo `schema_version`, `run_id`, `audited_layer`, `cycle`, `command`, `cwd`, `started_at`, `finished_at`, `exit_code`, `status`, `stdout_excerpt`, `stderr_excerpt`, `replacement_for`, `original_command`, `effective_command`, `waiver_basis`, `canonical_basis`, `approved_by`, `justification` e `essential_for_approval`. Markdown humano deve ser derivado no `report.md`, nunca usado como fonte canonica de comandos.

Formato minimo de `changed-files.txt`: um caminho relativo por linha, com prefixo opcional de status Git (`M`, `A`, `D`, `R`). Ele deve ser derivado do diff real do ciclo, nao preenchido manualmente sem conferencia.

## Catalogo Inicial de Verificacoes Deterministicas

O plano deve transformar este catalogo em `docs/harness/dependency-layers.md`. Comandos podem ser refinados por camada, mas o orquestrador deve registrar quando um comando obrigatorio nao for executado e por que.

Os comandos da camada 0 assumem a estrutura padrao do repositorio com `backend/` e `frontend/` presentes. Em ambiente reduzido, ausencia desses diretorios deve virar `blocked_environment` com justificativa, nao aprovacao silenciosa.

| Camada | Verificacoes iniciais |
| --- | --- |
| 0 | `git status --short`; `php -v`; `node -v`; `cd backend && php artisan --version`; `cd backend && ./vendor/bin/pest --help`; `cd frontend && node -e "const s=require('./package.json').scripts; ['typecheck','lint','build','test','test:e2e'].forEach(k=>{if(!s[k]) throw new Error(k)})"` |
| 1 | `cd backend && php artisan camada1:audit-permissions`; testes afetados de auth/tenant/permissao; `cd backend && ./vendor/bin/pest --dirty --parallel --no-coverage` quando houver mudanca |
| 2 | `cd backend && php artisan camada2:validate-routes`; testes afetados de controller/API; `cd backend && ./vendor/bin/pest --dirty --parallel --no-coverage` quando houver mudanca |
| 3 | `cd frontend && npm run typecheck`; `cd frontend && npm run lint`; `cd frontend && npm run build`; Vitest afetado quando houver mudanca de comportamento |
| 4 | Playwright afetado por modulo; testes backend/frontend do fluxo alterado; smoke do contrato frontend-backend quando aplicavel |
| 5 | validacao de scripts e compose sem producao real; build local quando aplicavel; checagem de secrets/configs; sem deploy |
| 6 | suite de analise estatica/lint relevante; Pest/Vitest/Playwright em escopo escalado; relatorio de achados transversais sem redesign estrutural |
| 7 | checklist de backup/rollback/deploy; dry-run quando existir; deploy real pela LLM CLI quando `deployment_authorization` estiver valida; migration real apenas quando autorizada |

## Criterios de Aceite do Primeiro Entregavel

- A matriz de camadas cobre baseline, fundacao, backend, frontend, modulos, infra, qualidade e producao.
- A matriz de aceite por camada existe e aponta fontes canonicas.
- A definicao formal de `approved` existe e e objetiva.
- Os schemas formais de `harness-state.json`, `auditor-*.json`, `consolidated-findings.json` e `commands.log.jsonl` estao definidos.
- O schema minimo de `report.md` e `changed-files.txt` esta definido.
- A taxonomia de severidade e tipos de bloqueio esta definida.
- A semantica de execucao de comandos (`passed`, `failed`, `not_executed`, `waived_by_policy`, `replaced_by_equivalent`) esta definida.
- O algoritmo de resolucao de conflito entre auditores esta definido.
- O modelo de proveniencia por commit e invalidacao de camada esta definido.
- As classes de falha interna do harness estao definidas.
- O ownership por camada e os guardrails de escopo estao definidos.
- A convencao de `run_id`, timezone UTC e regra de ciclo incremental por camada estao definidas.
- As invariantes semanticas do manifesto estao definidas.
- A politica de dirty working tree esta definida.
- A politica de atomicidade, lock e concorrencia global unica esta definida.
- `deployment_authorization` e seus invariantes estao definidos.
- `target_scope` esta definido para rodadas por camada, modulo ou finding set.
- `commands.log.jsonl` e o formato canonico unico de evidencia de comandos.
- `verification_only`, `waived_by_policy` e `replaced_by_equivalent` possuem restricoes explicitas.
- Mudanca de fonte canonica no mesmo ciclo de aprovacao funcional e proibida sem decisao humana registrada.
- Politica de reset/reabertura de ciclo e regeneracao de artefatos invalidos esta definida.
- Contrato minimo de independencia por auditor esta definido.
- Os modos `full`, `targeted` e `verification_only` estao definidos.
- A fronteira entre camadas 5, 6 e 7 esta explicita.
- O workflow permite iniciar uma camada especifica.
- O protocolo define cinco auditores independentes por ciclo.
- O protocolo define reauditoria apos correcao.
- O limite de 10 ciclos e explicito.
- O manifesto `docs/harness/harness-state.json` e a unica fonte de estado persistente.
- A maquina de estados do manifesto esta definida.
- O diretorio de ciclo possui convencao de arquivos para auditores, consolidacao, logs e diff.
- O catalogo inicial de verificacoes deterministicas por camada existe.
- O corretor usa `gpt-5.4 xhigh` quando a plataforma permitir selecao de modelo.
- A LLM CLI pode executar deploy quando houver `deployment_authorization` valida e run ativa da camada 7.
- O CLI local nao promete capacidades que nao possui.
- O Iron Protocol continua prevalecendo em seguranca, completude, testes e producao.

## Riscos e Mitigacoes

- Risco: automacao falsa que parece autonomo mas depende de decisao manual escondida.
  Mitigacao: separar claramente workflow de agente e CLI deterministico; declarar que autonomia depende de guardrails versionados.

- Risco: auditores produzirem findings duplicados ou conflitantes.
  Mitigacao: orquestrador consolida por causa raiz, severidade e bloqueio, gerando `consolidated-findings.json`.

- Risco: corretor alterar areas demais.
  Mitigacao: guardrail de cascata maior que cinco arquivos fora da camada, limite recomendado de oito arquivos por ciclo e justificativa obrigatoria para mudanca cross-layer.

- Risco: loop infinito de auditoria/correcao.
  Mitigacao: limite fixo de 10 ciclos e escalacao obrigatoria.

- Risco: conflitar com Iron Protocol.
  Mitigacao: declarar precedencia do Iron Protocol e alterar apenas entradas minimas.

- Risco: camada 0 virar gate generico permanente.
  Mitigacao: separar baseline obrigatorio de baseline recomendado.

- Risco: camada 6 virar buraco negro de redesign.
  Mitigacao: tratar camada 6 como fechamento transversal e reclassificar achados estruturais para a camada proprietaria.

- Risco: relatorio aprovado apontar para codigo que mudou depois.
  Mitigacao: registrar `base_commit`, `head_commit`, `approved_commit` e invalidar camada quando dependencia anterior ou HEAD relevante mudar.

- Risco: CLI virar uma segunda fonte de logica semantica.
  Mitigacao: limitar CLI a estrutura, schema, status e transicao formal; decisao de aprovacao/bloqueio vem do orquestrador.

- Risco: falha do proprio harness ser confundida com falha da camada auditada.
  Mitigacao: classificar `harness_state_corruption`, `harness_output_invalid`, `harness_incomplete_run` e `harness_provenance_mismatch` como falhas internas bloqueantes do ciclo.

- Risco: duas runs paralelas corromperem o manifesto.
  Mitigacao: uma run ativa global, lock em `docs/harness/.lock`, escrita por arquivo temporario e rename atomico.

- Risco: `verification_only`, `waived_by_policy` ou comando equivalente virarem atalhos para pular verificacao.
  Mitigacao: gatilhos proibidos, campos obrigatorios de justificativa e proibicao de waiver para comando essencial.

- Risco: corretor alterar fonte canonica para aprovar o proprio ciclo.
  Mitigacao: fonte canonica de criterio nao muda no mesmo ciclo de aprovacao funcional sem decisao humana e nova versao da matriz/protocolo.

- Risco: LLM CLI executar deploy em alvo errado ou sem pre-condicao.
  Mitigacao: `deployment_authorization` exige `run_id`, camada, ciclo, HEAD autorizado, alvo, commit, comando, rollback, backup, diff de migrations verificado, policy de migrations e health checks; divergencia vira `blocked_policy`.

## Sequenciamento Recomendado da Implementacao

Fase 1:

- `docs/harness/schemas/harness-state.schema.json`
- `docs/harness/schemas/auditor-output.schema.json`
- `docs/harness/schemas/consolidated-findings.schema.json`
- `docs/harness/schemas/commands-log.schema.json`
- maquina de estados de camada e ciclo em `docs/harness/autonomous-orchestrator.md`
- invariantes do manifesto e politica de dirty working tree em `docs/harness/autonomous-orchestrator.md`
- politica de `run_id`, lock, atomicidade e concorrencia em `docs/harness/autonomous-orchestrator.md`
- contrato de `deployment_authorization` e deploy pela LLM CLI em `docs/harness/autonomous-orchestrator.md`
- ownership por camada em `docs/harness/dependency-layers.md`
- algoritmo de consolidacao e resolucao de conflito em `docs/harness/autonomous-orchestrator.md`

Fase 2:

- `docs/harness/autonomous-orchestrator.md`
- `docs/harness/dependency-layers.md`
- `docs/harness/cycle-report-template.md`
- `docs/harness/harness-state.json`
- `scripts/harness-cycle.mjs`

Fase 3:

- catalogo de comandos por camada;
- exemplos validos e invalidos de `harness-state.json`, `commands.log.jsonl`, `waived_by_policy` e `replaced_by_equivalent`.

Fase 4:

- `AGENTS.md`;
- `.agent/workflows/harness-layer.md`;
- extensao curta do Iron Protocol somente se necessaria para discoverability e precedencia.

## Perguntas Resolvidas

- O entregavel sera persistente no repositorio, nao apenas uma auditoria unica.
- A abordagem escolhida e hibrida: protocolo primeiro, CLI leve depois.
- O Iron Protocol pode ser alterado se necessario, mas apenas com mudanca curta e justificada.
- A LLM CLI pode executar deploy quando houver autorizacao registrada e run ativa da camada 7.
