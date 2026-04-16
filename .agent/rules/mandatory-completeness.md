# COMPLETUDE OBRIGATORIA -- REGRA RIGIDA INVIOLAVEL

> [!CAUTION]
> **ESTA REGRA E CARREGADA EM TODA CONVERSA, TODA ACAO, TODO MOMENTO.**
> Nao existe "fora do escopo", "depois eu faco", "e so um placeholder". Se tocou, COMPLETA.
> **Violacao = tarefa INCOMPLETA e resultado INACEITAVEL.**

> **⚙️ HARNESS ENGINEERING — Modo operacional sempre-ligado (P-1).** Esta regra se compõe com o Harness: completude (Lei 1) é o *conteúdo* da entrega; Harness é o *formato* da entrega. Toda resposta final que envolva código segue o **fluxo de 7 passos** (entender → localizar → propor → implementar → verificar → corrigir → evidenciar) e o **formato de resposta de 7 itens obrigatórios + 1 opcional** (resumo + arquivos + motivo técnico + testes executados + resultado real + riscos remanescentes + próximo passo/recomendações [+ como desfazer]). A checklist de Gate Final é o *insumo* que alimenta o item 4/5 do formato Harness. Fonte canônica: `.agent/rules/harness-engineering.md`.

> **Autonomous Harness — mandato por perfil:** esta regra exige completude de agentes com mandato de escrita. O orquestrador nao audita nem corrige codigo; ele dispara agentes/subagentes com contexto limpo. Auditores read-only, consolidadores e verificadores NAO editam codigo; eles registram, classificam, deduplicam ou evidenciam lacunas para o corretor. Toda camada exige cinco auditores diferentes e nova rodada apos qualquer correcao.

---

## 1. PRINCIPIO FUNDAMENTAL: FLUXO PONTA A PONTA

**OBRIGATORIO:** Toda implementacao DEVE ser completa de ponta a ponta. Significa:

- **Frontend -> API -> Controller -> Service -> Model -> Migration -> Seed (se aplicavel)**
- O fluxo INTEIRO deve funcionar. Nao basta fazer "so o frontend" ou "so o backend".
- Se uma peca do fluxo nao existe, **DEVE SER CRIADA**.
- Se uma peca do fluxo esta quebrada, **DEVE SER CORRIGIDA**.

### O que "ponta a ponta" significa no KALIBRIUM

| Camada | DEVE existir e funcionar |
|--------|--------------------------|
| **Frontend** | Componente/page -> hook -> chamada API -> tratamento de erro -> feedback visual |
| **API Client** | Funcao em `@/lib/api` -> tipagem correta -> interceptors funcionando |
| **Rotas Backend** | Rota em `routes/api.php` -> middleware correto -> array syntax |
| **Controller** | Controller -> Form Request -> validacao -> resposta tipada |
| **Service/Logic** | Logica de negocio isolada -> tratamento de excecoes |
| **Model** | Eloquent Model -> relationships -> casts -> fillable |
| **Migration** | Tabela/coluna existe no banco -> com guards `hasTable`/`hasColumn` |
| **Testes** | Unit + Feature/Integration -> happy path + error path + edge cases |

> [!CAUTION]
> **VIOLACAO GRAVE:** Criar um componente frontend que chama uma API que nao existe no backend.
> **VIOLACAO GRAVE:** Criar uma rota no backend sem o Controller correspondente.
> **VIOLACAO GRAVE:** Criar um Controller que referencia um Model sem migration.
> **VIOLACAO GRAVE:** Entregar qualquer funcionalidade sem testes.

---

## 2. CHECKLIST DE INTEGRIDADE DO SISTEMA (OBRIGATORIO)

Antes de considerar QUALQUER tarefa como concluida, verificar:

### 2.1 Arquitetura e Blueprint (OBRIGATORIO)

- [ ] A implementacao respeita a metodologia descrita em `docs/BLUEPRINT-AIDD.md`?
- [ ] O codigo gerado reflete exatamente o que esta nas migrations (`backend/database/migrations/`) e Models (`backend/app/Models/`)?
- [ ] O fluxo obedece as regras de transicao de estado definidas no diagrama Mermaid do Bounded Context correspondente (`docs/modules/`)?

### 2.2 Rotas e Conexoes

- [ ] Toda rota no frontend (`React Router`) tem correspondencia no backend (`routes/api.php`)
- [ ] Toda chamada `api.get/post/put/delete` no frontend aponta para endpoint existente
- [ ] Toda rota no backend tem Controller com metodo implementado (nao vazio)
- [ ] Middleware de autenticacao/autorizacao esta aplicado corretamente
- [ ] Rotas literais vem ANTES de rotas com `{id}` (ordem correta)

### 2.2 Banco de Dados

- [ ] Toda coluna referenciada no codigo tem migration correspondente
- [ ] Migrations novas usam guards `hasTable`/`hasColumn` para idempotencia
- [ ] Relationships no Model (`hasMany`, `belongsTo`, etc.) estao corretas
- [ ] Foreign keys existem e apontam para tabelas corretas
- [ ] `$fillable`, `$casts`, `$hidden` estao atualizados no Model

### 2.3 Validacao e Seguranca

- [ ] Controllers usam Form Requests (nunca `$request->validate()` inline)
- [ ] Validacao do frontend espelha validacao do backend
- [ ] Permissoes (`can`, `authorize`, Spatie) estao aplicadas
- [ ] CSRF/Sanctum configurado corretamente
- [ ] **FormRequest authorize() tem logica REAL** — `return true;` sem verificacao e PROIBIDO (ver Lei 3b do Iron Protocol)
- [ ] **tenant_id e created_by sao atribuidos no controller** — NUNCA expostos como campos do FormRequest
- [ ] **Relacionamentos validam pertencimento ao tenant** — `exists:table,id` deve incluir where tenant_id

### 2.3b Performance e Listagens

- [ ] **Endpoints de listagem usam paginacao** — `->paginate()` ou `->simplePaginate()` (PROIBIDO: `::all()` ou `->get()` sem limite)
- [ ] **Controllers usam eager loading** — `->with([...])` para toda relationship referenciada no response
- [ ] **N+1 queries prevenidas** — se o response inclui dados de relationship, `with()` DEVE estar presente
- [ ] **Select otimizado** — usar `->select()` quando possivel para limitar colunas

### 2.4 Frontend Completo

- [ ] Estados de loading, erro, e vazio tratados no componente
- [ ] Feedback visual para acoes do usuario (toast, alert, etc.)
- [ ] Formularios com validacao client-side (React Hook Form + Zod)
- [ ] Tipos TypeScript corretos (zero `any`)
- [ ] `aria-label` em elementos interativos

### 2.5 Testes (OBRIGATORIO -- SEM EXCECAO)

- [ ] Toda funcionalidade nova tem testes
- [ ] Testes cobrem: happy path + error path + edge cases + limites
- [ ] Testes sao profissionais: nomes descritivos, cenarios realistas
- [ ] Testes seguem padrao AAA (Arrange-Act-Assert)
- [ ] Nenhum teste foi mascarado, pulado, comentado ou relaxado
- [ ] Testes existentes continuam passando apos a mudanca
- [ ] **5 cenarios MINIMOS por controller:** sucesso CRUD, validacao 422, cross-tenant 404, permissao 403, edge cases (ver `test-policy.md` para templates)
- [ ] **Testes validam estrutura do JSON** — assertJsonStructure() ou assertJsonPath(), NAO apenas status code
- [ ] **Teste cross-tenant obrigatorio** — criar recurso de outro tenant e verificar que retorna 404
- [ ] **Testes adaptativos por complexidade:** Features com logica = 8+ testes/controller. CRUDs simples = 4-5 testes (sucesso + 422 + cross-tenant). Bug fixes = regressao + afetados

---

## 3. PROIBICOES ABSOLUTAS DE INCOMPLETUDE

| PROIBIDO | Consequencia |
|----------|-------------|
| Deixar stub/placeholder sem implementacao real | Tarefa INCOMPLETA |
| Criar frontend sem backend correspondente | Tarefa INCOMPLETA |
| Criar backend sem frontend correspondente (quando aplicavel) | Tarefa INCOMPLETA |
| Criar rota sem Controller/metodo implementado | Tarefa INCOMPLETA |
| Criar Controller sem Form Request | Codigo REJEITADO |
| Criar Model sem migration | Tarefa INCOMPLETA |
| Referenciar coluna inexistente no banco | Tarefa INCOMPLETA |
| Criar hook/service que chama endpoint inexistente | Tarefa INCOMPLETA |
| Entregar funcionalidade sem testes | Tarefa INCOMPLETA |
| Deixar TODO/FIXME sem resolver | Tarefa INCOMPLETA |
| Dizer "isso pode ser feito depois" | **PROIBIDO** -- fazer AGORA |
| Entregar codigo que nao compila/builda | **VIOLACAO GRAVE** |
| Mascarar teste que falha | **VIOLACAO GRAVISSIMA** |
| Avancar para etapa N+1 sem completar N | **PROIBIDO** -- terminar etapa atual |
| Remover validacao/comportamento em refatoracao | **VIOLACAO GRAVISSIMA** |
| Refatorar sem inventario pre/pos | **VIOLACAO GRAVE** |
| Nao revisar git diff antes de concluir | **VIOLACAO GRAVE** |
| Cascata oportunistica >5 arquivos sem reportar | **VIOLACAO GRAVE** -- parar e consolidar |
| FormRequest authorize() com `return true` sem logica | **Codigo REJEITADO** -- implementar autorizacao real |
| Endpoint index() retornando Model::all() sem paginacao | **Codigo REJEITADO** -- usar paginate() |
| Controller sem eager loading em relationships | **Codigo REJEITADO** -- usar ->with([...]) |
| tenant_id ou created_by exposto no FormRequest | **Codigo REJEITADO** -- atribuir no controller |
| Testes de controller sem cenario cross-tenant | **Tarefa INCOMPLETA** -- adicionar teste de isolamento |
| Testes de controller sem cenario de validacao 422 | **Tarefa INCOMPLETA** -- adicionar teste de validacao |
| Controller com menos de 4 testes | **Tarefa INCOMPLETA** -- revisar contra cenarios minimos |

---

## 4. PROTOCOLO DE IMPLEMENTACAO PROATIVA

Quando a IA estiver trabalhando em qualquer tarefa:

### 4.1 Deteccao Automatica de Lacunas

A IA DEVE automaticamente detectar e implementar:

1. **Rotas faltantes**: se frontend chama `/api/xyz`, a rota DEVE existir no backend
2. **Controllers faltantes**: se rota referencia `XyzController@method`, o controller DEVE existir
3. **Form Requests faltantes**: se controller recebe dados, o Form Request DEVE existir
4. **Models faltantes**: se logica referencia `Xyz::find()`, o Model DEVE existir
5. **Migrations faltantes**: se Model referencia tabela, a migration DEVE existir
6. **Hooks/Services faltantes**: se componente usa `useXyz()`, o hook DEVE existir
7. **Tipos faltantes**: se interface TypeScript e referenciada, DEVE estar exportada
8. **Conexoes faltantes**: se dois modulos se comunicam, a ponte DEVE funcionar

### 4.2 Regra de Cascata

Ao implementar uma funcionalidade, seguir a **cascata completa**:

```
1. Migration (banco) -> 2. Model (Eloquent) -> 3. Form Request (validacao)
-> 4. Controller (logica) -> 5. Rota (api.php) -> 6. API Client (frontend)
-> 7. Hook/Service -> 8. Componente/Page -> 9. TESTES (todos os niveis)
```

**NUNCA** parar no meio da cascata. Se comecou, termina.

### 4.3 Correcao de Funcionalidades Existentes

- Se encontrar funcionalidade existente quebrada: **CORRIGIR IMEDIATAMENTE**
- Se encontrar funcionalidade existente incompleta: **COMPLETAR**
- Se encontrar funcionalidade existente sem testes: **CRIAR TESTES**
- **NAO perguntar** se deve corrigir. **CORRIGIR.**

**Excecao de mandato read-only:** se o agente estiver atuando como auditor, consolidador ou verificador no Autonomous Harness, NAO corrigir diretamente. Registrar finding estruturado, tipo de bloqueio e verificacao recomendada para o corretor.

---

## 4b. PRESERVACAO ABSOLUTA NA REESCRITA (OBRIGATORIO)

> [!CAUTION]
> **Reescrever ou refatorar codigo NAO e licenca para simplificar.**
> A nova versao DEVE ter no MINIMO os mesmos comportamentos da anterior. Pode ADICIONAR, nunca REMOVER.

### Inventario Pre/Pos Obrigatorio

Antes de qualquer refatoracao ou reescrita significativa:

**PASSO 1 — INVENTARIO ANTES:**
```
Listar todos:
- [ ] Endpoints/rotas publicas
- [ ] Methods publicos da classe
- [ ] Validacoes (cada regra de FormRequest ou check manual)
- [ ] Condicoes/branches (if/else, switch, early returns)
- [ ] Edge cases tratados (null, empty, boundary)
- [ ] Side effects (eventos, logs, notificacoes, cache)
- [ ] Permissoes/autorizacao verificadas
- [ ] Middlewares aplicados
```

**PASSO 2 — REESCREVER**

**PASSO 3 — INVENTARIO DEPOIS:**
```
Conferir item por item:
- [ ] Todos os endpoints/rotas ANTES existem DEPOIS?
- [ ] Todos os methods publicos ANTES existem DEPOIS?
- [ ] Todas as validacoes ANTES existem DEPOIS?
- [ ] Todas as condicoes/branches ANTES existem DEPOIS?
- [ ] Todos os edge cases ANTES existem DEPOIS?
- [ ] Todos os side effects ANTES existem DEPOIS?
- [ ] Todas as permissoes ANTES existem DEPOIS?
- [ ] Todos os middlewares ANTES existem DEPOIS?
```

**PASSO 4 — DIFF OBRIGATORIO:**
```bash
git diff --stat   # ver quais arquivos mudaram
git diff          # revisar CADA linha removida (prefixo -)
```
- Toda linha removida (prefixo `-`) deve ser JUSTIFICADA ou RESTAURADA
- Se a linha continha validacao, condicao, ou comportamento: RESTAURAR
- Se a linha era codigo morto genuino (nao chamado em nenhum lugar): pode remover

### O que NAO e justificativa para remover comportamento

| Argumento | Veredicto |
|-----------|-----------|
| "Simplificou o codigo" | **NAO ACEITO** — simplicidade nao justifica perda de funcionalidade |
| "Parecia redundante" | **NAO ACEITO** — pode haver cenario que depende disso |
| "Nao parece necessario" | **NAO ACEITO** — se existia, tinha motivo. Investigar antes |
| "Estava duplicado" | **ACEITO PARCIALMENTE** — somente se puder provar que ambos os caminhos levavam ao mesmo resultado |
| "O teste confirma que nao faz falta" | **ACEITO** — se o teste cobre o cenario e continua passando |

---

## 5. TESTES -- POLITICA RIGIDA REFORCADA

> [!CAUTION]
> **Esta secao REFORCA e COMPLEMENTA a `test-policy.md`.** Ambas se aplicam simultaneamente.

### 5.1 Quando criar testes (SEMPRE)

| Situacao | Acao |
|----------|------|
| Funcionalidade nova | **CRIAR** testes completos |
| Funcao/metodo novo | **CRIAR** teste especifico |
| Funcao/metodo alterado (refatoracao, bugfix, mudanca de comportamento) | **CRIAR/ATUALIZAR** teste especifico |
| Bug fix | **CRIAR** teste que reproduz o bug + verifica a correcao |
| Alteracao de fluxo | **CRIAR/ATUALIZAR** testes do fluxo |
| Migration nova | **CRIAR** teste que verifica a estrutura |
| Endpoint novo | **CRIAR** teste Feature/Integration |
| Componente novo | **CRIAR** teste de renderizacao + interacao |

### 5.2 Profundidade obrigatoria dos testes

Cada teste DEVE cobrir no MINIMO:

```
Happy path (fluxo normal, dados validos)
Error path (dados invalidos, erros esperados)
Edge cases (limites, valores vazios, nulos, extremos)
Autorizacao (usuario sem permissao)
Validacao (campos obrigatorios, formatos, limites)
```

### 5.3 Se o teste falhar

```
1. ANALISAR a causa raiz
2. Se o SISTEMA esta errado -> CORRIGIR O SISTEMA
3. Se o TESTE esta errado -> CORRIGIR O TESTE (documentar motivo)
4. NUNCA: comentar, skip, relaxar assertion, trocar valor esperado
```

---

## 5b. SEQUENCIAMENTO OBRIGATORIO DE ETAPAS

> [!CAUTION]
> **E PROIBIDO iniciar a Etapa N+1 de um plano se a Etapa N nao estiver 100% completa.**
> Nao existe "volto depois". Nao existe "avanco para desbloquear". Termina ANTES de avancar.

### Protocolo entre etapas de um plano

```
ETAPA N:
  1. Implementar TUDO da etapa (cascata completa)
  2. Rodar testes especificos da etapa
  3. Rodar suite completa (pest --parallel)
  4. Verificar Gate Final (checklist)
  5. Reportar conclusao com EVIDENCIA:
     - Quantos testes passaram
     - git diff --stat (resumo do que mudou)
     - Confirmacao de que nenhuma funcionalidade foi perdida
  6. SO ENTAO → iniciar Etapa N+1
```

### Situacoes onde isto se aplica

| Cenario | Regra |
|---------|-------|
| Plano com etapas numeradas (1, 2, 3...) | Completar etapa N ANTES de iniciar N+1 |
| Plano com fases (Fase 1, Fase 2...) | Completar fase N ANTES de iniciar N+1 |
| Tarefa com sub-tarefas independentes | Cada sub-tarefa deve ter Gate Final proprio |
| Bug fix que revela outro bug | Corrigir bug original PRIMEIRO, depois o novo |
| Refatoracao que requer mudancas em cadeia | Completar cadeia inteira antes de iniciar outra |

### Unica excecao permitida

Se a Etapa N+1 e **pre-requisito tecnico comprovado** da Etapa N (dependencia invertida), o agente PODE iniciar N+1 parcialmente, MAS:
1. DEVE documentar a justificativa explicitamente
2. DEVE retornar a Etapa N imediatamente apos desbloquear
3. DEVE completar Etapa N ANTES de completar N+1

---

## 6. GATE FINAL DE COMPLETUDE (OBRIGATORIO)

Antes de reportar QUALQUER trabalho como "concluido":

### Checklist Final Obrigatorio

```
=== FUNCIONALIDADE ===
[] O fluxo ponta a ponta funciona? (Frontend -> Backend -> Banco -> Resposta -> UI)
[] Todas as rotas necessarias existem e funcionam?
[] Todas as migrations foram criadas/atualizadas?
[] Todos os Models estao corretos com relationships e BelongsToTenant?

=== CONTROLLERS E FORM REQUESTS ===
[] Todos os Controllers tem Form Requests?
[] FormRequest authorize() tem logica REAL? (PROIBIDO: return true sem verificacao)
[] Endpoints de listagem (index) usam paginacao? (PROIBIDO: Model::all() ou ->get() sem limite)
[] Controllers usam eager loading (->with()) para relationships? (PROIBIDO: N+1 queries)
[] tenant_id e created_by sao atribuidos no controller, NAO expostos no FormRequest?

=== TESTES ===
[] Testes foram criados para TODA funcionalidade nova?
[] Testes cobrem os 5 cenarios MINIMOS? (sucesso, validacao 422, cross-tenant 404, permissao 403, edge cases)
[] Testes cobrem cenarios proporcionais a complexidade? (features=8+, CRUDs=4-5, bugs=regressao)
[] Testes validam estrutura JSON (assertJsonStructure), NAO apenas status code?
[] Testes existentes continuam passando?
[] Nenhum teste foi mascarado?

=== QUALIDADE ===
[] O frontend compila sem erros? (npm run build)
[] Zero console.log, zero any, zero dd()?
[] aria-label em elementos interativos?
[] Todos os TODOs/FIXMEs foram resolvidos?

=== REVISAO FINAL ===
[] DIFF REVISADO: git diff conferido — nenhuma funcionalidade removida silenciosamente
[] INVENTARIO (se refatoracao): endpoints/methods/validacoes ANTES = DEPOIS
[] SEQUENCIAMENTO (se plano multi-etapa): etapa atual 100% completa antes de avancar
```

> [!CAUTION]
> **Se ALGUM item acima nao foi cumprido, a tarefa NAO esta concluida.**
> **Voltar e completar ANTES de reportar.**

---

## 7. REGRA DE CARREGAMENTO OBRIGATORIO

Esta regra DEVE ser carregada e aplicada:

- Em TODA nova conversa
- Em TODA nova acao do agente
- Em TODA implementacao (nova ou alteracao)
- Em TODA correcao de bug
- Em TODA revisao de codigo
- Em TODA resposta que envolva codigo

**NAO existe excecao. NAO existe "fora do escopo". NAO existe "depois".**
