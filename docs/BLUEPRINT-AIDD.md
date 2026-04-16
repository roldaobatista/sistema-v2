# Blueprint: SaaS Orientado por IA (AIDD)

Este documento descreve o "Blueprint" (Plano Diretor) exato e a explicacao tecnica de como e por que criamos cada agrupamento de arquivos antes de escrever a primeira linha de codigo, projetado para o desenvolvimento de um SaaS do zero utilizando Automacao Orientada por IA (AIDD - AI-Driven Development).

Nesta metodologia, a documentacao nao e apenas "texto explicativo", mas sim a **programacao do comportamento da IA**. Se a documentacao for ambigua, a IA vai "alucinar" (inventar regras, errar o escopo ou misturar tecnologias).

Este esqueleto metodologico garante que qualquer Agente IA saiba exatamente interpretar o sistema e escrever o codigo sem se perder no meio do caminho.

---

## Fase 1: Fundacao Arquitetural (`docs/architecture/`)

**Objetivo:** Eliminar qualquer margem para a IA tomar decisoes estruturais erradas. Aqui nos definimos o "chassi" do software.

* **`STACK-TECNOLOGICA.md` / `INFRAESTRUTURA.md`**:
  * **Como foi feito:** Uma lista declarativa e estrita das tecnologias exatas (Bill of Materials). Backend: Laravel 13 + PHP 8.2+. Frontend: React 19 + Vite + TypeScript. Banco: MySQL 8 (strict mode). Cache/Filas: Redis. Real-time: Reverb (WebSockets).
  * **Por que existe:** Impede que o agente de codigo decida usar Next.js num dia e Angular no outro. Ele fecha o escopo de dependencias. Qualquer biblioteca fora do BOM e **proibida** sem ADR aprovado.
* **`ARQUITETURA.md` / `CODEBASE.md`**:
  * **Como foi feito:** Define os padroes de pasta, nomenclaturas (PSR-12, Pint), e a separacao entre as camadas (Controller → Service → Model).
  * **Por que existe:** Garante que todo arquivo gerado va para o diretorio correto mantendo o codigo limpo e consistente.
* **Decisoes e Padroes Numerados (00 a 20)**:
  * **Como foi feito:** 21 guias isolados, cada um especificando uma regra de ouro (Multi-tenancy, CQRS, Cache, API Versionada, Mobile/Offline, etc.).
  * **Por que existe:** Para que a IA compreenda como o sistema lida com regras de negocio transversais, bloqueando falhas estruturais. Cada padrao contem marcadores `[AI_RULE]` e `[AI_RULE_CRITICAL]` que sao restricoes inviolaveis.
* **`ADR.md` (Architecture Decision Records)**:
  * **Como foi feito:** Um registro documentando "Por que escolhemos X em vez de Y" para cada decisao significativa.
  * **Por que existe:** Mantem o historico conceitual para evitar que a IA sugira refatorar tudo para outra tecnologia porque "parece melhor".

## Fase 2: O Modelo de Dados Pre-Desenvolvimento (`backend/database/migrations/`)

**Objetivo:** Estabelecer o esquema de dados como a Primeira Fonte de Verdade (Single Source of Truth).

* **Migrations Laravel (376 arquivos)**:
  * **Como foi feito:** Modelagem completa do banco de dados relacional via Laravel Migrations antes de escrever logica de negocio. Todas as tabelas, indices, foreign keys e constraints. Cada Model Eloquent (368 models) espelha exatamente uma migration.
  * **Por que existe:** Em AIDD, os agentes de Backend olham primeiro para o modelo de dados para inferir quais rotas (APIs) devem criar. Se o banco esta perfeito, 80% do CRUD gerado pela IA sera perfeito de primeira.
* **Models Eloquent com `BelongsToTenant`**:
  * **Como foi feito:** Todo model que pertence a um tenant usa a trait `BelongsToTenant`, que aplica um global scope automatico filtrando por `tenant_id`. O tenant e determinado por `$request->user()->current_tenant_id`.
  * **Por que existe:** Impede vazamento de dados entre tenants. A IA NUNCA precisa adicionar `where('tenant_id', ...)` manualmente — o scope global ja faz isso.
* **Factories e Seeders**:
  * **Como foi feito:** Cada Model tem sua Factory correspondente para testes. Seeders populam dados iniciais (permissoes, lookups, tenant de demonstracao).
  * **Por que existe:** Permite que agentes de IA escrevam testes que criam dados realisticos sem precisar inventar estruturas de dados.

## Fase 3: Engenharia de Dominio e "Bounded Contexts" (`docs/modules/`)

> ⚠️ **STATUS: EM PLANEJAMENTO (2026-04-10)** — A pasta `docs/modules/` esta vazia. A documentacao por modulo descrita abaixo e o **objetivo metodologico** desta fase, mas ainda nao foi materializada. Ate que seja, agentes IA devem usar `docs/PRD-KALIBRIUM.md` (requisitos e gaps conhecidos) combinado com **leitura direta dos Models/Controllers/Services do dominio alvo** — o codigo e sempre a fonte definitiva. `docs/raio-x-sistema.md` foi removido em 2026-04-10 por gerar falsos negativos. NAO assuma que `docs/modules/{Modulo}.md` existe.

**Objetivo:** Dividir o monolito em partes pequenas e independentes (Modulos) para que a janela de contexto da IA nao estoure.

* **Documento Individual por Modulo (29 modulos)** *(planejado)*:
  * **Como sera feito:** Cada modulo ganhara seu escopo definido via frontmatter YAML (`type`, `domain`, `owner`), suas proprias entidades (Models) isoladas que ele gerencia, e quais eventos ele dispara e escuta (comunicacao entre modulos).
  * **Por que existe:** A IA e ruim em tratar sistemas gigantes de uma vez so. Com a fragmentacao modular, o escopo fica contido (ex: "Programe apenas o Helpdesk considerando estas regras"). O agente le UM arquivo de modulo e tem tudo que precisa.
* **Maquinas de Estado em Mermaid**:
  * **Como foi feito:** Blocos de codigo em markdown usando a sintaxe Mermaid (`stateDiagram-v2`) para desenhar fluxos geometricos de status.
  * **Por que existe:** E a ferramenta mais importante do AIDD. Em vez de explicar em portugues como funciona uma ordem de servico, o Mermaid da um **modelo matematico finito** para a IA criar o codigo de mudanca de status (`state machines`) sem pular etapas. Cada transicao invalida e bloqueada automaticamente.
* **Guard Rails `[AI_RULE]`**:
  * **Como foi feito:** Marcadores inline no markdown que a IA deve tratar como restricoes absolutas.
  * **Por que existe:** Diferencia "sugestao" de "obrigacao". Um `[AI_RULE_CRITICAL]` nunca pode ser ignorado — e o equivalente a um `assert()` na documentacao.
* **Regras Cross-Domain**:
  * **Como foi feito:** Secao em cada modulo listando como ele se conecta a outros modulos (ex: "WorkOrder.completed → dispara FinanceService.generateInvoice").
  * **Por que existe:** Impede que a IA crie modulos isolados demais ou acoplados demais. Define os pontos exatos de integracao.

## Fase 4: Limites de Compliance e Leis (`docs/compliance/`)

**Objetivo:** Traduzir leis do mundo real para guard rails (trilhos) de codigo logico.

* **ISO 17025 (Metrologia e Laboratorios)**:
  * **Como foi feito:** Mapeamos os requisitos da norma: assinatura dupla em certificados, rastreabilidade ambiental, custodia documental, soft delete obrigatorio.
  * **Por que existe:** O modulo Lab gera certificados de calibracao com validade legal. Qualquer violacao pode invalidar a acreditacao do laboratorio.
* **ISO 9001 (Gestao da Qualidade)**:
  * **Como foi feito:** RNCs (Relatorios de Nao Conformidade), acoes corretivas CAPA, feature flags condicionais por tenant, auditorias internas.
  * **Por que existe:** Define o framework de qualidade que permeia todos os modulos.
* **Portaria 671/2021 + CLT (Ponto Digital)**:
  * **Como foi feito:** Requisitos de REP-P nativo: GPS + selfie obrigatorios, hash chain para AFD, espelho de ponto, violacoes CLT (interjornada 11h, intrajornada, hora extra), eventos eSocial S-2230/S-1000.
  * **Por que existe:** O modulo HR implementa um Registrador Eletronico de Ponto via Software. Nao-compliance pode gerar multas trabalhistas.

## Fase 5: Design System e Contratos UI/UX (`docs/design-system/`)

**Objetivo:** Consistencia visual absoluta sem a IA ter que adivinhar cores.

* **Design Tokens (`TOKENS.md`)**:
  * **Como foi feito:** Paleta de cores (brand navy/slate, nao azul generico), tipografia (Inter + JetBrains Mono), espacamento, sombras — tudo em JSON estruturado para Tailwind CSS.
  * **Por que existe:** Evita o efeito "Frankenstein" comum quando a IA gera telas. Cores roxas/violetas sao **proibidas** para evitar templates genericos.
* **Padroes de Componentes (`COMPONENTES.md`)**:
  * **Como foi feito:** Regras de acessibilidade (a11y), padroes de loading/error states, convencoes de formularios, tabelas, modais.
  * **Por que existe:** Garante que todo componente gerado pela IA siga o mesmo padrao visual e de interacao.

## Fase 6: Sistema de Prompts e Automacao (`prompts/`)

> ⚠️ **STATUS: EM PLANEJAMENTO (2026-04-10)** — A pasta `prompts/` esta vazia. Templates de prompt e system prompts ainda nao foram criados. Por enquanto, os "guard rails" sao codificados em `.agent/rules/` (iron-protocol, mandatory-completeness, test-policy, test-runner) + `CLAUDE.md` + `AGENTS.md`. NAO assuma que existem templates em `prompts/`.

**Objetivo:** Procedimento operacional padrao de como falar com os agentes.

* **Templates de Prompt e System Prompts** *(planejado)*:
  * **Como sera feito:** Gabaritos de metadados ensinando ao Orquestrador onde pesquisar as informacoes dependendo do pedido humano.
  * **Por que existe:** Mantem o determinismo. Define as "Definicoes de Pronto (DoD)" para os Agentes verificarem testes de unidade, lint, etc, antes de entregar o codigo.

## Fase 7: Verificacao Continua de Qualidade (`CLAUDE.md` + `AGENTS.md` + `.cursor/rules/`)

**Objetivo:** Garantir qualidade via regras automatizadas integradas ao workflow dos agentes.

* **Regras Inviolaveis Embutidas nos Agentes**:
  * **Como foi feito:** `CLAUDE.md` define regras absolutas de completude, testes obrigatorios e fluxo ponta-a-ponta. `AGENTS.md` define o Iron Protocol com 8 Leis Inviolaveis e Gate Final de Conclusao. `.cursor/rules/` contem regras automaticas carregadas por contexto. `.agent/rules/` contem as regras-fonte detalhadas (iron-protocol, mandatory-completeness, test-policy, test-runner).
  * **Por que existe:** Substitui auditorias manuais por camada com verificacao continua integrada ao workflow do agente. Cada agente valida qualidade em tempo real, nao pos-facto.
* **Padroes de Qualidade por Controller (Lei 3b do Iron Protocol)**:
  * **Como foi feito:** Regras explicitas e verificaveis para todo controller: FormRequest com authorize() real (PROIBIDO `return true` sem logica), paginacao obrigatoria em index(), eager loading obrigatorio, atribuicao de tenant_id/created_by no controller.
  * **Por que existe:** Impede que agentes produzam codigo "tecnicamente completo" mas com falhas de seguranca (authorize vazio), performance (N+1, sem paginacao) ou qualidade de testes (sem cross-tenant, sem 422).
* **Cenarios Minimos de Teste por Controller**:
  * **Como foi feito:** Todo controller DEVE ter minimo 8 testes cobrindo 5 cenarios: sucesso CRUD, validacao 422, cross-tenant 404, permissao 403, edge cases. Templates completos em `.agent/rules/test-policy.md` e `backend/tests/README.md`.
  * **Por que existe:** Impede testes superficiais que verificam apenas happy path. O cenario cross-tenant e obrigatorio porque BelongsToTenant e a camada critica de seguranca do sistema multi-tenant.

## Fase 8: Operacao e Deploy (`docs/operacional/`)

**Objetivo:** O sistema precisa ser deployavel e operavel por agentes.

* **Guias Operacionais**:
  * **Como foi feito:** Deploy completo (do zero a producao), mapa de testes, regras do projeto, checklists de dominio, troubleshooting.
  * **Por que existe:** Um agente de IA precisa saber como colocar o sistema no ar, rodar testes e diagnosticar problemas.

## Fase 9: Enterprise Expansion e Machine-to-Machine (M2M)

> 🔮 **STATUS: FUTURE ROADMAP (2026-04-10)** — Esta fase descreve uma expansao aspiracional. Nao ha artefatos concretos, especificacao detalhada ou roadmap implementado. Trate como **visao de longo prazo**, nao como requisito atual. Modulos parcialmente existentes hoje no codigo (ex: Logistics, IoT) seguem o padrao geral do sistema, nao um framework M2M dedicado.

**Objetivo:** Evoluir a plataforma ERP para uma workstation empresarial industrial e E2E, integrando portais externos e automação de hardware.

* **7 Módulos Avançados** *(roadmap futuro)*:
  * **Como sera feito:** Expansão tática do Kalibrium abrangendo: `Projects` (Integração Financeira), `Omnichannel` (Inbox universal), `Logistics` (ZPL e Reversa), `SupplierPortal` (B2B Magic Links), `IoT_Telemetry` (Captura Serial), `FixedAssets` (Baixa Patrimonial) e `Analytics_BI` (ETL Data Lake).
  * **Por que existe:** Estende a fundação para lidar com chão de fábrica, B2B externo e conformidade contábil automatizada, cobrindo o restante da operação sem intervenção humana.

---

## Resumo do Workflow para Agentes IA (SaaS do Zero)

As instrucoes base para os agentes sao:

```
1. Ler Stack e Infra (O que e e Onde vai rodar)
   → docs/architecture/STACK-TECNOLOGICA.md + INFRAESTRUTURA.md

2. Ler Arquitetura e Regras (Como o codigo e organizado)
   → docs/architecture/ARQUITETURA.md + CODEBASE.md + padroes 00-20

3. Entender o Modelo de Dados (Como os dados sao guardados)
   → backend/database/migrations/ + backend/app/Models/

4. Ler o Bounded Context do Modulo Alvo (O que programar)
   → docs/modules/{Modulo}.md *(em planejamento — ate la, usar `docs/PRD-KALIBRIUM.md` (RFs + gaps) + leitura direta de Models/Controllers/Services do dominio alvo. O codigo e sempre o juiz final.)*

5. Verificar Compliance (O que NAO pode dar errado)
   → docs/compliance/ (se o modulo e regulado)

6. Aplicar Design System (Como sera a interface)
   → docs/design-system/TOKENS.md + COMPONENTES.md

7. Escrever Codigo + Testes (TDD)
   → Seguir Controller → Service → Model → Migration → Types → Frontend

8. Auditar (O codigo esta correto?)
   → CLAUDE.md + AGENTS.md (regras inviolaveis) + testes automatizados

9. Deployar
   → docs/operacional/deploy-completo.md
```

### Regras Inviolaveis para Agentes

1. **Tenant isolation**: Todo model com dados de cliente USA `BelongsToTenant`. NUNCA filtrar `tenant_id` manualmente.
2. **Tenant ID**: Sempre via `$request->user()->current_tenant_id`. NUNCA `company_id`.
3. **Status em ingles**: `'paid'`, `'pending'`, `'partial'` — NUNCA em portugues.
4. **Campos em ingles**: Todas as colunas do banco em ingles.
5. **Validacao no backend**: NUNCA confiar no input do cliente. FormRequests com regras adequadas.
6. **Sem TODO/FIXME**: Se algo precisa ser feito, implementar agora.
7. **Sem codigo morto**: Se nao esta conectado, remover.
8. **Testes obrigatorios**: Toda funcionalidade ou funcao alterada DEVE ter teste. Minimo 8 testes por controller (ver `.agent/rules/test-policy.md`).
9. **Migrations imutaveis**: NUNCA alterar migration ja executada — criar nova.
10. **Consistencia entre camadas**: Se um campo existe no Model, deve existir na migration, no controller, no type TS e no frontend.
11. **FormRequest authorize() REAL**: `authorize()` retornando `return true;` sem verificacao e PROIBIDO. Deve usar `$this->user()->can(...)` via Spatie ou Policy. Unica excecao: endpoints genuinamente publicos (comentar `// Public endpoint`).
12. **Paginacao obrigatoria**: Todo `index()` DEVE usar `->paginate()` ou `->simplePaginate()`. PROIBIDO `Model::all()` ou `->get()` sem limite em listagens.
13. **Eager loading obrigatorio**: Todo `index()`/`show()` com relationships DEVE usar `->with([...])`. PROIBIDO N+1 queries.
14. **tenant_id/created_by no controller**: Atribuir via `$request->user()->current_tenant_id` e `$request->user()->id`. PROIBIDO expor como campos do FormRequest.
15. **Teste cross-tenant obrigatorio**: Todo controller DEVE ter teste que cria recurso de outro tenant e verifica 404 (nao 403). BelongsToTenant retorna 404 via global scope.
16. **Estrutura JSON validada em testes**: Testes DEVEM usar `assertJsonStructure()` ou `assertJsonPath()`. PROIBIDO testes que verificam apenas status code.

### Exemplos de Referencia no Codebase

Ao implementar novos modulos, consultar estes exemplos que seguem os padroes corretamente:

| Padrao | Arquivo de Referencia | O que observar |
|--------|----------------------|----------------|
| Paginacao com safety cap | `EquipmentController.php` | `->paginate(min((int) $request->input('per_page', 25), 100))` |
| Eager loading com callbacks | `EquipmentController.php` | `->with(['customer:id,name', 'equipmentModel.products'])` |
| authorize() com Spatie | `ApprovePayrollRequest.php` | `$this->user()->hasPermissionTo('hr.payroll.manage')` |
| Teste cross-tenant | `AccountPayableTest.php` | `test_cannot_access_other_tenant_payable()` → assert 404 |
| Teste validacao 422 | `EquipmentControllerTest.php` | `assertJsonValidationErrors(['serial_number'])` |
| Teste isolamento critico | `AuthorizationTenantCriticalTest.php` | Permissao + tenant = 404 (permissao sozinha nao basta) |
