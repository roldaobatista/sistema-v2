# Roadmap de Expansão Arquitetural: 7 Pilares & O Princípio do "Lead Eterno"

Este documento delineia o planejamento completo de todos os arquivos de documentação (arquitetura, módulos e fluxos) que precisam ser **criados** ou **alterados** para acomodar os 7 novos VAZIOS FUNCIONAIS enterprise identificados, além de injetar a regra de ouro: **"Todo cliente é um Lead Eterno"**.

## 1. O Princípio do "Lead Eterno"

**Regra de Negócio Crítica (`[AI_RULE_CRITICAL]`):**
> *Todo cliente é um Lead Eterno. A esteira de vendas não acaba no "Won" (Fechamento). O cliente entra imediatamente em réguas de retenção, cross-sell, up-sell, expansão de licença/contrato e renovação automática. No banco de dados, a Flag de "Lead" nunca é descartada cognitivamente pelo CRM; o ciclo de vida apenas muda de fase, reentrando no funil perpetuamente.*

### Documentos a Serem Alterados para Embutir o Lead Eterno

- **`docs/modules/CRM.md`**
  - **Ação:** Injetar a `[AI_RULE_CRITICAL]` do Lead Eterno nas regras de Funil.
  - **Ação:** Alterar o mapeamento de Status de `Deal` para prever reentrada de clientes atuais (Ex: Deal Type: "Expansion", "Renewal", "Cross-sell").
  - **Ação:** Atualizar `CrmSequence` para engatilhar campanhas em leads já convertidos.

- **`docs/modules/Contracts.md`**
  - **Ação:** Adicionar gatilho: 90 dias antes do fim do contrato, um "Lead Card" deve ser reaberto ou movido de volta para o Kanban do Vendedor no `CRM.md`.

- **`docs/modules/Innovation.md` (Referral & Gamification)**
  - **Ação:** Clientes base ("Lead Eternos") recebem automações ativas de indicação (Programa de Afiliados/Indicação). O lead eterno atua como canal de aquisição.

- **`docs/fluxos/CICLO-COMERCIAL.md`**
  - **Ação:** Refazer o fluxograma visual (Mermaid) indicando o Loop Contínuo. Após o nó `[Assinar Contrato]`, o fluxo deve retornar a um nó `[Fase de Expansão/Renovação (Lead Eterno)]`.

---

## 2. Novos Módulos Arquiteturais (A Criar)

Para atender as demandas de Data Lake, Projetos, Logística, Omnichannel, Ativo Fixo, IoT e Portal B2B, a arquitetura deve ser enriquecida com **7 novos documentos Mestres em `docs/modules/`**.

> **NOTA:** Os 7 documentos de módulos listados abaixo **já existem** em `docs/modules/` (Projects.md, Omnichannel.md, Logistics.md, SupplierPortal.md, IoT_Telemetry.md, FixedAssets.md, Analytics_BI.md). **NÃO recriar esses arquivos.** Usar os existentes como fonte canônica e expandir conforme necessário.

### 2.1 `docs/modules/Projects.md` (PPM - Project Portfolio Management)

- **O que documentar:** Gestão de Cronogramas, Gráficos de Gantt, Alocação de Técnicos em longo prazo, Milestone Billing.
- **Integração:** WorkOrders (tarefas pai/filha submetidas ao projeto) e Finance (faturamento por etapa validada).

### 2.2 `docs/modules/Omnichannel.md` (Inbox Centralizada)

- **O que documentar:** Arquitetura para a "Inbox Universal". Websockets para chat em tempo real unificando Webhook do WhatsApp (Integrations), Respostas de E-mail (Email) e Mensagens do Portal do Cliente.
- **Roteamento:** Habilidade de abrir O.S. ou Deal diretamente da tela do chat.

### 2.3 `docs/modules/Logistics.md` (WMS/TMS Leve & Logística Reversa)

- **O que documentar:** Etiquetas ZPL/Zebra para Inbound/Outbound. Geração de código de rastreamento de transportadoras ou Correios para instrumentos no processo de Calibração (RMA shipment).
- **Integração:** `Quality.md` (RMA) e `Inventory.md` (WMS Básico).

### 2.4 `docs/modules/SupplierPortal.md` (Portal B2B do Fornecedor)

- **O que documentar:** Acesso self-service (Painel Fornecedor) para responder às `PurchaseQuote` (Cotação Multi-fornecedor). Upload direto de CTE/XML pelo transportador ou fornecedor.
- **Automação:** Magic Links que expiram para coletar orçamentos sem exigir senha ou login complexo do fornecedor.

### 2.5 `docs/modules/IoT_Telemetry.md` (Captura Serial Contínua)

- **O que documentar:** Client Desktop ou Agent (Node/Python) para leitura de portas COM (RS-232, TCP/IP) em balanças e equipamentos do laboratório, transmitindo via API para preenchimento de `CalibrationReading`. Elimina o erro humano de digitação durante o compliance ISO-17025.

### 2.6 `docs/modules/FixedAssets.md` (Ativo Imobilizado e CIAP)

- **O que documentar:** Depreciação contábil mensal de Frota (`FleetVehicle`) e Equipamentos do Laboratório (`ToolCalibration`).
- **Integração:** Módulo Fiscal (SPED Bloco G, CIAP) e Finanças. Define a lógica de "Baixa Patrimonial" em caso de quebra de equipamento.

### 2.7 `docs/modules/Analytics_BI.md` (Data Lake & Custom Reports)

- **O que documentar:** Arquitetura do ETL (Extração) para jogar dados pesados do tenant em ambientes analíticos como Power BI ou Metabase. Geração de relatórios cruzados em tempo real (Drag-and-Drop) de KPIs híbridos (RH + Finanças + Operacional).

---

## 3. Revisão de Diagramas e Cross-Modules (Alterações Finais)

- **`docs/modules/INTEGRACOES-CROSS-MODULE.md`**
  - **Ação:** Criar seção de como o `IoT_Telemetry` "fala" com `Lab`;
  - **Ação:** Mapear interação entre `Logistics` (Transportadora) e `Fiscal` (Notas de Remessa/Retorno de Conserto).

- **`docs/auditoria/CAMADA-4-MODULOS-E2E.md`**
  - **Ação:** Inserir os 7 novos módulos no radar de prioridade e de testes ponta a ponta E2E.

- **`docs/BLUEPRINT-AIDD.md`**
  - **Ação:** Adicionar as "Fases 5 e 6 - Enterprise Expansion e Machine-to-Machine" cobrindo esses 7 módulos.

---
**Status Final do Planejamento:** Roadmap montado. O processo requer injeção da regra "Lead Eterno" em 4 documentos existentes, criação de 7 novos módulos nativos documentados na pasta `modules/`, e atualização dos diagramas de fluxo cruzado para abranger o ecossistema escalado.
