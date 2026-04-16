---
status: superseded
type: implementation
created: 2026-03-24
description: Plano de execução AIDD em ondas — Onda 0 concluída, Ondas 1-3 pendentes
---

> [!WARNING]
> **ESTE PLANO ESTÁ OBSOLETO (SUPERSEDED).** Uma auditoria do sistema realizada em 25/03/2026 revelou que várias instruções listadas abaixo (como construir `JourneyCalculationService` e CRUDs de Quality/Lab) instruiriam erroneamente os agentes autônomos a recriar funcionalidades que já existem no repositório. O risco técnico e de alucinação de IA é ALTO.
> **INSTRUÇÃO PARADA:** Abandone este arquivo e use EXCLUSIVAMENTE o documento `2026-03-25-plano-implementacao-completo.md` na mesma pasta para guiar a execução das Fases.

> ✅ **ONDA 0 VALIDADA em 2026-03-25.** Todas as interfaces e contratos estão formatados. IAs podem prosseguir para ONDAs 1-3.

# Mega-Plano: Execução Autônoma AIDD (AI-Driven Development)

## 🎯 Objetivo: Resolver Funcionalidades Faltantes e Fluxos Incompletos

**Data:** 24/03/2026
**Estratégia:** Dividir o escopo em **Ondas (Waves)** sequenciais e independentes, permitindo que Agentes Autônomos especializados (Backend, Frontend, Compliance, Matemática) executem o desenvolvimento de ponta a ponta sem alucinações.
**Metodologia:** Cada tarefa deve seguir estritamente o `AGENTS.md`, com cobertura de `testes` e `100% features` ("se tocou, COMPLETA", zero mock, 100% integração ponta a ponta).

---

## 🌊 ONDA 0: Hardening de Documentação e Contratos Técnicos

Antes dos agentes escreverem código "heavy", precisamos garantir que a infraestrutura documental detalhada exista.

| # | Tarefa (Agent Prompt) | Agente(s) | Ação a Executar |
|---|----------------------|-----------|------------------|
| **0.1** | **Contracts do PWA (WorkOrders)** | `orchestrator` | Redigir os JSON Contracts exatos e Diagramas de Estado (Mermaid) para o fluxo do PWA do Técnico (13 rotas de execução, fotos, assinatura). Atualizar `docs/modules/WorkOrders.md`. |
| **0.2** | **Contratos de Matemática e Lab** | `backend-specialist` | Documentar as equações matemáticas (EMA por OIML, Incerteza GUM, RepeatabilityTest) e formatar DTOs para o Laboratório. Atualizar `docs/modules/Lab.md`. |
| **0.3** | **Contratos de Folha (CLT 671)** | `backend-specialist` | Desenhar arquitetura de banco de horas determinístico (`JourneyCalculationService`) e hash-chain para o AFD no `docs/compliance/PORTARIA-671.md`. |
| **0.4** | **Dashboards & Kanban** | `frontend-specialist` | Definir tokens visuais, contratos de API e payload de websockets/hook (usePipeline) para CRM, Inventory e Quality no `docs/modules/CRM.md`. |

**Validação (Definition of Done ONDA 0):** As IAs só podem codificar após todas as interfaces e algoritmos complexos de ONDA 0 estarem formatados em Markdown nas suas respecitvas documentações.

---

## 🌊 ONDA 1: O Núcleo Financeiro e Operacional (Backend Core)

Aqui o `backend-specialist` é ativado para implementar o coração do sistema transacional. Todo `controller` exige seu próprio `FormRequest`, e toda model precisa de `testes de feature`.

| # | Tarefa (Agent Prompt) | Agente(s) | Ação a Executar |
|---|----------------------|-----------|------------------|
| **1.1** | **WorkOrder Execution Back-End** | `backend-specialist` | Criar `WorkOrderExecutionController` com seus 13 endpoints (Iniciar, Pausar, Tirar Foto, Assinar). Integrar `WorkOrderInvoicingService` (TOCTOU locks) e disparar Job `WorkOrderCompleted`. Criar os testes correspondentes nas suítes locais. |
| **1.2** | **Service-Calls Automation** | `backend-specialist` | Desenvolver lógica TDD em `ServiceCallService` para auto-assignment baseado em SLA timeouts e carga dos técnicos. Completar transições de estado. |
| **1.3** | **Inventory Event Listeners** | `backend-specialist` | OUVIR evento `WorkOrderCompleted` -> acionar transação DB com `StockService.deduct()`. Criar Job cronificado `CheckMinRepoPoint` que gere automações de `PurchaseQuotation`. |

**Validação (DoD ONDA 1):** `php artisan test` 100% passando. Comprovar dedução real de estoque quando Workflow é finalizado via banco isolado (Tenant).

---

## 🌊 ONDA 2: Frontend Core & PWA (SPA Vite)

O `frontend-specialist` assume o topo da stack consumindo as APIs desenvolvidas na ONDA 1.

| # | Tarefa (Agent Prompt) | Agente(s) | Ação a Executar |
|---|----------------------|-----------|------------------|
| **2.1** | **PWA Técnico React** | `mobile-developer` / `frontend-specialist`| Construir fluxo de mapa (GPS Geocoding), transições offline-sync (ServiceWorkers), botão flotante modal de assinatura digital via HTML Canvas. |
| **2.2** | **Kanban Comercial (CRM e Quotes)** | `frontend-specialist` | Criar placa drag-and-drop com Hook `useQuotes` e `usePipeline`. Exibir `Magic token` links para o cliente final renderizar o orçamento PDF no navegador. |
| **2.3** | **Tabelas de Pricing e Visão 360** | `frontend-specialist` | Componentes visuais para catálogo de produtos (WorkOrders), Linha do tempo de Histórico de preço de Clientes e inventário cego (Inventory). |

**Validação (DoD ONDA 2):** `cd frontend && npm run build` sem avisos (Zero TypeScript Anys, Labels Aria completos). UI interativa testada contra a API real.

---

## 🌊 ONDA 3: Algoritmos Avançados, Compliance e Regras Especializadas

Camadas fechadas do aplicativo onde precisão e conformidade são a meta máxima. IAs operando estritamente sobre as regras ISO e leis governamentais.

| # | Tarefa (Agent Prompt) | Agente(s) | Ação a Executar |
|---|----------------------|-----------|------------------|
| **3.1** | **Motor de Cálculos Lab e Pricing** | `backend-specialist` | Implementar `RepeatabilityTest`, erro de indicação com bcmath (`bcadd / bcdiv / bcmul`) nas classes de calibração. Motor `LaborCalculationService`. Todos com Unit tests de matemática profunda para evitar ponto flutuante. |
| **3.2** | **Portaria 671 / HR / eSocial** | `security-auditor` / `backend-specialist` | Codificar motor `JourneyCalculationService` em TDD rigoroso. Logs de ajuste de ponto apendados inalteráveis (`audit_logs`) com Hash SHA-256 no Serviço de AFD Export. |
| **3.3** | **Workflow Lab e Inmetro Seal** | `backend-specialist` | Desenvolver `EquipmentCalibration` Wizard e log-book ambiental `LabLogbookEntry`. Rastreabilidade de `InmetroSeal` completa com endpoint público de verificação (QR Code PDF Service). |
| **3.4** | **Módulo SGQ (ISO 17025 e 9001)** | `backend-specialist` | Criar CRUDS para RNC (`QualityAudit`, `CapaRecord`) acoplados com flags de ambiente exclusivas `strict_iso_17025`, impedindo bypasses de Single Sign-off. Jobs preventivos de 30 dias de expiração criados e configurados. |

**Validação (DoD ONDA 3):** Testes exaustivos nas funções bcmath (Zero Floats). O Algoritmo de tempo da Portaria 671 deve comprovar DSR (Descanso Semanal Remunerado) de feriados em bateria simulada.

---

## 🚀 Dinâmica de Desenvolvimento Agente-para-Agente

Para este sucesso no paradigma 100% IA (AIDD):

1. **Ativação Simples:** O Usuário ou o Orchestrator copia o texto de uma "Tarefa" (Ex: _"1.1 WorkOrder Execution Back-End"_) e emite um único comando claro no chat.
2. **Setup Iron Protocol:** O agente que responde vai carregar automaticamente seu `AGENTS.md` local, verificando os testes que tem na frente de si.
3. **Ponto Parada de Conclusão:** A IA codifica, gera o teste de API, gera a interface da Web. Só entrega pro humano (Manda "Status: Concluído e Gate Aprovado") quando `php artisan test` der verde e `npm build` fechar inteiro sem Erros TypeScript.

> Todo comando acionado por você no cursor deve começar com foco profundo num único item desta planilha de ondas, do início ao fim, até limparmos integralmente as pendências mapeadas no relatório de Auditoria.
