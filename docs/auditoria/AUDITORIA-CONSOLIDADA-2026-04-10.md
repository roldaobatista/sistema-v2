# Auditoria Consolidada — Kalibrium ERP

**Data:** 2026-04-10
**Fontes:** ~~`docs/raio-x-sistema.md`~~ (removido 2026-04-10) v2.4, `docs/audits/RELATORIO-AUDITORIA-SISTEMA.md` (Deep Audit 2026-04-10), `docs/plans/seguranca-remediacao-auditoria.md` v3
**Escopo:** Funcionalidades, módulos e fluxos — estado real validado contra código-fonte.

---

## 1. Maturidade Global por Domínio (PRD-KALIBRIUM v3.2)

| # | Domínio | Status | % | Veredito |
|---|---|---|---|---|
| 1 | Financeiro | 🟡 Parcial | 70 | Fluxos AP/AR reais; conciliação básica; integrações dependem de credencial |
| 2 | Operacional (OS/Service Calls/Agenda) | 🟢 Completo | 85 | Ciclo completo, 17 estados, PDF, GPS, assinaturas reais |
| 3 | Comercial/CRM | 🟡 Parcial | 65 | Scoring real; **Deal→Quote inexistente** |
| 4 | RH & Compliance | 🟢 Completo | 90 | Folha, ponto, hash chain genuínos |
| 5 | Estoque & Produtos | 🟡 Parcial | 85 | Movimentação real; **FIFO/FEFO não implementado** |
| 6 | Qualidade & Calibração | 🟢 Completo | 95 | EMA, certificados, SPC reais; Regras de Decisão ISO §7.8.6 ✅ |
| 7 | Analytics & BI | 🟡 Parcial | 85 | BI ok; camada AI parcial |
| 8 | Infraestrutura | 🟡 Parcial | 85 | Multi-tenant sólido; **PWA offline incompleto** |

---

## 2. Bloqueadores Críticos (Impedem Go-Live / Ciclo Completo)

### 🔴 CRIT-1 — Webhook Asaas desconectado da máquina de estados (Financeiro)
- **Status:** Código `AsaasPaymentProvider` (boleto/PIX/status/QR) já escrito, mas a **orquestração do webhook de recebimento (RF-19.5 a RF-19.7)** não baixa parcela automaticamente para `PAID`.
- **Impacto:** Ciclo `OS_COMPLETED → INVOICED → NFSE_ISSUED → PAYMENT_GENERATED → PAID` quebra no último passo. Invalida promessa de "recebimento sem toque humano".
- **Ação:** Finalizar listener do webhook → transição FSM → baixa em `accounts_receivable` → testes de integração cobrindo success/fail/idempotência.

### 🔴 CRIT-2 — CNAB AR/AP ausente
- CNAB só existe para payroll. Cobrança bancária e pagamento em lote de fornecedores ficam de fora.
- **Ação:** estender geração CNAB 240/400 para AR e AP, reusando contrato do payroll.

### 🔴 CRIT-3 — NFS-e sem contrato externo ativado
- Providers `FocusNFeProvider`, `NuvemFiscalProvider`, `ResilientFiscalProvider` prontos; falta contrato comercial + config por tenant.
- **Ação operacional** (não código): onboarding de gateway e secrets por tenant.

### 🔴 CRIT-4 — Remediação de segurança (P0.1 re-aberto)
- Ver `docs/plans/seguranca-remediacao-auditoria.md` v3 — pós-verificação em 2026-04-10, P0.1 foi reclassificado e continua com itens abertos: CSP `unsafe-inline`/`unsafe-eval`, tokens em `localStorage` vs HttpOnly, FormRequests com `authorize()=true`, lookup de tenant em endpoints públicos, baseline de permissões.

---

## 3. Lacunas Importantes (Funcionalidade / Metodologia)

> ⚠️ **Correção pós-verificação (2026-04-10):** a primeira versão desta seção copiou gaps do Raio-X/PRD sem confrontar o código. Verificação direta encontrou 4 falsos negativos. Esta versão traz o estado real.

### IMP-1 — Deal → Quote  ✅ **IMPLEMENTADO (PRD/Raio-X estão desatualizados)**
- Action: `backend/app/Actions/Crm/ConvertDealToQuoteAction.php` (transação, validações de tenant/cliente/status, cria `Quote` + `QuoteEquipment` + `QuoteItem` a partir dos produtos do Deal)
- Rota: `POST deals/{deal}/convert-to-quote` em `backend/routes/api/crm.php:53` (com permissão dupla `crm.deal.update|quotes.quote.create`)
- Controller: `CrmController@dealsConvertToQuote`
- Frontend: `frontend/src/components/crm/DealDetailDrawer.tsx:137` — mutation + botão operante
- API client: `frontend/src/lib/crm-api.ts:207` (`convertDealToQuote`)
- Teste: `backend/tests/Feature/CrmDealConvertToQuoteTest.php`
- **Ação real:** remover do PRD-CHANGELOG (v2.1/v3.1 ainda listam como "desconectado"), atualizar Raio-X do domínio 3.

### IMP-2 — FIFO/FEFO  🟡 **IMPLEMENTADO, mas não configurável por tenant/produto**
- `backend/app/Services/StockService.php:253` — `selectBatches(strategy)` implementa FIFO (por `created_at`) e FEFO (por `expires_at`, fallback FIFO para lotes sem validade)
- `reserve()`, `deduct()`, `returnStock()` aceitam `$strategy = 'FIFO'` (default)
- **Consumidores confirmados:** `WorkOrderActionController`, `StockAdvancedController`, `HandleWorkOrderCancellation`, `HandleWorkOrderInvoicing`, `InventoryController`, `KardexController`, `StockController`, `UsedStockItemController`
- Testes: `StockServiceTest.php`, `StockServiceProfessionalTest.php`, `StockMovementServiceTest.php`
- **Gap real:** todos os callers passam `strategy='FIFO'` default. Não há coluna `products.stock_strategy` ou config por tenant para escolher FEFO. Para ativar FEFO é preciso tocar código.
- **Ação:** adicionar `stock_strategy` em `products` (ou `product_categories` / `tenant_settings`) + fazer callers lerem do model.

### IMP-3 — PWA offline / Conflict Resolution  ✅ **IMPLEMENTADO E TESTADO**
- `frontend/src/lib/offline/ConflictResolver.ts` — 3 estratégias: `local_wins` (LWW, default), `server_wins`, `manual`
- `autoResolveConflicts()` default = last-write-wins
- Teste: `frontend/src/__tests__/hooks/useSyncEngine.test.ts` compara `local.updated_at >= remote.updated_at` (LWW genuíno)
- Outros testes offline: `useOfflineCache.test.ts`, `useOfflineDb.test.ts`, `useOfflineStore.test.tsx`, `useCrossTabSync.test.ts`, `fixed-assets-offline.test.ts`
- **Ressalva legítima do Deep Audit:** teste em campo com concorrência real (2+ sessões + rede intermitente) não está automatizado. Isso é teste E2E/chaos, não unit.
- **Ação:** criar cenário Playwright com 2 contextos offline + rede intermitente (escopo opcional, não bloqueia nada).

### IMP-4 — Camada AI  🟡 **SCAFFOLDING EXISTE, LLM NÃO PLUGADO**
- `backend/app/Services/AiAssistantService.php` — tem tool-calling estruturado com 5 tools: `predictive_maintenance`, `expense_analysis`, `triage_suggestions`, `sentiment_analysis`, `dynamic_pricing`
- `backend/app/Services/AIAnalyticsService.php` — serviço de analytics
- `backend/app/Http/Requests/Ai/AiChatRequest.php` — request para chat
- `backend/app/Http/Requests/Analytics/AnalyticsForecastRequest.php` — forecast
- **Busca por `anthropic|claude` no backend retorna 0 hits** (fora de security-audit).
- **Status real:** estrutura pronta para receber LLM, mas nenhum provider Anthropic/OpenAI está instanciado.
- **Ação:** conectar ao plano `docs/plans/agente-ceo-ia.md` — adicionar provider Claude + secret management por tenant.

### IMP-5 — PRD desatualizado  ✅ **CONFIRMADO (Deep Audit 10/04 tinha razão)**
- PRD v3.1 last update 2026-04-06 ainda marca Deal→Quote como "desconectado" e FIFO/FEFO como "pendente" — ambos falsos (ver IMP-1/IMP-2).
- `TECHNICAL-DECISIONS.md` afirma `offlineDb.ts` deprecated, mas **16 arquivos ainda importam** dele incluindo o próprio `syncEngine.ts:17`.
- PRD-KALIBRIUM v3.2 já está mais próximo, mas herdou status do PRD em alguns pontos.
- **Ação:** atualizar PRD-CHANGELOG com status verificado + corrigir TECHNICAL-DECISIONS (ou concluir migração real de `offlineDb.ts`).

### IMP-6 — Conciliação bancária básica  🟡 **A VERIFICAR no código**
- Raio-X diz "básica". Não validei neste round. Marcar como `a-verificar` antes de escrever plano.

---

## 4. Melhorias (Refatoração / Dívida Técnica)

- **DashboardPage.tsx** operacional mas sem testes E2E cobrindo os múltiplos widgets (`dashboard-stats`, OS recentes, NPS, RH).
- **`offlineDb.ts` deprecated** (commit 49bb38fd) — confirmar remoção total e migrar call-sites restantes para `offline/indexedDB.ts`.
- **Documentação ativa vs arquivo** — `docs/.archive/` ainda grande; manter isolamento absoluto (CLAUDE.md já reforça).
- Testes de regressão para fluxo OS → Faturamento → NFS-e → Baixa (ponta-a-ponta), hoje cobertos em unidades isoladas.

---

## 5. O que deveria ser alterado

1. **Parar de tratar NFS-e/Boleto/PIX como "bloqueadores de código"** — são integrações dependentes de contrato/credencial (PRD-KALIBRIUM v3.2 já reclassificou). Foco real: **webhook orchestration**.
2. **Fechar ciclo financeiro** antes de qualquer feature nova no domínio (CRIT-1 + CRIT-2).
3. **Travar avanço do CRM** até ter Deal→Quote, senão pipeline comercial fica ornamental.
4. **Política de estoque** deve ser decidida por tenant (FIFO/FEFO/Custo médio) — hoje é implícito.
5. **PWA offline** precisa de teste adversarial (duas sessões, rede intermitente) antes de ser vendido como feature estável.
6. **Remediação de segurança P0.1** deve virar gate de deploy, não plano paralelo.

---

## 6. Priorização Sugerida

| Ordem | Item | Esforço | Desbloqueia |
|---|---|---|---|
| 1 | CRIT-1 Webhook Asaas → FSM → baixa | M | Ciclo OS→Recebimento completo |
| 2 | CRIT-4 Segurança P0.1 (lista do plano v3) | M | Go-live seguro |
| 3 | CRIT-2 CNAB AR/AP | M | Cobrança bancária em escala |
| 4 | IMP-1 Deal→Quote | S | Pipeline comercial real |
| 5 | IMP-2 FIFO/FEFO | M | Custo de estoque correto |
| 6 | IMP-3 PWA offline — teste de conflito | S | Confiança mobile/campo |
| 7 | CRIT-3 NFS-e (operacional) | — | Faturamento eletrônico |

---

## 7. Referências Cruzadas

- ~~`docs/raio-x-sistema.md`~~ (removido 2026-04-10) — inventário validado (fonte de verdade)
- `docs/audits/RELATORIO-AUDITORIA-SISTEMA.md` — Deep Audit 10/04 (OS, Calibração, Financeiro)
- `docs/plans/seguranca-remediacao-auditoria.md` — plano P0/P1 segurança v3
- `docs/plans/iso17025-regras-decisao.md` — já implementado (commit 7b951f11)
- `docs/plans/calibracao-normativa-completa.md` — 12 etapas, calibração normativa
- `docs/plans/motor-jornada-operacional.md` — CLT/Portaria 671/eSocial/LGPD

**Conclusão:** o sistema está **maduro em núcleo operacional e calibração** (os dois pilares do Kalibrium), mas o **ciclo financeiro ponta-a-ponta** está travado num único ponto (webhook Asaas) que bloqueia a promessa de automação. Resolver CRIT-1 + CRIT-4 (segurança) é pré-requisito de go-live — o resto é roadmap.
