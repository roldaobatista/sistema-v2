# Rodada A11y e Performance — Telas Principais Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Melhorar acessibilidade real e performance percebida nas telas principais de autenticação, dashboard, CRM e analytics com mudanças pequenas, verificáveis e de baixo risco.

**Architecture:** A rodada trabalha sobre páginas já existentes, priorizando correções semânticas de UI e carregamento tardio de blocos pesados onde houver ganho concreto. O foco é reduzir issues reais do checker de acessibilidade e melhorar o custo inicial das rotas mais usadas sem abrir refatoração ampla do frontend.

**Tech Stack:** React 19, TypeScript, Vite, Vitest, Tailwind CSS, scripts locais de auditoria de acessibilidade/UX.

---

### Task 1: Mapear e testar as telas de autenticação

**Files:**
- Modify: `frontend/src/pages/auth/LoginPage.tsx`
- Modify: `frontend/src/pages/auth/ForgotPasswordPage.tsx`
- Modify: `frontend/src/pages/auth/ResetPasswordPage.tsx`
- Test: `frontend/src/pages/auth/__tests__/*`

- [ ] Rodar o checker de acessibilidade e confirmar os problemas atuais nas telas de autenticação
- [ ] Revisar os componentes e localizar inputs sem `label`/`aria-label` e interações sem suporte de teclado
- [ ] Ajustar a semântica mínima necessária nas três telas
- [ ] Rodar os testes focados existentes ou criar cobertura mínima se faltar
- [ ] Confirmar redução dos problemas reais nessa área

### Task 2: Corrigir dashboard principal e boundary operacional

**Files:**
- Modify: `frontend/src/pages/dashboard/DashboardPage.tsx`
- Modify: `frontend/src/components/ErrorBoundary.tsx`
- Test: `frontend/src/pages/dashboard/__tests__/*`

- [ ] Validar os problemas de acessibilidade reais do dashboard e do error boundary
- [ ] Corrigir elementos clicáveis inadequados, handlers de teclado e headings principais
- [ ] Garantir que botões e ações críticas tenham texto acessível
- [ ] Rodar testes focados das telas alteradas
- [ ] Confirmar que o build continua verde

### Task 3: Corrigir CRM principal

**Files:**
- Modify: `frontend/src/pages/crm/CrmDashboardPage.tsx`
- Modify: `frontend/src/pages/crm/CrmPipelinePage.tsx`
- Test: `frontend/src/pages/crm/__tests__/*`

- [ ] Validar os problemas reais apontados no CRM dashboard e pipeline
- [ ] Corrigir campos sem identificação acessível e interações semânticas inadequadas
- [ ] Revisar headings e navegação das áreas principais da página
- [ ] Rodar testes focados do CRM afetado
- [ ] Reexecutar o checker para confirmar melhora nesse grupo

### Task 4: Refinar Analytics para acessibilidade e peso inicial

**Files:**
- Modify: `frontend/src/pages/analytics/AnalyticsHubPage.tsx`
- Modify: `frontend/src/pages/analytics/AnalyticsOverview.tsx`
- Modify: `frontend/src/pages/analytics/PredictiveAnalytics.tsx`
- Modify: `frontend/src/components/charts/DonutChart.tsx`
- Test: `frontend/src/pages/analytics/__tests__/AnalyticsHubPage.test.tsx`

- [ ] Revisar os pontos restantes de acessibilidade e os componentes de analytics mais visíveis
- [ ] Corrigir labels, headings e interações clicáveis nessa área
- [ ] Reduzir custo inicial adicional com lazy loading ou separação de imports quando houver ganho real
- [ ] Remover escolhas visuais problemáticas de alto impacto documentadas no relatório, como o roxo em charts do escopo
- [ ] Rodar teste focado do hub de analytics e validar o build

### Task 5: Verificação final da rodada

**Files:**
- Reference: `docs/operacional/RELATORIO-FASE-24-POLISH-FINAL.md`

- [ ] Rodar `python .agent/skills/frontend-design/scripts/accessibility_checker.py frontend/src`
- [ ] Rodar `cd frontend && npm run build`
- [ ] Rodar os testes focados das áreas alteradas
- [ ] Revisar `git diff --stat` para confirmar diff controlado
- [ ] Registrar o resultado final com o que melhorou e o que ainda sobra fora do escopo
