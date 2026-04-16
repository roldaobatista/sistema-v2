# 🏗️ Master Builder: Criando o Sistema do Zero com IA

O grande poder do **AIDD (AI-Driven Development)** neste repositório é que a documentação atua como "código-fonte" para a IA. Se amanhã você deletar a pasta `backend/` e `frontend/`, você poderá recriar o sistema idêntico apenas copiando e colando os prompts abaixo em um Agente (Antigravity, Cursor, ou Claude/ChatGPT).

Este documento contém a **Sequência Exata de Prompts** para reconstruir o SaaS do zero, módulo a módulo, forçando a IA a ler as `Regras de Ouro` antes de codificar.

---

## Passo 1: O Boot Arquitetural (Fundação)

Abra seu Agente de IA na raiz do projeto vazio (com apenas as pastas `docs/`, `prompts/` e arquivos de regra) e envie o Prompt 1:

```markdown
Vou iniciar a construção de um ERP SaaS. Você será o Líder Técnico.
Antes de escrever qualquer código ou rodar qualquer comando de setup, você PRECISA entender as restrições do nosso ecossistema.

Tarefa 1: Leia OBRIGATORIAMENTE os arquivos abaixo nesta ordem:
1. `docs/BLUEPRINT-AIDD.md` (Para entender nossa metodologia de IA).
2. `docs/architecture/STACK-TECNOLOGICA.md` (Para entender nossas limitações tecnológicas e versões exatas).
3. `backend/database/migrations/` + `backend/app/Models/` (Para conhecer o schema do banco via migrations Laravel, que sao a Fonte da Verdade de dados).
4. `docs/modules/Core.md` (Para entender o modelo multi-tenant e RBAC).
5. `CLAUDE.md` e `AGENTS.md` (Regras do projeto e Iron Protocol).
6. `.agent/rules/iron-protocol.md` + `.agent/rules/test-policy.md` + `.agent/rules/mandatory-completeness.md` (Protocolo de qualidade obrigatório).

Responda apenas "Li os fundamentos arquiteturais" quando terminar. Não gere código ainda.
```

---

## Passo 2: O Setup dos Repositórios (Boilerplate)

```markdown
Agora que você conhece a Stack Tecnológica e o Banco de Dados, vamos criar os esqueletos:

Tarefa 2:
1. Crie a infraestrutura do Backend na pasta `backend/` usando Laravel 12 (PHP 8.4+).
2. Configure a autenticação Sanctum/JWT e o scaffolding do banco baseados nas migrations e Models de Users e Tenants em `backend/`.
3. Crie a infraestrutura do Frontend na pasta `frontend/` usando React 19 (Vite) + TypeScript.
4. Crie no Frontend a estrutura de pastas proposta no `BLUEPRINT-AIDD.md`.
5. Implemente o middleware de isolamento multi-tenant conforme `Core.md`.

Gere o código, revise se está nos padrões e me avise quando o Boilerplate compilar sem erros.
```

---

## Passo 3: Injetando o Design System (UI/UX)

```markdown
Nosso backend está de pé, mas nosso frontend está vazio. Nossa UI deve ser perfeitamente padronizada.

Tarefa 3:
1. Leia a pasta `docs/design-system/`.
2. Extraia as cores, bordas, fontes e tipografia exatas listadas ali.
3. Configure o arquivo global de CSS/Tailwind no `frontend/` usando RIGOROSAMENTE essas regras.
4. Crie uma pasta `frontend/src/components/ui/` e crie no mínimo os componentes Button, Input, Modal, Table, Card, Badge e Alert seguindo o Design System.

Proibido: Usar qualquer cor "hardcoded" que não exista nos tokens.
```

---

## Passo 4: Construindo os Módulos (Loop pelos 29 Bounded Contexts)

Este prompt é o **coração do AIDD**. Você executa ele **uma vez por módulo**, substituindo `{MÓDULO}` pelo nome. Repita para todos os 29 Bounded Contexts.

```markdown
Vamos implementar o módulo "{MÓDULO}".

Tarefa 4:
1. Leia o arquivo `docs/modules/{MÓDULO}.md`. Nele há uma Máquina de Estados (Mermaid) e Guard Rails [AI_RULE] obrigatórios.
2. Analise nas migrations (`backend/database/migrations/`) as tabelas listadas na seção "Entidades".
3. Verifique se este módulo tem requisitos de compliance: Lab/Quality/Inmetro → `docs/compliance/ISO-17025.md`, HR/ESocial → `docs/compliance/PORTARIA-671.md`, Quality → `docs/compliance/ISO-9001.md`.
4. Leia `docs/modules/INTEGRACOES-CROSS-MODULE.md` para verificar se este módulo participa de integrações cross-domain.
5. Verifique os fluxos relacionados em `docs/fluxos/` que envolvem este módulo.
6. No Backend: Crie Rota, Controller(s), FormRequest(s), Service(s) e Policy conforme o módulo. O Service DEVE OBRIGATORIAMENTE respeitar as transições de status e os Guard Rails.
7. No Frontend: Crie as páginas de listagem, criação e edição deste módulo respeitando os componentes do Design System (`docs/design-system/TOKENS.md` e `COMPONENTES.md`).
8. Implemente os Event Listeners e comportamentos Cross-Domain listados no documento do módulo.
9. Crie testes completos: unit tests para services, feature tests para endpoints (happy path + error path + edge cases + autorização), frontend tests com Vitest.

Definition of Done:
- Backend: `php artisan test` → zero falhas, testes criados para TODA funcionalidade nova
- Frontend: `npm run build` → zero erros, `npx tsc --noEmit` → zero erros de tipo
- Transições de estado respeitam o diagrama Mermaid
- Zero TODO/FIXME no código
- Guard Rails [AI_RULE] implementados e testados
- Checklist de `.agent/rules/mandatory-completeness.md` verificado

**Padrões de Qualidade OBRIGATÓRIOS por Módulo (verificar ANTES de marcar como concluído):**

FormRequests:
- [ ] `authorize()` com lógica REAL (Spatie `can()` ou Policy). PROIBIDO `return true;` sem verificação
- [ ] `tenant_id` e `created_by` NUNCA como campos do FormRequest — atribuir no Controller
- [ ] Validações `exists:` em FKs consideram o tenant do usuário

Controllers:
- [ ] `index()` usa `->paginate(min((int) $request->input('per_page', 25), 100))` — PROIBIDO `Model::all()`
- [ ] `index()` e `show()` usam `->with([...])` para eager loading — PROIBIDO N+1
- [ ] `store()` atribui `tenant_id` via `$request->user()->current_tenant_id` e `created_by` via `$request->user()->id`

Testes (mínimo 8 por controller):
- [ ] Sucesso CRUD (index 200, store 201, show 200, update 200, destroy 200/204) com assertDatabaseHas
- [ ] Validação 422 — campos obrigatórios ausentes + dados inválidos com assertJsonValidationErrors
- [ ] Cross-tenant 404 — recurso de outro tenant retorna 404 (NÃO 403) — OBRIGATÓRIO
- [ ] Permissão 403 — acesso sem permissão adequada (remover Gate bypass)
- [ ] Edge cases — paginação, assertJsonStructure, eager loading
- [ ] Ver templates em `backend/tests/README.md` e `.agent/rules/test-policy.md`

Referências de boas práticas no codebase:
- Paginação: `EquipmentController.php` → `paginate(min(...))`
- Eager loading: `EquipmentController.php` → `->with(['customer:id,name', ...])`
- authorize() real: `ApprovePayrollRequest.php` → `hasPermissionTo('hr.payroll.manage')`
- Teste cross-tenant: `AccountPayableTest.php` → `test_cannot_access_other_tenant_payable()`
- Teste crítico: `AuthorizationTenantCriticalTest.php` → permissão + tenant juntos

> **Se qualquer item acima NÃO foi cumprido, o módulo NÃO está completo. Voltar e corrigir ANTES de avançar.**
```

### Verificação Cross-Module (OBRIGATÓRIO antes de implementar)
- [ ] Ler `docs/modules/INTEGRACOES-CROSS-MODULE.md` — seção do módulo sendo implementado
- [ ] Verificar eventos que o módulo EMITE e confirmar que listeners existem nos módulos destino
- [ ] Verificar eventos que o módulo CONSOME e confirmar que dispatchers existem nos módulos fonte
- [ ] Registrar novos eventos no Registro Mestre de Eventos (seção final do arquivo)
- [ ] Definir sync vs async para cada integração
- [ ] Definir retry policy para integrações async

### Ordem de Execução Recomendada (Dependências)

| Fase | Módulos | Justificativa |
| ---- | ------- | ------------- |
| 4.1 | `Core`, `Integrations` | Fundação: tenants, users, IAM, webhooks |
| 4.2 | `Email`, `Agenda`, `Alerts` | Comunicação, tarefas e notificações (usados por todos) |
| 4.3 | `CRM`, `Quotes`, `Pricing` | Comercial: pipeline, orçamentos, preços |
| 4.4 | `Contracts`, `Finance`, `Fiscal` | Financeiro: contratos, AP/AR, NF-e |
| 4.5 | `Inventory`, `Procurement` | Estoque e compras |
| 4.6 | `WorkOrders`, `Service-Calls`, `Helpdesk` | Operacional: OS, chamados, SLA |
| 4.7 | `Operational`, `Fleet` | Campo: checklists, rotas, frota |
| 4.8 | `Lab`, `Inmetro`, `Quality`, `WeightTool` | Metrologia, qualidade ISO e calibração |
| 4.9 | `HR`, `Recruitment`, `ESocial`, `Mobile` | Pessoas: RH, R&S, obrigações + PWA operações campo |
| 4.10 | `Portal`, `TvDashboard`, `Innovation` | Interfaces externas: portal cliente, TV + melhoria interna |

### Procedimento de Rollback entre Fases

> **Regra:** Cada fase DEVE terminar com todos os testes passando (`php artisan test` = zero falhas) ANTES de iniciar a próxima fase. Se uma fase quebrar testes de fases anteriores, resolver ANTES de avançar.

| Situação | Procedimento |
|----------|-------------|
| Fase N quebra testes da Fase N-1 | **PARAR.** Analisar o conflito. Corrigir na Fase N sem alterar contratos da Fase N-1. Rodar suite completa antes de prosseguir. |
| Módulo parcialmente implementado (controllers existem, services não) | **Completar** o que falta usando `docs/modules/{Modulo}.md` como spec. NÃO recriar o que já existe. |
| Migration conflita com migration de fase anterior (FK duplicada, coluna já existente) | **Criar migration incremental** (`add_X_to_Y`) em vez de alterar migration existente. Regenerar schema dump. |
| Dois módulos da mesma fase precisam de entidade compartilhada | **Implementar a entidade no primeiro módulo** da lista. O segundo módulo importa o Model — NÃO duplicar. |
| Testes passam isolados mas falham em `--parallel` | **Eliminar estado compartilhado** (static vars, singletons, cache global). Cada teste deve ser autossuficiente. |

**Regra de ouro:** Nunca avançar para Fase N+1 com testes vermelhos. O custo de corrigir cresce exponencialmente com cada fase acumulada.

---

## Passo 4.5: Validação de Fluxos e Integrações Cross-Domain

Após implementar todos os módulos individualmente, valide os fluxos que cruzam módulos.

Tarefa 4.5:
1. Leia `docs/modules/INTEGRACOES-CROSS-MODULE.md` — valide que as 6 integrações documentadas funcionam de ponta a ponta.
2. Percorra os fluxos em `docs/fluxos/` e verifique que cada fluxo funciona completamente:
   - `CICLO-COMERCIAL.md` (CRM → Quotes → WorkOrders → Finance)
   - `FATURAMENTO-POS-SERVICO.md` (WorkOrder → Invoice → NF-e → Payment)
   - `CICLO-TICKET-SUPORTE.md` (Helpdesk → Assignment → Resolution → SLA)
   - `ADMISSAO-FUNCIONARIO.md` (Recruitment → HR → eSocial S-2200)
   - E todos os demais fluxos documentados.
3. Crie testes de integração que validem os fluxos cross-module mais críticos (ver `docs/operacional/CRITICAL-TEST-PATHS.md`).

Definition of Done: Todos os fluxos documentados em `docs/fluxos/` funcionam end-to-end sem erros.

---

## Passo 5: Auditoria Legal e Compliance (Refino)

```markdown
Nosso MVP está pronto, mas o SaaS atende a laboratórios regulados por lei.

Tarefa 5:
1. Leia tudo dentro de `docs/compliance/` (ISO-17025, Portaria 671, ISO-9001).
2. Varra os Controllers do Backend criados nos passos anteriores.
3. Injete a Trilha de Auditoria Universal (HashChainService) em todas as edições e exclusões de registros sensíveis.
4. Garanta double sign-off condicional no Lab se tenant.strict_iso_17025 = true.
5. Verifique que todos os documentos versionados (DocumentVersion) são imutáveis.

Mapeamento módulo → compliance:
- Lab, Quality, Inmetro, WeightTool → `docs/compliance/ISO-17025.md` (calibração, incerteza GUM, dupla assinatura)
- HR, ESocial → `docs/compliance/PORTARIA-671.md` (ponto digital, eSocial S-2200/S-2230/S-2299, AFD hash chain)
- Quality, Operational → `docs/compliance/ISO-9001.md` (RNC, CAPA, revisão pela direção)
```

---

## Passo 6: Integrações Externas

```markdown
O sistema precisa se comunicar com o mundo exterior.

Tarefa 6:
1. Leia `docs/modules/Integrations.md`.
2. Implemente o Circuit Breaker para toda chamada HTTP externa.
3. Configure webhooks com validação HMAC.
4. Implemente as integrações: WhatsApp, Google Calendar, Auvo, BrasilAPI, ViaCEP.
5. Crie o Health Check centralizado em `/api/v1/integrations/health`.
```

---

## Passo 6.5: Validação Operacional

Tarefa 6.5:
1. Valide que o sistema atende os benchmarks de `docs/operacional/PERFORMANCE-BENCHMARKS.md` (API p95<500ms, DB queries<100ms, bundle<300KB gzipped).
2. Execute os critical test paths de `docs/operacional/CRITICAL-TEST-PATHS.md` (5 smoke tests + 8 critical paths).
3. Verifique que os cenários de `docs/operacional/TROUBLESHOOTING-GERAL.md` têm respostas adequadas (Redis, MySQL, Frontend, WebSocket, Docker).
4. Confirme que os procedimentos de `docs/operacional/ROLLBACK-PROCEDURES.md` são executáveis.

---

## Gate Final por Passo (OBRIGATÓRIO antes de avançar)

Antes de iniciar o próximo Passo, verificar TODOS estes itens. Se QUALQUER item falhar, o Passo NÃO está completo:

```
□ Testes passando: `cd backend && ./vendor/bin/pest --parallel --processes=16 --no-coverage` → zero falhas
□ Frontend compila: `cd frontend && npm run build` → zero erros
□ FormRequests authorize() com lógica REAL? (grep -r "return true" em FormRequests novos → ZERO resultados)
□ Controllers index() com paginação? (grep "::all()" em Controllers novos → ZERO resultados)
□ Controllers com eager loading? (verificar ->with() em todo index/show que retorna relationships)
□ tenant_id/created_by atribuídos no controller? (grep "tenant_id" em FormRequest rules → ZERO resultados)
□ Mínimo 8 testes por controller novo?
□ Testes cross-tenant 404 existem para todo controller novo?
□ Testes validação 422 existem para todo controller novo?
□ assertJsonStructure em testes de listagem?
□ git diff revisado — nenhuma funcionalidade removida silenciosamente
```

> **Comandos de verificação rápida:**
> ```bash
> # Verificar authorize() vazio
> grep -rn "return true" backend/app/Http/Requests/ --include="*.php" | grep authorize
> # Verificar Model::all() sem paginação
> grep -rn "::all()" backend/app/Http/Controllers/ --include="*.php"
> # Contar testes por controller
> grep -c "function test_\|it(" backend/tests/Feature/Api/V1/**/*Test.php
> ```

---

## Passo 7: Refactoring e Dívida Técnica

```markdown
O sistema está funcional, mas precisamos garantir qualidade interna antes de escalar.

Tarefa 7:
1. **N+1 Queries**: Varra todos os Controllers e identifique relacionamentos Eloquent sem eager loading. Adicione `->with()` ou `$with` no Model onde necessário. Referência: `EquipmentController.php` mostra o padrão correto com `->with(['customer:id,name', 'equipmentModel.products:id,name,code'])`.
2. **Duplicação de código**: Identifique lógica duplicada entre Controllers/Services. Extraia para Traits, Base Classes ou Services compartilhados.
3. **Fat Controllers**: Nenhum Controller deve ter mais de 80 linhas por método. Mova lógica de negócio para Services, validação para FormRequests, e formatação para Resources/Transformers.
4. **Código morto**: Remova rotas não utilizadas, imports não referenciados, métodos nunca chamados e variáveis não lidas.
5. **Type Safety**: Elimine todo `any` no TypeScript frontend. Adicione return types em todos os métodos PHP. Garanta que interfaces TS refletem exatamente os Resources do backend.
6. **Error Handling**: Todo Service deve ter try/catch com logging adequado. Nenhum `catch` vazio. Erros de domínio devem usar Exceptions customizadas (ex: `InsufficientStockException`).
7. **Hardcoded Config**: Mova qualquer valor hardcoded (URLs, limites, timeouts) para `config/`, `.env` ou `TenantSettings`.

Verificação:
- `cd backend && ./vendor/bin/phpstan analyse --level=6`
- `cd frontend && npx tsc --noEmit`
- Ambos devem passar com zero erros.
```

---

## Passo 8: Performance Optimization Checklist

```markdown
O sistema está limpo internamente. Agora precisamos garantir que ele performa sob carga real.

Tarefa 8:
1. **Indexes no DB**: Revise todas as migrations. Toda coluna usada em `WHERE`, `ORDER BY`, `JOIN` ou `FOREIGN KEY` deve ter index. Crie migrations adicionais para indexes faltantes.
2. **Query Performance**: Nenhuma query deve ultrapassar 500ms. Ative o query log (`DB::enableQueryLog()`) e identifique queries lentas. Otimize com indexes, eager loading ou cache.
3. **Cache Strategy**: Implemente cache Redis para:
   - Listagens frequentes (tenants, users, permissions): cache por 5min com invalidação on-write.
   - Configurações de tenant (`TenantSettings`): cache por 15min.
   - Dashboards e relatórios: cache por 1-5min conforme criticidade.
4. **Frontend Bundle**: O bundle principal deve ter menos de 500KB gzipped. Use `npx vite-bundle-visualizer` para identificar dependências pesadas. Aplique code-splitting por rota com `React.lazy()`.
5. **Otimização de Imagens**: Logos e anexos devem ser processados com compressão. Implemente resize server-side para thumbnails.
6. **API Response Time**: p95 de todos os endpoints deve ser < 500ms. Endpoints de listagem devem usar paginação (max 50 por página).
7. **Queue Health**: Jobs de longa duração (email, NF-e, relatórios) devem rodar em filas dedicadas. Monitore failed_jobs e implemente retry com backoff.
8. **WebSocket (Reverb)**: Verifique que eventos são broadcastados apenas para os canais necessários. Implemente heartbeat e reconexão automática no frontend.

Verificação:
- `php artisan db:monitor` — conexões ativas
- `php artisan queue:monitor` — filas saudáveis
- `npx vite build --report` — tamanho do bundle
- `curl -w "%{time_total}" -o /dev/null -s http://localhost/api/v1/health` — tempo de resposta
```

---

## Passo 9: Security Audit Before Production

```markdown
Último passo antes do deploy. Segurança é inegociável.

Tarefa 9:
1. **Tenant Isolation Test**: Crie um teste automatizado que loga como User do Tenant A e tenta acessar/modificar dados do Tenant B. DEVE retornar 403 ou dados vazios em 100% dos endpoints.
2. **SQL Injection**: Varra todo o codebase por `DB::raw()`, `whereRaw()`, `selectRaw()`. Toda interpolação de variável DEVE usar bindings (`?` ou named parameters). Zero exceções.
3. **XSS Prevention**: No frontend, verifique que nenhum dado do usuário é renderizado via `dangerouslySetInnerHTML` sem sanitização. No backend, valide que responses usam encoding adequado.
4. **CSRF Protection**: Confirme que todo formulário e mutation request envia o token CSRF. Sanctum deve estar configurado com domínio correto em `SANCTUM_STATEFUL_DOMAINS`.
5. **Auth em todos os endpoints**: Varra `routes/api.php`. TODO endpoint (exceto login, register, health) DEVE estar dentro de `middleware('auth:sanctum')`. Sem exceções.
6. **Rate Limiting**: Login e endpoints públicos devem ter rate limiting (`throttle:5,1` para login, `throttle:60,1` para APIs autenticadas). Configurar em `RouteServiceProvider` ou `bootstrap/app.php`.
7. **File Upload Validation**: Todo upload deve validar: tipo MIME (whitelist), tamanho máximo (10MB default), extensão. Armazenar fora de `public/` com nomes hasheados.
8. **Secrets Audit**: Verifique que `.env` não está no git. Verifique que nenhum token/secret está hardcoded no código. Busque por patterns: `sk_live`, `Bearer`, `password`, API keys em strings.
9. **Dependency Audit**: Execute `cd backend && composer audit` e `cd frontend && npm audit`. Corrija todas as vulnerabilidades HIGH e CRITICAL.
10. **Security Headers**: Confirme os headers: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Strict-Transport-Security`, `Content-Security-Policy`. Configurar via middleware Laravel ou nginx.

Verificação:
- `cd backend && composer audit`
- `cd frontend && npm audit`
- `cd backend && grep -rn "DB::raw\|whereRaw\|selectRaw" app/ --include="*.php"` — revisar cada ocorrência
- `cd backend && php artisan route:list | grep -v "auth:sanctum"` — deve listar apenas rotas públicas
```

---

## Como usar na prática hoje?
Se você precisa construir um módulo "novo" (ex: Vendas), você não precisa rodar os Passos 1 a 3. Basta:
1. Criar o `docs/modules/Vendas.md` com a máquina de estados Mermaid
2. Criar as migrations Laravel necessarias
3. Rodar o **Passo 4** no chat da IA

A documentação é o código que programa a IA. Atualize o doc, e a IA atualiza o sistema.
