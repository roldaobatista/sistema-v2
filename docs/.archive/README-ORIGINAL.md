# KALIBRIUM ERP — Plano Diretor para Construcao por Agentes IA

> **Metodologia**: AIDD (AI-Driven Development) — [Blueprint Completo](BLUEPRINT-AIDD.md)
> **Stack**: Laravel 12 + React 19 + MySQL 8 + Redis + Reverb
> **Atualizado em**: 24/03/2026

---

## Objetivo deste Repositorio

Este repositorio contem a **especificacao completa e executavel** de um ERP SaaS para empresas de assistencia tecnica, servico de campo e laboratorios de metrologia. Toda a documentacao foi projetada para que **agentes de IA construam e mantenham o sistema de forma autonoma, deterministica e sem alucinacoes**.

A documentacao nao e "texto explicativo" — e a **programacao do comportamento da IA**. Cada arquivo define regras, restricoes, maquinas de estado e guard rails que os agentes devem seguir ao pe da letra.

### Publico-Alvo do Sistema

- Empresas de assistencia tecnica e servico de campo
- Laboratorios de calibracao e metrologia (ISO 17025)
- Empresas com frota de veiculos e tecnicos externos
- Operacoes que exigem compliance regulatorio (INMETRO, eSocial, Portaria 671)

---

## AVISO PARA AGENTES IA (LEITURA CRITICA)

1. **NUNCA** leia a pasta `.archive/` — contem documentacao legada que causara alucinacoes
2. **SEMPRE** consulte esta documentacao ANTES de escrever codigo
3. **SIGA** as maquinas de estado Mermaid ao pe da letra — elas sao o modelo matematico do sistema
4. **RESPEITE** os `[AI_RULE]` e `[AI_RULE_CRITICAL]` — sao restricoes inviolaveis
5. **VERIFIQUE** compliance antes de tocar em modulos regulados (Lab, HR, Fiscal, eSocial)

---

## Ordem de Leitura para Agentes IA

Um agente que vai trabalhar neste sistema deve ler a documentacao nesta ordem:

### Fase 1: Fundacao Arquitetural (`architecture/`)
>
> "O que e o sistema, como esta organizado, quais sao as regras inviolaveis"

| Prioridade | Documento | Propósito |
|-----------|----------|-----------|
| 1 | [`ARQUITETURA.md`](architecture/ARQUITETURA.md) | Visao geral e leis inviolaveis do sistema |
| 2 | [`STACK-TECNOLOGICA.md`](architecture/STACK-TECNOLOGICA.md) | Bill of Materials — tecnologias exatas permitidas |
| 3 | [`CODEBASE.md`](architecture/CODEBASE.md) | Regras de codebase (PSR-12, Pint, convencoes) |
| 4 | [`06-6-modelo-de-multi-tenancy.md`](architecture/06-6-modelo-de-multi-tenancy.md) | Multi-tenancy (regra mais critica do sistema) |
| 5 | [`04-4-estrutura-de-um-modulo.md`](architecture/04-4-estrutura-de-um-módulo.md) | Estrutura de diretorios padrao |

### Fase 2: Dominios e Bounded Contexts (`modules/`)
>
> "O que o sistema faz, modulo por modulo, com maquinas de estado"

Cada modulo define: entidades, estados, transicoes, eventos, regras de negocio e restricoes.

### Fase 3: Compliance e Regulatorio (`compliance/`)
>
> "O que NAO pode dar errado — leis, normas, requisitos legais"

### Fase 4: Design System (`design-system/`)
>
> "Como o sistema parece — paleta, tipografia, componentes"

### Fase 5: Operacional (`operacional/`)
>
> "Como deployar, testar e operar o sistema"

---

## Bounded Contexts — Os 29 Dominios do Sistema

O sistema e um **modular monolith** dividido em 29 bounded contexts principais. Cada modulo tem sua propria especificacao com frontmatter YAML, lista de entidades, Mermaid State Machine, Guard Rails `[AI_RULE]` e regras Cross-Domain.

### Modulos de Negocio Principal

| Modulo | Especificacao | Dominio | O que faz |
| ------ | ------------- | ------- | --------- |
| Ordens de Servico | [`WorkOrders.md`](modules/WorkOrders.md) | `workorders` | Ciclo completo de OS: criacao, agendamento, despacho, execucao em campo, finalizacao e faturamento |
| Financeiro | [`Finance.md`](modules/Finance.md) | `finance` | Contas a receber/pagar, fluxo de caixa, conciliacao bancaria, comissoes de tecnicos |
| Fiscal (NF-e/NFS-e) | [`Fiscal.md`](modules/Fiscal.md) | `fiscal` | Emissao de notas fiscais eletronicas, integracao SEFAZ, DANFE, impostos |
| CRM & Vendas | [`CRM.md`](modules/CRM.md) | `crm` | Pipeline de vendas, leads, oportunidades, follow-ups, conversao |
| Orcamentos | [`Quotes.md`](modules/Quotes.md) | `quotes` | Criacao, versionamento, envio, aprovacao e conversao em OS |
| Contratos | [`Contracts.md`](modules/Contracts.md) | `contracts` | Gestao de contratos, SLA, renovacao automatica, faturamento recorrente |
| Precificacao | [`Pricing.md`](modules/Pricing.md) | `pricing` | Tabelas de preco, markup, descontos, regras por cliente/contrato |

### Modulos Operacionais

| Modulo | Especificacao | Dominio | O que faz |
| ------ | ------------- | ------- | --------- |
| Chamados Tecnicos | [`Service-Calls.md`](modules/Service-Calls.md) | `service_calls` | Abertura, triagem, atribuicao, SLA e resolucao de chamados |
| Helpdesk & SLA | [`Helpdesk.md`](modules/Helpdesk.md) | `helpdesk` | Tickets internos, tracking de SLA, escalonamento, metricas |
| Operacional | [`Operational.md`](modules/Operational.md) | `operational` | Checklists de campo, rotas otimizadas, execucao mobile |
| Portal do Cliente | [`Portal.md`](modules/Portal.md) | `portal` | Acesso externo: abertura de chamados, orcamentos, certificados, NFs |
| Estoque & Inventario | [`Inventory.md`](modules/Inventory.md) | `inventory` | Pecas, movimentacoes (entrada/saida/transferencia), reservas, inventario |
| Compras | [`Procurement.md`](modules/Procurement.md) | `procurement` | Requisicoes, cotacoes de fornecedor, pedidos de compra, recebimento |
| Frota | [`Fleet.md`](modules/Fleet.md) | `fleet` | Veiculos, manutencao preventiva/corretiva, abastecimento, km |

### Modulos Tecnicos / Laboratorio

| Modulo | Especificacao | Dominio | O que faz |
| ------ | ------------- | ------- | --------- |
| Laboratorio & Metrologia | [`Lab.md`](modules/Lab.md) | `lab` | Certificados de calibracao, instrumentos, padroes, incerteza de medicao |
| INMETRO & Inteligencia | [`Inmetro.md`](modules/Inmetro.md) | `inmetro` | Lacres, verificacoes iniciais/subsequentes, conformidade metrologia legal |
| Qualidade & ISO | [`Quality.md`](modules/Quality.md) | `quality` | RNCs, acoes corretivas (CAPA), auditorias internas, indicadores |

### Modulos de Pessoas & RH

| Modulo | Especificacao | Dominio | O que faz |
| ------ | ------------- | ------- | --------- |
| Recursos Humanos | [`HR.md`](modules/HR.md) | `hr` | Ponto digital REP-P (Portaria 671), ferias CLT, folha, banco de horas |
| Recrutamento & Selecao | [`Recruitment.md`](modules/Recruitment.md) | `recruitment` | Vagas, candidatos, pipeline de selecao, onboarding |
| eSocial | [`ESocial.md`](modules/ESocial.md) | `esocial` | Eventos S-1000, S-2230, integracao com governo federal |

### Modulos de Plataforma

| Modulo | Especificacao | Dominio | O que faz |
| ------ | ------------- | ------- | --------- |
| Core | [`Core.md`](modules/Core.md) | `core` | Multi-tenancy, IAM (Spatie), cadastros auxiliares (lookups), audit log |
| Email & Comunicacao | [`Email.md`](modules/Email.md) | `email` | Templates, filas de envio, tracking, inbox unificado |
| Agenda & Tarefas | [`Agenda.md`](modules/Agenda.md) | `agenda` | Calendario, agendamentos, tarefas, lembretes, sincronizacao |
| TV Dashboard | [`TvDashboard.md`](modules/TvDashboard.md) | `tv_dashboard` | Paineis real-time (Reverb), cameras RTSP/WebRTC, KPIs operacionais |
| Integracoes | [`Integrations.md`](modules/Integrations.md) | `integrations` | WhatsApp, SEFAZ, eSocial, gateways de pagamento, APIs externas |

---

## Documentacao Tecnica Completa

### Arquitetura (`architecture/` — 27 documentos)

Fundacao completa do sistema. Define o "chassi" que impede agentes IA de tomarem decisoes estruturais erradas.

| # | Documento | O que define |
|---|----------|-------------|
| 00 | [`00-introducao.md`](architecture/00-introducao.md) | Mapa de navegacao dos padroes arquiteturais |
| 01 | [`01-escopo-arquitetural.md`](architecture/01-1-escopo-arquitetural-e-rastreabilidade.md) | Escopo, limites e rastreabilidade de decisoes |
| 02 | [`02-modular-monolith.md`](architecture/02-2-por-que-modular-monolith.md) | Justificativa tecnica do modular monolith vs microservices |
| 03 | [`03-bounded-contexts.md`](architecture/03-3-bounded-contexts-domínios.md) | Mapeamento dos 29 dominios e suas fronteiras |
| 04 | [`04-estrutura-modulo.md`](architecture/04-4-estrutura-de-um-módulo.md) | Topologia de pastas obrigatoria por modulo |
| 05 | [`05-ownership.md`](architecture/05-5-ownership-e-fronteiras-por-contexto.md) | Quem e dono de cada entidade e servico |
| 06 | [`06-multi-tenancy.md`](architecture/06-6-modelo-de-multi-tenancy.md) | Modelo de multi-tenancy (BelongsToTenant + tenant_id) |
| 07 | [`07-comunicacao.md`](architecture/07-7-comunicação-entre-módulos.md) | Como modulos se comunicam (Events, Services, DTOs) |
| 08 | [`08-eventos.md`](architecture/08-8-semântica-de-eventos-e-mensageria.md) | Semantica de eventos e mensageria (Laravel Events + Reverb) |
| 09 | [`09-cqrs.md`](architecture/09-9-cqrs-command-query-responsibility-segregation.md) | CQRS — separacao de leitura e escrita |
| 10 | [`10-transacoes.md`](architecture/10-10-consistência-transacional.md) | Consistencia transacional e sagas |
| 11 | [`11-mobile-offline.md`](architecture/11-11-arquitetura-mobileoffline-e-sincronização.md) | Arquitetura mobile/offline (PWA) e sincronizacao |
| 12 | [`12-anti-corruption.md`](architecture/12-12-anti-corruption-layer-integrações-externas.md) | Anti-corruption layer para integracoes externas |
| 13 | [`13-observabilidade.md`](architecture/13-13-observabilidade-arquitetural.md) | Logs, metricas, health checks, alertas |
| 14 | [`14-camadas.md`](architecture/14-14-camadas-da-aplicação.md) | Camadas da aplicacao (Controller → Service → Model) |
| 15 | [`15-api-versionada.md`](architecture/15-15-api-versionada.md) | Versionamento de API (v1, v2) |
| 16 | [`16-extracao-modulo.md`](architecture/16-16-critérios-de-extração-de-módulo.md) | Quando e como extrair um modulo para microservico |
| 17 | [`17-testes-arquitetura.md`](architecture/17-17-testes-de-arquitetura.md) | Testes de conformidade arquitetural |
| 18 | [`18-config-tenant.md`](architecture/18-18-configurabilidade-por-tenant.md) | Feature flags e configuracao por tenant |
| 19 | [`19-cache.md`](architecture/19-19-estratégia-de-cache.md) | Estrategia de cache (Redis, query cache, model cache) |
| - | [`ARQUITETURA.md`](architecture/ARQUITETURA.md) | Visao geral e leis inviolaveis do kernel |
| - | [`STACK-TECNOLOGICA.md`](architecture/STACK-TECNOLOGICA.md) | Bill of Materials — stack exata permitida |
| - | [`INFRAESTRUTURA.md`](architecture/INFRAESTRUTURA.md) | Infraestrutura de deploy, Docker, servidor |
| - | [`CODEBASE.md`](architecture/CODEBASE.md) | Regras de codebase (PSR-12, Pint, nomenclatura) |
| - | [`CURRENT_STATE.md`](architecture/CURRENT_STATE.md) | Estado atual e roadmap |
| - | [`ADR.md`](architecture/ADR.md) | Architecture Decision Records |
| - | [`DASHBOARD-HEALTHCHECK.md`](architecture/DASHBOARD-HEALTHCHECK.md) | Dashboard de monitoramento e health check |

### Compliance Regulatorio (`compliance/` — 3 documentos)

Traduz leis e normas do mundo real em guard rails de codigo. Agentes DEVEM consultar antes de tocar em modulos regulados.

| Documento | Norma | Modulos Afetados |
| --------- | ----- | ---------------- |
| [`ISO-17025.md`](compliance/ISO-17025.md) | ISO/IEC 17025:2017 (Metrologia) | Lab, Inmetro, Quality |
| [`ISO-9001.md`](compliance/ISO-9001.md) | ISO 9001:2015 (Gestao da Qualidade) | Quality, todos os modulos |
| [`PORTARIA-671.md`](compliance/PORTARIA-671.md) | Portaria 671/2021 + CLT (Ponto Digital) | HR, eSocial |

### Design System (`design-system/` — 2 documentos)

Garante consistencia visual sem alucinacao de cores/fontes.

| Documento | O que define |
| --------- | ------------ |
| [`TOKENS.md`](design-system/TOKENS.md) | Paleta de cores (brand navy), tipografia (Inter), espacamento, sombras |
| [`COMPONENTES.md`](design-system/COMPONENTES.md) | Padroes de componentes, acessibilidade, loading/error states |

### Auditorias por Camada (`auditoria/` — 11 documentos)

Verificacao sistematica da qualidade do sistema em 7 camadas + analises complementares.

| Camada | Documento | O que verifica |
| ------ | --------- | -------------- |
| 1 | [`CAMADA-1-FUNDACAO.md`](auditoria/CAMADA-1-FUNDACAO.md) | Banco de dados, autenticacao, multi-tenancy |
| 2 | [`CAMADA-2-API-BACKEND.md`](auditoria/CAMADA-2-API-BACKEND.md) | Rotas, controllers, services, form requests |
| 3 | [`CAMADA-3-FRONTEND.md`](auditoria/CAMADA-3-FRONTEND.md) | React, TypeScript, componentes, hooks |
| 4 | [`CAMADA-4-MODULOS-E2E.md`](auditoria/CAMADA-4-MODULOS-E2E.md) | Fluxos end-to-end por modulo |
| 5 | [`CAMADA-5-INFRA-DEPLOY.md`](auditoria/CAMADA-5-INFRA-DEPLOY.md) | Infraestrutura, Docker, CI/CD |
| 6 | [`CAMADA-6-TESTES-QUALIDADE.md`](auditoria/CAMADA-6-TESTES-QUALIDADE.md) | Cobertura de testes, qualidade de codigo |
| 7 | [`CAMADA-7-PRODUCAO-DEPLOY.md`](auditoria/CAMADA-7-PRODUCAO-DEPLOY.md) | Producao, monitoramento, rollback |
| - | [`AUDIT-CENTRAL.md`](auditoria/AUDIT-CENTRAL.md) | Modulo Central (inbox unificado) |
| - | [`ANALISE-CODIGO.md`](auditoria/ANALISE-CODIGO.md) | Analise estatica e relatorio de codigo |
| - | [`REVISAO-CORRECOES.md`](auditoria/REVISAO-CORRECOES.md) | Revisao de correcoes aplicadas |
| - | [`deep-analysis-plan.md`](auditoria/deep-analysis-plan.md) | Plano de analise profunda do sistema |
| - | [`AUDITORIA-FUNCIONALIDADES-FLUXOS.md`](auditoria/AUDITORIA-FUNCIONALIDADES-FLUXOS.md) | Funcionalidades faltantes e fluxos incompletos (dia a dia) |

### Operacional (`operacional/` — 5 documentos)

Guias praticos para operacao do sistema.

| Documento | O que ensina |
| --------- | ------------ |
| [`deploy-completo.md`](operacional/deploy-completo.md) | Guia completo de deploy (setup ate producao) |
| [`mapa-testes.md`](operacional/mapa-testes.md) | Mapa completo de testes do sistema |
| [`project-rules.md`](operacional/project-rules.md) | Regras do projeto (idioma, convencoes, padroes) |
| [`checklist-calibracao.md`](operacional/checklist-calibracao.md) | Checklist de certificado de calibracao |
| [`troubleshooting-balancas.md`](operacional/troubleshooting-balancas.md) | Troubleshooting do dominio de balancas |

### Fluxos Transversais (`fluxos/` — em construcao)

Documentacao de fluxos ponta a ponta que cruzam multiplos modulos. Estao divididos em 30 operacoes criticas do sistema.

| Documento | Fluxo | Modulos Afetados (Visão Macro) |
| --------- | ----- | ------------------------------ |
| `ADMISSAO-FUNCIONARIO.md` | Fluxo de admissão de novo funcionário/técnico | HR, Recruitment |
| `AVALIACAO-DESEMPENHO.md` | Fluxo de avaliação de desempenho e feedback | HR |
| `CHAMADO-EMERGENCIA.md` | Tratamento de chamados emergenciais (P1/P5) | Service-Calls, WorkOrders |
| `CICLO-COMERCIAL.md` | Lead ate pagamento e comissao | CRM, Quotes, Contracts, WorkOrders, Finance, Fiscal |
| `CICLO-TICKET-SUPORTE.md` | Abertura, triagem e resolução de tickets de suporte | Helpdesk |
| `COBRANCA-RENEGOCIACAO.md` | Fluxo de cobrança de inadimplentes e renegociação | Finance, Email |
| `CONTESTACAO-FATURA.md` | Contestação financeira por parte do cliente | Finance, Portal |
| `COTACAO-FORNECEDORES.md` | Cotação com múltiplos fornecedores e aprovação | Procurement |
| `DESLIGAMENTO-FUNCIONARIO.md` | Processo de offboarding e devoluções | HR, Inventory, Fleet |
| `DESPACHO-ATRIBUICAO.md` | Abertura de chamado ate tecnico em campo | Service-Calls, WorkOrders, Operational, HR |
| `DEVOLUCAO-EQUIPAMENTO.md` | Devolução de equipamentos/ferramentas | Inventory |
| `ESTOQUE-MOVEL.md` | Estoque do veiculo e reposicao | Inventory, Fleet, WorkOrders, Procurement |
| `FALHA-CALIBRACAO.md` | Tratamento de não conformidade no laboratório | Lab, Quality |
| `FATURAMENTO-POS-SERVICO.md` | OS finalizada ate NF-e e pagamento | WorkOrders, Finance, Fiscal, Email |
| `FECHAMENTO-MENSAL.md` | Fechamento fiscal e faturamento de contratos | Contracts, Finance, Fiscal |
| `GARANTIA.md` | Análise e aprovação de serviços em garantia | Service-Calls, Quality |
| `GESTAO-FROTA.md` | Fluxo operacional de frota, sinistros e manutenções | Fleet |
| `INTEGRACOES-EXTERNAS.md` | Fluxos de mensageria com SEFAZ, eSocial, WhatsApp | Integrations, Fiscal, ESocial |
| `MANUTENCAO-PREVENTIVA.md` | Geracao automatica de OS preventiva | Contracts, WorkOrders, Agenda |
| `ONBOARDING-CLIENTE.md` | Processo de ativação de novo cliente ERP | Core, CRM |
| `OPERACAO-DIARIA.md` | Checklist e acompanhamento rotineiro diário | TvDashboard, Operational |
| `PORTAL-CLIENTE.md` | Jornada completa do cliente externo | Portal, Service-Calls, Quotes, Lab, Finance |
| `PWA-OFFLINE-SYNC.md` | Modo offline e sincronizacao | WorkOrders, Operational, HR, Inventory |
| `RECRUTAMENTO-SELECAO.md` | Seleção, entrevistas e pipeline de contratação | Recruitment |
| `RELATORIOS-GERENCIAIS.md` | KPIs por perfil de usuario | Todos |
| `REQUISICAO-COMPRA.md` | Solicitação interna de materiais e aprovação | Procurement |
| `RESCISAO-CONTRATUAL.md` | Rescisão de contratos e distratos | Contracts |
| `SLA-ESCALONAMENTO.md` | SLA automatico com escalonamento | Contracts, Service-Calls, Helpdesk, Email |
| `TECNICO-EM-CAMPO.md` | Jornada completa do tecnico no dia | WorkOrders, Operational, Inventory, Fleet, HR |
| `TECNICO-INDISPONIVEL.md` | Tratamento de indisponibilidade ou falta no campo | HR, Agenda, WorkOrders |

> **Plano de execucao completo:** [`superpowers/plans/2026-03-24-mega-documentacao-completa.md`](superpowers/plans/2026-03-24-mega-documentacao-completa.md)

---

## Fluxos Cross-Domain

Documentacao de integracoes entre modulos:

- [INTEGRACOES-CROSS-MODULE.md](modules/INTEGRACOES-CROSS-MODULE.md) — Mapa central de 6 integracoes entre modulos
  - Finance x Contracts (billing recorrente)
  - Contracts x WorkOrders (medicao)
  - HR x Finance (comissao)
  - Fiscal x Finance (webhook NF-e)
  - Helpdesk x Contracts (SLA)
  - Lab x Quality (certificado)

## Documentacao Operacional Adicional

- [PERFORMANCE-BENCHMARKS.md](operacional/PERFORMANCE-BENCHMARKS.md) — Targets de performance
- [CRITICAL-TEST-PATHS.md](operacional/CRITICAL-TEST-PATHS.md) — Smoke tests e critical paths
- [TROUBLESHOOTING-GERAL.md](operacional/TROUBLESHOOTING-GERAL.md) — Redis, MySQL, frontend, WebSocket, Docker
- [ROLLBACK-PROCEDURES.md](operacional/ROLLBACK-PROCEDURES.md) — Procedimentos de rollback

## Documentacao Arquitetural Adicional

- [ENFORCEMENT-RULES.md](architecture/ENFORCEMENT-RULES.md) — Mecanismos de enforcement para regras criticas
- [SERVICOS-TRANSVERSAIS.md](architecture/SERVICOS-TRANSVERSAIS.md) — 6 servicos cross-cutting documentados

---

## Estrutura do Repositorio

```text
sistema/
  backend/             # API Laravel 12 (PHP 8.4+)
    app/
      Http/
        Controllers/   #   220 controllers (API REST)
        Requests/      #   729 form requests (validacao)
        Middleware/     #   8 middlewares
      Models/          #   368 models (Eloquent + BelongsToTenant)
      Services/        #   121 services (logica de negocio)
      Policies/        #   62 policies (autorizacao Spatie)
      Enums/           #   38 enums (status, tipos)
      Events/          #   35 events
      Listeners/       #   37 listeners
      Observers/       #   13 observers
      Jobs/            #   25 jobs (filas async)
    database/
      migrations/      #   376 migrations
    routes/            #   23 route files (~2379 definicoes)
    tests/             #   609 arquivos de teste
  frontend/            # SPA React 19 + Vite + TypeScript
    src/
      pages/           #   419 paginas/modulos
      components/      #   233 componentes reutilizaveis
      hooks/           #   99 hooks customizados
      types/           #   25 type definitions
      stores/          #   18 stores (Zustand)
    e2e/               #   61 testes E2E (Playwright)
    tests/             #   275 testes Vitest
  deploy/              # Scripts e configs de deploy
  scripts/             # Scripts de auditoria e setup
  prompts/             # Templates AIDD obrigatorios
  docs/                # FONTE DE VERDADE (esta pasta)
    modules/           #   29 Bounded Contexts principais (20.618 linhas)
    fluxos/            #   10 fluxos transversais ponta a ponta (4.565 linhas)
    architecture/      #   27 docs de fundacao arquitetural
    design-system/     #   Design Tokens e padroes de componentes
    compliance/        #   ISO-17025, ISO-9001, Portaria-671
    auditoria/         #   13 auditorias sistematicas
    operacional/       #   5 guias operacionais
    .archive/          #   [PROIBIDO PARA IA] Docs legados
  .agent/              # Agentes IA (Antigravity Kit)
  .cursor/rules/       # Regras de AI para Cursor
```

---

## Numeros do Sistema (Mar/2026 — Contagem Real 24/03)

### Backend (Laravel 12 + PHP 8.4+)

| Camada | Quantidade |
|--------|-----------|
| Controllers | **220** |
| Models | **368** (incluindo Concerns e Lookups) |
| Services | **121** |
| Form Requests | **729** |
| Policies | **62** |
| Enums | **38** |
| Events | **35** |
| Listeners | **37** |
| Observers | **13** |
| Jobs | **25** |
| Middlewares | **8** |
| Migrations | **376** |
| Testes Backend | **609** arquivos |
| Route files | **23** modulares |
| Route definitions | **~2379** |

### Frontend (React 19 + TypeScript + Vite)

| Camada | Quantidade |
|--------|-----------|
| Paginas/Modulos | **419** |
| Componentes | **233** |
| Hooks Customizados | **99** |
| Type Definitions | **25** |
| Stores (Zustand) | **18** |
| Testes Vitest | **275** |
| Testes E2E (Playwright) | **61** |

### Documentacao AIDD

| Area | Quantidade | Linhas |
|------|-----------|--------|
| Bounded Contexts (modules/) | **29** bounded contexts principais + modulos transversais | **20.618** |
| Fluxos Transversais (fluxos/) | **30** fluxos ponta a ponta | **4.565** |
| Docs Arquiteturais (architecture/) | **27** documentos | **4.009** |
| Auditorias (auditoria/) | **13** documentos (7 camadas + complementares) | **1.819** |
| Compliance (compliance/) | **3** normas regulatorias | **739** |
| Operacional (operacional/) | **5** guias | **1.189** |
| Design System (design-system/) | **2** documentos | **151** |
| Total docs ativos | **110** arquivos | **34.207** |

---

## Conteudo Arquivado (Referencia Historica)

> A pasta `.archive/` contem documentacao de fases anteriores do projeto.
> **NAO** deve ser usada como fonte de verdade. Existe para rastreabilidade historica.

| Pasta | Conteudo | Qtd |
| ----- | -------- | --- |
| `.archive/prd/` | PRDs originais (backend, frontend, discovery, modulos detalhados, plano de implementacao) | 6 |
| `.archive/alinhamento/` | Documentos de alinhamento com stakeholders (empresa, tecnico, financeiro, decisoes) | 8 |
| `.archive/features/` | Especificacoes antigas (cameras RTSP, CRM, inventario de 1079+ endpoints) | 3 |
| `.archive/mvp/` | Status e resultados do MVP | 2 |
| `.archive/plans/` | Planos de execucao (OS, pendencias) | 2 |
| `.archive/old-plans/` | Planos mega anteriores (comissao, RH, testes, auditoria) | 6 |
| `.archive/superpowers/` | Analises, planos, reviews e specs gerados por IA | 13 |
| `.archive/testes/` | Logs de execucao e dados de teste | 2 |
| `.archive/business/` | Analise de concorrencia | 1 |
