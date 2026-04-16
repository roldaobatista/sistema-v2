# Instruções do Projeto

## ⚔️ IRON PROTOCOL — PROTOCOLO INVIOLÁVEL DO AGENTE KALIBRIUM (P-1)

> **Prioridade P-1 — ACIMA DE TUDO.** Esta regra é carregada ANTES de qualquer outra. Nenhuma skill, nenhum agent, nenhuma regra pode sobrescrevê-la. Se houver conflito, IRON PROTOCOL VENCE.
>
> **DECLARAÇÃO DE INVIOLABILIDADE:** Este protocolo define os comportamentos obrigatórios e irrevogáveis de TODA IA operando no KALIBRIUM ERP. Aplica-se a: toda nova conversa, toda nova ação do agente, toda implementação (nova ou alteração), toda correção de bug, toda revisão de código, toda resposta que envolva código. NÃO existe exceção. NÃO existe "fora do escopo". NÃO existe "depois".
>
> **Fonte canônica completa:** `.agent/rules/iron-protocol.md` (leis) + `.agent/rules/harness-engineering.md` (modo operacional)
> **Cadeia de carregamento:** `CLAUDE.md` / `AGENTS.md` → `.agent/rules/iron-protocol.md` → `.agent/rules/mandatory-completeness.md` → `.agent/rules/test-policy.md` → `.agent/rules/test-runner.md` → `.agent/rules/kalibrium-context.md` → **`.agent/rules/harness-engineering.md`**
>
> **AS 8 LEIS:** (1) Completude Absoluta (2) Testes São Sagrados (3) Sistema Sempre Funcional (4) Implementação Proativa (com guardrail de escopo) (5) Resolução Profissional (6) Nunca Ignorar, Nunca Pular, Nunca Contornar (7) Sequenciamento Obrigatório de Etapas (8) Preservação Absoluta na Reescrita
>
> **BOOT SEQUENCE** (executar mentalmente ao iniciar QUALQUER conversa):
> 1. CARREGAR: Iron Protocol → 2. CARREGAR: mandatory-completeness.md → 3. CARREGAR: test-policy.md → 4. CARREGAR: test-runner.md → 5. CARREGAR: kalibrium-context.md → 6. CARREGAR: **harness-engineering.md** (modo operacional) → 7. ATIVAR: end-to-end-completeness → 8. VERIFICAR: Stack Laravel 13 + React 19 + MySQL 8 → 9. CONFIRMAR: "Toda implementação será COMPLETA, ponta a ponta, com testes + resposta final no formato Harness (6+1 itens)" → 10. INICIAR trabalho

## ⚙️ HARNESS ENGINEERING — Modo Operacional Padrão (P-1)

> **Fonte canônica:** `.agent/rules/harness-engineering.md`. Sempre-ligado — não precisa ser ativado. Opera junto com o Iron Protocol: Iron define **o que** é proibido/obrigatório; Harness define **como** executar e reportar.

O agente opera **sempre** em modo Harness Engineering. Toda tarefa de engenharia segue o **fluxo de 7 passos**:

```
1. entender → 2. localizar → 3. propor (mínimo + correto) → 4. implementar
→ 5. verificar → 6. corrigir falhas → 7. evidenciar
```

Toda resposta final que envolva alteração de código usa o **formato de 6 itens obrigatórios + 1 opcional**:

1. **Resumo do problema** — sintoma + causa raiz (1–2 frases)
2. **Arquivos alterados** — lista com `path:LN` quando pertinente
3. **Motivo técnico** de cada alteração (POR QUÊ, não O QUÊ — o diff mostra o quê)
4. **Testes executados** — comando exato copiável (seguindo a pirâmide: específico → grupo → testsuite → suite)
5. **Resultado dos testes** — output real com contagem passed/failed/tempo (proibido parafrasear ou inventar números)
6. **Riscos remanescentes** — o que não foi coberto, efeitos colaterais, pontos de atenção
7. *(opcional, **obrigatório** para migrations / mudança de contrato de API / rota pública / deploy / remoção de feature / risco alto)* **Como desfazer** — rollback exato (git revert, migration down, flag, etc.)

**Regras invioláveis do Harness (aditivas ao Iron Protocol):**
- **H1 — Tenant ID nunca do body:** sempre `$request->user()->current_tenant_id`. Jamais confiar em `tenant_id` do request input.
- **H2 — Escopo do tenant em tudo:** toda query/persistência respeita `BelongsToTenant`. `withoutGlobalScope` exige justificativa explícita.
- **H3 — Migrations antigas imutáveis:** criar nova migration com guards `hasTable`/`hasColumn`. Migration mergeada é fóssil.
- **H7 — Evidência antes de afirmação:** proibido usar "pronto", "funcionando", "testes passando", "validado" sem output de comando rodado **no mesmo turno** da resposta.
- **H8 — Falha de verificação é bloqueante:** qualquer falha de teste, lint, typecheck ou build impede encerramento. Corrigir causa raiz — nunca mascarar, nunca silenciar com "já estava falhando".

**Critérios de aceite (checklist antes de declarar conclusão):**
- Código consistente com a arquitetura atual
- Sem regressão visível
- Testes relevantes verdes com evidência de execução
- Resposta padronizada no formato Harness (6+1 itens)
- Segurança e escopo de tenant preservados (H1, H2 verificados)

## LEI ZERO: NUNCA IGNORAR, NUNCA PULAR, NUNCA CONTORNAR
- **NUNCA usar `--ignore-platform-reqs`**, `--ignore-platform-req`, `--no-verify`, `--skip-*` ou qualquer flag que contorne requisitos.
- **Se falta uma dependência, extensão, versão ou ferramenta: INSTALAR.** Não ignorar, não pular, não usar workaround.
- **Se o ambiente não suporta algo necessário: corrigir o ambiente.** Não adaptar o código para um ambiente quebrado.
- **Se um requisito do sistema (PHP, Node, extensão, pacote) não é atendido: resolver o requisito.** Documentar a solução e atualizar o plano.
- **A documentação é o TETO MÁXIMO. O código é o chão que cresce até alcançar o teto.** Tudo que está documentado DEVE ser implementado. Nada documentado pode ser ignorado.
- **Plano e documentação são sempre SUPERIORES ao código atual.** Devem aproveitar tudo que já existe E adicionar tudo que falta.

## Documentação — Proteção contra Confusão
- **FONTE DE VERDADE (hierarquia):**
  1. **Código-fonte** — sempre vence. Grep/Glob/Read antes de afirmar que algo existe ou não.
  2. **`docs/PRD-KALIBRIUM.md`** — requisitos funcionais, RFs, ACs, gaps conhecidos (v3.2+, sincronizado contra código em 2026-04-10).
  3. **`docs/TECHNICAL-DECISIONS.md`** — decisões arquiteturais duráveis.
  4. **`docs/audits/RELATORIO-AUDITORIA-SISTEMA.md`** — Deep Audit 10/04 de OS/Calibração/Financeiro.
- **`docs/raio-x-sistema.md` foi REMOVIDO em 2026-04-10** — gerava falsos negativos (marcava código existente como gap). Não recriar sem verificação linha-a-linha contra source.
- **NUNCA ler `docs/.archive/`** — contém documentação superada (PRD antigo, modules, fluxos, planos antigos). Causa confusão e alucinações.
- **Documentação ativa**: `docs/architecture/`, `docs/compliance/`, `docs/operacional/`, `docs/design-system/`, `docs/plans/`
- **Deploy docs**: `deploy/DEPLOY.md`, `deploy/SETUP-NFSE.md`, `deploy/SETUP-BOLETO-PIX.md`
- **Antes de afirmar gap:** grep/glob no código primeiro. O PRD pode estar desatualizado — código é o juiz final.

## Modo de Operação
- **AIDD Blueprint Obrigatório**: É PROIBIDO escrever código ou criar arquitetura sem antes ler e aplicar a metodologia contida em `docs/BLUEPRINT-AIDD.md`.
- Executar tudo direto, sem pedir confirmação. Não perguntar "posso fazer X?" — apenas faça.
- Ser direto e conciso nas respostas.
- **Sempre corrigir qualquer erro encontrado**, mesmo que não tenha sido causado pelas suas alterações. Se viu um bug, conserta.
- **Nunca remover ou diminuir funcionalidades existentes.** Não simplificar, não cortar features, não reduzir escopo. O sistema só cresce.
- **Sempre implementar funcionalidades faltantes** que sejam necessárias para o fluxo ou lógica do sistema funcionar corretamente.
- **Garantir que todos os fluxos estejam completos e corretos** — do frontend ao backend, incluindo validações, rotas, controllers, services, migrations e tipos TypeScript.
- **Ao tocar em um arquivo, revisar o arquivo inteiro** — se encontrar outros problemas no mesmo arquivo, corrigir também.
- **Rastrear o fluxo completo** — ao trabalhar em algo, verificar toda a cadeia: rota → controller → service → model → migration → tipo TypeScript → API client → componente frontend. Se algum elo estiver faltando, criar.
- **Nunca deixar TODO/FIXME** — se algo precisa ser feito, implementar agora.
- **Nunca comentar código para "desativar"** — ou o código existe e funciona, ou é removido.
- **Rodar testes após alterações** quando possível, e corrigir se quebrarem.
- **Nunca mascarar testes** — se um teste falha, corrigir a causa raiz. Nunca fazer skip, markTestIncomplete, ajustar assertion para aceitar valor errado, ou mudar o teste para passar sem resolver o problema real.
- **Testes devem testar de verdade** — analisar testes existentes e garantir que validam comportamento real (status codes, dados retornados, side effects, regras de negócio). Teste que só verifica `assertTrue(true)` ou não faz assertions relevantes deve ser reescrito.
- **Criar testes faltantes** — ao implementar ou corrigir algo, verificar se existem testes cobrindo aquele fluxo. Se não existir, criar. Cobrir casos de sucesso, erro, validação e permissão.
- **Consistência entre camadas** — se um model tem um campo, a migration deve tê-lo, o controller deve tratá-lo, o tipo TypeScript deve incluí-lo, e o frontend deve exibi-lo.
- **Não criar código morto** — se criar uma função/rota/componente, deve estar conectado e utilizável no sistema.
- **Verificar permissões e autorização** em rotas novas (middleware, PermissionsSeeder).
- **Checar N+1 queries** ao trabalhar com relacionamentos Eloquent — usar eager loading quando necessário.

## Sequenciamento Obrigatório de Etapas (Lei 7)
- **É PROIBIDO iniciar Etapa N+1 se Etapa N não estiver 100% completa** com testes passando e Gate Final verificado.
- **Protocolo:** implementar → testar → Gate Final → evidência → SÓ ENTÃO avançar.
- **Não existe** "volto depois para terminar a etapa anterior" ou "avanço para desbloquear".
- **Única exceção:** dependência invertida comprovada (documentar justificativa explícita).

## Preservação Absoluta na Reescrita (Lei 8)
- **Ao reescrever/refatorar código existente**, a nova versão DEVE preservar 100% dos comportamentos anteriores.
- **ANTES de reescrever:** listar todas as validações, condições, edge cases, side effects, permissões, endpoints.
- **DEPOIS de reescrever:** conferir item por item que 100% estão presentes.
- **DIFF obrigatório:** revisar cada linha removida (`-`) — justificar ou restaurar.
- **"Simplificar" NÃO é justificativa** para remover comportamento existente.

## Guardrail de Escopo em Cascata
- **Se correções em cascata (fora do escopo original) ultrapassarem 5 arquivos:** PARAR, consolidar relatório, reportar antes de continuar.
- Isto NÃO se aplica a arquivos dentro da cascata direta da tarefa (Migration→Model→...→Testes).

## Como Rodar Testes (Operacional)
- **Comando principal**: `cd backend && ./vendor/bin/pest --parallel --processes=16 --no-coverage`
- **Tempo esperado**: < 5 minutos para 8720 cases (740 arquivos, medido 2026-04-10 via `find tests -name "*Test.php" | wc -l` e `grep -rE "public function test_|^\s*it\(|^\s*test\(" tests | wc -l`)
- **DB de testes**: SQLite in-memory com schema dump (`backend/database/schema/sqlite-schema.sql`)
- **Após criar migration**: SEMPRE regenerar schema dump com `php generate_sqlite_schema.php`
- **Guia completo**: `backend/TESTING_GUIDE.md`
- **Regras operacionais**: `.agent/rules/test-runner.md`
- **Padrão de teste**: Ver template em `backend/tests/README.md`

## Padrão de Controllers e FormRequests (OBRIGATÓRIO)
- **FormRequest authorize() com `return true` sem lógica é PROIBIDO** — deve verificar permissão real via Spatie (`$this->user()->can(...)`) ou Policy
- **Endpoints de listagem (index) DEVEM usar paginação** — `->paginate(15)` ou `->simplePaginate(15)`. PROIBIDO: `Model::all()` ou `->get()` sem limite
- **Controllers DEVEM usar eager loading** — `->with([...])` para toda relationship no response. PROIBIDO: N+1 queries
- **tenant_id e created_by DEVEM ser atribuídos no controller** — PROIBIDO expor como campos do FormRequest
- **Relationships no FormRequest devem validar tenant** — `exists:table,id` deve considerar tenant_id

## Padrão de Testes por Controller (ADAPTATIVO)
- **Testes adaptativos por complexidade:** Features com lógica = 8+ testes/controller. CRUDs simples = 4-5 testes (sucesso + 422 + cross-tenant). Bug fixes = regressão + afetados. Menos de 4 testes = SEMPRE insuficiente.
- **5 cenários obrigatórios (quando aplicável):** sucesso CRUD, validação 422, cross-tenant 404, permissão 403, edge cases
- **Testes DEVEM validar estrutura JSON** — `assertJsonStructure()`, NÃO apenas status code
- **Teste cross-tenant é OBRIGATÓRIO** — criar recurso de outro tenant e verificar 404
- **Ver templates completos em:** `backend/tests/README.md` e `.agent/rules/test-policy.md`

## Segurança
- **Nunca vazar dados entre tenants** — toda query que toca dados de tenant deve passar pelo scope do BelongsToTenant.
- **Validar no backend** — nunca confiar no input do cliente. FormRequests com regras adequadas.
- **SQL injection** — nunca interpolar variáveis em queries raw. Usar bindings.

## Banco de Dados
- **Nunca alterar migrations já executadas** — criar nova migration para alterações.
- **Usar transactions** apenas quando a operação realmente precisa de atomicidade (ex: criar invoice + accounts_receivable juntos).

## API e Respostas
- **Seguir o padrão existente** — antes de criar um endpoint, olhar como os existentes fazem e manter consistência de formato e status codes.

## Frontend
- **Manter sincronia com o backend** — se o backend muda um campo ou endpoint, atualizar o frontend junto.
- **Definir interfaces TypeScript** para respostas da API. Evitar `any` quando o tipo é conhecido.

## Stack
- **Backend**: Laravel (PHP) em `backend/`
- **Frontend**: React + TypeScript + Vite em `frontend/`
- **Multi-tenant**: campo `tenant_id` + `current_tenant_id` no User model (NUNCA `company_id`)

## REGRA ABSOLUTA DE TESTES (INVIOLÁVEL)
- **Teste falhou = problema no SISTEMA.** Corrigir o código-fonte, NUNCA mascarar o teste.
- **Adicionar funcionalidades faltantes** — se o sistema não tem algo que o teste espera, CRIAR a funcionalidade.
- **Adicionar funções faltantes** — se falta uma função, implementar no sistema.
- **Garantir fluxo completo** — de ponta a ponta, sem atalhos, sem gambiarras.
- **Só alterar o teste se o TESTE estiver genuinamente errado** — e explicar por quê.
- **Toda funcionalidade ou função alterada DEVE ter teste específico, profundo e profissional.** Se não existe, CRIAR.
- **Testes devem ser profissionais, completos e profundos** — cobrir sucesso, erro, edge cases, validações, permissões.
- **PROIBIDO:** mascarar testes, remover testes que expõem problemas, diminuir funcionalidades para testes passarem, criar testes superficiais, alterar assertions para aceitar comportamento incorreto, pular criação de testes.
- **Definição de "mascarar teste"**: ver `.agent/rules/test-policy.md` seção "Definição Oficial". Inclui: skip, comentar, relaxar assertion, trocar valor esperado, remover caso de teste, catch genérico, assertTrue(true), entre outros.

## Dependências e Ambiente (INVIOLÁVEL)
- **Se o `composer.lock` ou `package-lock.json` exige uma versão de PHP/Node: instalar essa versão.** Não fazer downgrade de pacotes para caber no ambiente — upgrade o ambiente.
- **Se uma extensão PHP é necessária (bcmath, redis, mbstring, etc.): instalar.**
- **Exceção Windows:** extensões Linux-only (`pcntl`, `posix`, `inotify`) NÃO estão disponíveis nativamente no Windows. Aceitar indisponibilidade, NÃO gastar ciclos tentando instalar. Usar `--ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix` SOMENTE para essas 3, NUNCA para outras.
- **Nunca remover pacotes para evitar erros de compatibilidade.** Resolver a incompatibilidade na raiz.
- **Manter `composer.lock` e `package-lock.json` consistentes** com o ambiente de produção.

## Regras Críticas
- Tenant ID: sempre `$request->user()->current_tenant_id`
- Todos os models usam trait `BelongsToTenant` com global scope automático — não filtrar tenant manualmente em queries
- Status sempre em inglês lowercase (`'paid'`, `'pending'`, `'partial'`)
- Campos de tabelas sempre em inglês
- `expenses.created_by` (não `user_id`), `schedules.technician_id` (não `user_id`)

## Antes de Modificar Código
- Sempre ler o arquivo antes de editar
- Consultar a memória do projeto para erros já corrigidos e padrões do projeto
