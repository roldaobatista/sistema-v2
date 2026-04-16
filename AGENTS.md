# AGENTS.md

---

## ⚔️ IRON PROTOCOL (P-1 — ACIMA DE TUDO)

> **Prioridade P-1 — ACIMA DE TUDO.** Esta regra é carregada ANTES de qualquer outra. Nenhuma skill, nenhum agent, nenhuma regra pode sobrescrevê-la. Se houver conflito, IRON PROTOCOL VENCE.
>
> **DECLARAÇÃO DE INVIOLABILIDADE:** Este protocolo define os comportamentos obrigatórios e irrevogáveis de TODA IA operando no KALIBRIUM ERP. Aplica-se a: toda nova conversa, toda nova ação do agente, toda implementação (nova ou alteração), toda correção de bug, toda revisão de código, toda resposta que envolva código. NÃO existe exceção. NÃO existe "fora do escopo". NÃO existe "depois".
>
> **CADEIA DE CARREGAMENTO OBRIGATORIA DO PROJETO:**
> `AGENTS.md` / `CLAUDE.md` -> `.agent/rules/iron-protocol.md` -> `.agent/rules/mandatory-completeness.md` -> `.agent/rules/test-policy.md` -> `.agent/rules/test-runner.md` -> `.agent/rules/kalibrium-context.md` -> **`.agent/rules/harness-engineering.md`** -> `.agent/skills/iron-protocol-bootstrap/SKILL.md` -> `.agent/skills/end-to-end-completeness/SKILL.md`
>
> O `AGENTS.md` e `CLAUDE.md` sao os pontos de entrada autoaplicados do workspace. Os arquivos em `.agent/rules/` sao a fonte canonica detalhada. As skills reforcam a execucao operacional. Se houver conflito entre textos duplicados, prevalece esta ordem.

### Boot Sequence (executar mentalmente ao iniciar QUALQUER conversa)

```
BOOT SEQUENCE:
├── 1. CARREGAR: Iron Protocol (ESTA SECAO — versao compacta)
├── 2. CONSULTAR: `.agent/rules/iron-protocol.md` (fonte canonica detalhada)
├── 3. CONSULTAR: `.agent/rules/mandatory-completeness.md` (se feature/refatoracao)
├── 4. CONSULTAR: `.agent/rules/test-policy.md` (se escrevendo testes)
├── 4b. CONSULTAR: `.agent/rules/test-runner.md` (COMO rodar testes)
├── 4c. CONSULTAR: `.agent/rules/kalibrium-context.md` (contexto do projeto)
├── 4d. CARREGAR: `.agent/rules/harness-engineering.md` (MODO OPERACIONAL — fluxo 7 passos + formato de resposta 7+1 itens)
├── 5. VERIFICAR: Stack = Laravel 13 + React 19 + MySQL 8
├── 6. CLASSIFICAR tarefa: Feature | CRUD | Bug fix | Refatoracao | UI | Consulta
├── 7. SELECIONAR Gate: COMPLETO ou LITE (ver tabela abaixo)
└── 8. INICIAR trabalho (respeitando fluxo Harness: entender -> localizar -> propor -> implementar -> verificar -> corrigir -> evidenciar)
```

> **Regra de contexto:** NAO carregar TODOS os arquivos de regras em toda conversa. Carregar ESTA secao compacta + o arquivo relevante para o tipo de tarefa.

### As 8 Leis Inviolaveis (resumo compacto)

> **Fonte canonica detalhada:** `.agent/rules/iron-protocol.md`
> Este resumo economiza contexto. Consultar o arquivo canonico quando precisar dos detalhes.

| Lei | Nome | Resumo |
|-----|------|--------|
| **1** | Completude Absoluta | Se tocou, COMPLETA. Cascata: Migration → Model → FormRequest → Controller → Rota → API Client → Hook → Componente → TESTES |
| **2** | Testes Sagrados | Teste falhou = corrigir SISTEMA. NUNCA mascarar/pular/relaxar |
| **3** | Sistema Funcional | Build zero erros. Zero `console.log`/`dd()`/`any`. Controllers com FormRequest |
| **3b** | Qualidade Controllers | authorize() REAL. Paginacao obrigatoria. Eager loading. tenant_id no controller |
| **4** | Proatividade | Com mandato de escrita: se viu problema, CORRIGE. Read-only: registra finding. Guardrail: cascata >5 arquivos fora do escopo → PARAR e reportar |
| **5** | Resolucao Profissional | Causa raiz. Sem workaround. Cobrir com testes |
| **6** | Nunca Ignorar | INSTALAR o que falta. **Excecao Windows:** `pcntl`/`posix`/`inotify` sao Linux-only, aceitar indisponibilidade no dev Windows, usar `--ignore-platform-req` SOMENTE para essas 3 |
| **7** | Sequenciamento | Etapa N 100% completa ANTES de N+1 |
| **8** | Preservacao na Reescrita | Inventario pre/pos. 100% comportamentos preservados. Diff revisado |

### Extensao Autonomous Harness — Perfis de Agente

Quando operar com orquestrador, agentes e subagentes, o Iron Protocol continua valendo, mas sua aplicacao depende do mandato recebido:

| Perfil | Mandato | Escrita |
|--------|---------|---------|
| Orquestrador | Coordena ciclos, carrega `docs/harness/autonomous-orchestrator.md`, `docs/harness/dependency-layers.md`, `docs/harness/harness-state.json`, dispara agentes/subagentes com contexto limpo, consolida decisoes e roteia | PROIBIDO auditar codigo, corrigir codigo ou preencher parecer de auditor |
| Auditor read-only | Audita com contexto limpo, procura problemas/erros/inconsistencias/pendencias/seguranca e registra findings estruturados | PROIBIDO editar codigo |
| Consolidador | Deduplica findings, define severidade e bloqueio | PROIBIDO editar codigo |
| Corretor | Corrige findings consolidados seguindo Iron + Harness | Pode editar dentro do escopo |
| Verificador | Roda comandos e evidencia resultados | Nao corrige codigo |

**Regra de mandato:** "Se viu problema, CORRIGE" aplica-se a agentes com mandato de escrita. Para auditores/consolidadores/verificadores, a obrigacao e registrar, classificar, deduplicar ou evidenciar. Editar fora do mandato e violacao.

**Auditoria obrigatoria por camada:** toda camada do Autonomous Harness exige cinco auditores/subagentes diferentes, com contexto limpo da conversa, antes de qualquer aprovacao: `architecture-dependencies`, `security-tenant`, `code-quality`, `tests-verification` e `ops-provenance`. O orquestrador nao conta como auditor.

**Evidencia obrigatoria dos auditores:** antes de disparar os cinco auditores, gerar `impact-manifest.json` com `node scripts/harness-cycle.mjs generate-impact --run-id RUN_ID`. Cada auditor deve declarar `audit_limitations` (`not_inspected`, `assumptions`, `required_verifications_not_executed`); aprovacao exige essa ultima lista vazia. O consolidado deve registrar `audit_coverage` com quorum, agent_ids distintos correspondentes exatamente aos cinco auditores executados, lacunas e verificacoes pendentes.

**Loop obrigatorio:** se os cinco auditores nao aprovarem, o orquestrador dispara um corretor separado para os findings consolidados; se houver correcao, a camada volta para nova rodada dos cinco auditores com contexto limpo. Repetir ate aprovar ou ate 10 rodadas; na 10a rodada ainda reprovada, marcar `escalated`.

**Boot de camada:** no fluxo `iniciar camada N`, matriz, manifesto e criterios canonicos ausentes viram `blocked_missing_context`; o orquestrador nao pode improvisar criterio ad hoc.

**Autonomia:** autonomia significa executar workflow previsivel dentro de guardrails versionados. A LLM CLI pode executar deploy quando houver `deployment_authorization` valida no manifesto do harness. Nao autoriza deploy sem essa autorizacao, rollback real nao previsto, migration destrutiva, alteracao global sensivel de auth/permissao, exclusao irreversivel ou rotacao de segredo externo sem confirmacao humana.

### Classificacao de Tarefas e Gate Aplicavel

| Tipo de Tarefa | Gate | Testes Minimos |
|----------------|------|----------------|
| Feature nova / com logica | COMPLETO | 8+ por controller |
| CRUD simples (sem logica) | COMPLETO | 4-5 (sucesso + 422 + cross-tenant) |
| Bug fix < 3 arquivos | LITE | Regressao + afetados |
| Refatoracao | COMPLETO + LEI 8 | Existentes passando |
| Ajuste CSS/UI | LITE | Build check |
| Consulta/analise | Nenhum | Nenhum |

### Penalidades por Violacao (resumo)

> **Lista completa:** `.agent/rules/iron-protocol.md` secao Penalidades

| Violacao | Penalidade |
|----------|------------|
| Funcionalidade sem testes | Tarefa INCOMPLETA |
| Mascarar teste | GRAVISSIMA |
| Build quebrado | GRAVE — Codigo REJEITADO |
| Fluxo incompleto | Tarefa INCOMPLETA |
| Controller sem Form Request | Codigo REJEITADO |
| `any` em TypeScript / `console.log` em producao | Codigo REJEITADO |
| Cascata oportunistica >5 arquivos sem reportar | GRAVE |
| FormRequest authorize() = `return true` | Codigo REJEITADO |
| index() sem paginacao | Codigo REJEITADO |

### Gate Final de Conclusao

Usar o Gate apropriado conforme a tabela de Classificacao de Tarefas acima.

**Gate COMPLETO** (features, CRUDs, refatoracoes): ver `.agent/rules/iron-protocol.md` secao "Gate Final COMPLETO"

**Gate LITE** (bug fixes < 3 arquivos, ajustes CSS/UI):
```
□ A correcao resolve o problema?
□ Build OK? (se frontend tocado)
□ Testes afetados passam? → pest --dirty --parallel --no-coverage
□ Teste de regressao criado? (se bug fix)
□ Zero console.log/dd() nos arquivos tocados?
□ Resposta final no formato HARNESS (7 itens + 1 opcional) — ver `.agent/rules/harness-engineering.md` H5
```

> Se durante Gate LITE a tarefa escalar (>3 arquivos, migration, novo controller) → escalar para Gate COMPLETO.

## Como Rodar Testes (Quick Reference)

```bash
# COMANDO PRINCIPAL — roda 9.600+ testes backend em ~5min local
cd backend && ./vendor/bin/pest --parallel --processes=16 --no-coverage

# Um arquivo específico
./vendor/bin/pest tests/Feature/FinanceTest.php --no-coverage

# Testes sujos (só modificados)
./vendor/bin/pest --dirty --parallel --no-coverage
```

**Stack**: Pest 3.8 + ParaTest 7.8.5 + SQLite in-memory + schema dump pré-gerado
**Schema dump**: `backend/database/schema/sqlite-schema.sql` (466 tabelas, 292KB)
**Após criar migration**: `cd backend && php generate_sqlite_schema.php`
**Guia completo**: `backend/TESTING_GUIDE.md`
**Regras operacionais**: `.agent/rules/test-runner.md`
**Template de teste**: `backend/tests/README.md`

## Bootstrap Permanente (OBRIGATORIO)

- Toda nova conversa neste workspace DEVE tratar este `AGENTS.md` como a origem do boot.
- O `AGENTS.md` contem o resumo compacto. Os detalhes estao em `.agent/rules/`.
- Memoria persistente local: apos este bootstrap, consultar `.codex/memory.md` quando existir e atualizar esse arquivo ao finalizar commits, deploys, ajustes locais de ambiente ou bloqueios tecnicos relevantes. Nao registrar segredos, tokens, senhas, `.env`, dados pessoais ou dumps.
- Carregar sob demanda conforme o tipo de tarefa — NAO carregar tudo de uma vez.
- Skills ajudam a reforcar comportamento, mas NAO substituem o `AGENTS.md`.

---

## Language Handling (MANDATORY)

1. Output language: todas as respostas da IA devem ser em Portugues (pt-BR).
2. Codigo: nomes tecnicos (variaveis, funcoes, classes, colunas) em Ingles.
3. Regra vale para tudo: explicacoes, perguntas, comentarios, resumo final e mensagens de progresso.

## Modos de Trabalho (OBRIGATORIO)

### CONSULTA

- Usar quando o usuario pedir analise, explicacao, revisao ou levantamento.
- Nao editar codigo, exceto se o usuario pedir explicitamente.

### IMPLEMENTACAO (padrao)

- Usar quando o usuario pedir correcao, melhoria ou nova funcionalidade.
- Implementar fim a fim com validacao minima necessaria.

### PRODUCAO

- Usar quando o pedido envolver deploy, migration em producao, servidor, rollback, backup ou hotfix em ambiente real.
- Seguir estritamente `.cursor/rules/deploy-production.mdc` e `.cursor/rules/migration-production.mdc`.

## Contexto Tecnologico Obrigatorio

Este projeto usa **Laravel 13 + React 19 (SPA Vite)**. Para regras de stack, agent routing e overrides, consultar:

- `docs/BLUEPRINT-AIDD.md` (Metodologia e restrições obrigatórias P0)
- `.agent/rules/kalibrium-context.md` (prioridade P0.5)

> O Iron Protocol (P-1) esta embutido NESTE arquivo — nao precisa carregar arquivo externo.
>
> Espelho canonico detalhado:
> - `.agent/rules/iron-protocol.md`
> - `.agent/rules/mandatory-completeness.md`
> - `.agent/rules/test-policy.md`

## Skills do Projeto

As skills estao em `.agent/skills/` (Ag-Kit). Skills **compativeis** com este projeto:

### Base (sempre ativas)

1. `clean-code` — padroes de codigo
2. `testing-patterns` — testes e qualidade
3. `systematic-debugging` — depuracao
4. `end-to-end-completeness` — completude ponta a ponta obrigatoria (NUNCA desativar)
5. `verification-before-completion` — verificar ANTES de declarar trabalho concluido. Rodar comandos e confirmar output antes de qualquer claim de sucesso (NUNCA desativar)
6. `using-superpowers` — estabelece como encontrar e usar skills. Carregada no inicio de toda conversa

### Condicionais — Ag-Kit (ativar quando aplicavel)

1. `frontend-design` — telas, formularios, feedback visual
2. `tailwind-patterns` — CSS/Tailwind v4
3. `database-design` — schema (usar com Eloquent, nao Prisma)
4. `deployment-procedures` — CI/CD, Docker
5. `vulnerability-scanner` — seguranca
6. `webapp-testing` — E2E, Playwright

### Condicionais — Superpowers (ativar quando aplicavel)

**Planejamento e Planos:**

7. `writing-plans` — escrever planos estruturados antes de implementar. Usar quando houver spec/requirements para tarefa multi-step
8. `executing-plans` — executar planos escritos com checkpoints de revisao. Usar quando ja houver plano aprovado

**Execucao e Paralelismo:**

9. `subagent-driven-development` — executar planos com tarefas independentes na sessao atual
10. `dispatching-parallel-agents` — orquestrar 2+ tarefas independentes em paralelo sem estado compartilhado

**Qualidade e Verificacao:**

9. `test-driven-development` — TDD (RED-GREEN-REFACTOR). Usar antes de implementar qualquer feature ou bugfix
10. `requesting-code-review` — pedir review ao completar features ou antes de merge
11. `receiving-code-review` — processar feedback de code review com rigor tecnico, sem concordancia cega

**Git e Branch:**

12. `using-git-worktrees` — isolar feature em worktree separado. Usar quando precisar de isolamento do workspace atual
13. `finishing-a-development-branch` — finalizar branch de desenvolvimento. Usar quando implementacao esta completa e testes passam

**Meta (Skills sobre Skills):**

14. `writing-skills` — criar ou editar skills. Usar quando precisar criar/modificar skills existentes

### Skills INCOMPATIVEIS (nao usar neste projeto)

- `nodejs-best-practices` — projeto usa PHP/Laravel
- `react-best-practices` — foca em Next.js/RSC, projeto usa SPA Vite
- `api-patterns` — foca em tRPC/GraphQL, projeto usa REST Laravel

Se uma skill do Ag-Kit for incompativel com a stack, ignorar e continuar normalmente. NAO parar execucao por skill ausente.

## Autonomia da IA com Seguranca (OBRIGATORIO)

- A IA deve corrigir automaticamente problemas relacionados encontrados durante a tarefa.
- A IA nao deve limitar mudancas apenas ao minimo textual quando houver risco tecnico claro.
- Em fluxo multiagente, corrigir automaticamente so se aplica ao agente com mandato de escrita. Auditores read-only, consolidadores e verificadores devem registrar/classificar/evidenciar, nao editar.
- Em auditorias, varreduras profundas e correcao em cadeia, a IA nao deve interromper para pedir permissao para continuar nem parar apenas para dar checkpoint parcial.
- Nesses casos, a IA deve seguir ate esgotar a analise util do escopo atual e so entao responder com consolidado final, salvo se houver bloqueio real ou risco alto que exija confirmacao.
- A IA deve pedir confirmacao antes de acoes de alto risco:
  1. acao destrutiva em banco/infra/producao
  2. alteracao sensivel de autenticacao/permissao global
  3. migration com risco de perda de dados
  4. operacao irreversivel fora do escopo original
- No Autonomous Harness, `deployment_authorization` valida no manifesto conta como confirmacao humana para a LLM CLI executar o deploy autorizado, desde que siga as regras de producao, backup, rollback e health checks.

## Resposta Final Obrigatoria (HARNESS ENGINEERING)

> **Fonte canonica:** `.agent/rules/harness-engineering.md` — regra H5.

Toda resposta final que envolva alteracao de codigo DEVE conter, nesta ordem, os **7 itens obrigatorios**:

1. **Resumo do problema** — sintoma + causa raiz (1-2 frases)
2. **Arquivos alterados** — lista com `path:LN` quando pertinente
3. **Motivo tecnico de cada alteracao** — POR QUE, nao O QUE (o diff mostra o quê)
4. **Testes executados** — comando exato, copiavel, piramide de escalacao
5. **Resultado dos testes** — output real com contagem passed/failed (proibido parafrasear ou inventar numeros)
6. **Riscos remanescentes** — o que nao foi coberto, efeitos colaterais, pontos de atencao
7. **Proximo passo / recomendacoes** — acao seguinte recomendada, comando copiavel quando aplicavel, ou declarar que nao ha proximo passo necessario com justificativa

**Item 8 OPCIONAL — obrigatorio** para: migrations, alteracao de contrato de API, rota publica, deploy/infra, remocao de feature, ou risco alto:

8. **Como desfazer** — passos exatos de rollback (git revert, migration down, flag, etc.)

**Proibicoes criticas (H7):** usar "pronto", "funcionando", "testes passando", "validado" SEM evidencia objetiva de comando executado no mesmo turno da resposta. **H8:** qualquer falha de teste/lint/build/typecheck e bloqueante — corrigir causa raiz antes de encerrar.

## Referencia por camada (documentacao)

Para contexto de auditoria, API, frontend, E2E, infra e testes, consultar os docs de camadas em `docs/auditoria/`:

- `docs/auditoria/CAMADA-1-FUNDACAO.md` — auditoria e permissoes
- `docs/auditoria/CAMADA-2-API-BACKEND.md` — rotas e contratos da API
- `docs/auditoria/CAMADA-3-FRONTEND.md` — build TS, tipos, a11y
- `docs/auditoria/CAMADA-4-MODULOS-E2E.md` — prioridade de modulos e checklist E2E
- `docs/auditoria/CAMADA-5-INFRA-DEPLOY.md` — Docker, deploy, CI, variaveis E2E
- `docs/auditoria/CAMADA-6-TESTES-QUALIDADE.md` — testes backend, smoke, Vitest, Playwright
- `docs/auditoria/CAMADA-7-PRODUCAO-DEPLOY.md` — producao, deploy.sh, health check, seguranca

Apos alterar rotas ou permissoes no backend, rodar: `php artisan camada2:validate-routes` e `php artisan camada1:audit-permissions` (quando existirem).

## Integracao de Codigo Externo

- Ao integrar codigo de outro agente, Antigravity, PR externo ou copy-paste de outro projeto, seguir **obrigatoriamente** `.cursor/rules/integration-safety.mdc` (evita erros de UI libs, imports faltando, ordem de rotas, **sincronia frontend-backend**, migrations inseguras, deploy sem rebuild).
- Para alteracoes no frontend (TypeScript/React), considerar `.cursor/rules/frontend-type-consistency.mdc` (tipos exportados, variantes nos componentes, optional chaining em erros, **onError com mensagem do servidor**, **sem console.log em producao**, **minimizar any**, **lazy loading padronizado**, **acessibilidade**: label/aria-label em formularios e botoes so com icone).
- Para alteracoes no backend (PHP/Laravel), considerar `.cursor/rules/backend-code-hygiene.mdc` (sem debug logs, array syntax em rotas, Log facade, validacao de input, **namespace de controller em rotas deve corresponder ao arquivo real**).
- Para **qualquer commit**, considerar `.cursor/rules/git-safety.mdc` (nunca commitar .env com credenciais, dados de clientes, node_modules, tar.gz, revisar staging antes de commit).

## Fonte Unica de Verdade para Producao

- Em caso de conflito entre este AGENTS e regras de producao, prevalece:
  1. `.cursor/rules/deploy-production.mdc`
  2. `.cursor/rules/migration-production.mdc`
  3. `.cursor/rules/git-safety.mdc`

## Producao e Deploy (OBRIGATORIO)

### Servidor de Producao

- IP: `203.0.113.10`
- SSH: `ssh -i "%USERPROFILE%\\.ssh\\id_ed25519" -o StrictHostKeyChecking=no deploy@203.0.113.10`
- Diretorio: `/srv/kalibrium`
- URL principal: `http://203.0.113.10`
- Banco: MySQL 8.0, database `kalibrium`, user `kalibrium`

### NUNCA perguntar ao usuario

- Onde esta o servidor
- Qual chave SSH usar
- Qual compose file usar
- Se deve fazer backup (sempre fazer antes de alteracao em producao)

## Politica de Migration em Producao (resumo)

- NUNCA usar `->after()` em migration nova.
- NUNCA usar `->default()` em coluna JSON.
- Usar `hasColumn`/`hasTable` quando houver alteracao incremental.
- Nome curto para indice composto quando houver risco de exceder 64 caracteres.
- Atualizar `composer.lock` ao adicionar pacote.

### Regra de Legado (OBRIGATORIA)

- Se a migration legado ja rodou em producao: nao editar arquivo antigo; criar migration corretiva nova, idempotente e reversivel.
- Se a migration legado ainda nao rodou em producao: pode corrigir o arquivo diretamente.
- NUNCA usar `migrate:fresh` ou `migrate:reset` em producao.

## Safety Rule For Skill Origin

Nao deletar skills originais do caminho:

- `.agent/skills/` (relativo a raiz do projeto)

Politica obrigatoria: copy-only.

## Mapa de Testes

Consultar `docs/operacional/mapa-testes.md` para o inventario detalhado.

## Contexto para IAs (Antigravity, Cursor, etc.)

Antes de qualquer implementacao, consultar:

1. **AIDD Blueprint** — OBRIGATORIO ler `docs/BLUEPRINT-AIDD.md` para entender as 6 Fases do projeto.
2. **Iron Protocol** — embutido NESTE arquivo (secao 1) — 8 Leis inviolaveis, boot, gate de conclusao
3. `.agent/rules/kalibrium-context.md` — stack real, overrides de routing, regras adaptativas
3. `.agent/rules/mandatory-completeness.md` — checklist detalhado ponta a ponta
4. `.agent/rules/test-policy.md` — politica de testes inviolavel com protocolo de correcao
5. `.cursor/rules/` — regras tecnicas especificas (migrations, deploy, frontend, backend, completude)
6. `docs/` — documentacao por camadas (7 camadas)
