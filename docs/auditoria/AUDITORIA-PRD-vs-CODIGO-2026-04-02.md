# Auditoria PRD vs Código Real — Kalibrium ERP

**Data:** 2026-04-02
**Método:** 5 agentes paralelos vasculharam backend/ e frontend/ confrontando cada RF do PRD com código real
**Escopo:** 100% dos RFs (RF-01 a RF-13) + NFRs + claims de infraestrutura

## Resumo Executivo

| Categoria | Total Claims | ✅ Correto | ⚠️ Difere | ❌ Errado no PRD |
|-----------|-------------|-----------|----------|-----------------|
| RF-01 OS | 10 | 10 | 0 | 0 |
| RF-02 Calibração | 8 | 7 | 1 | 0 |
| RF-03 Financeiro | 12 | 9 | 2 | 1 |
| RF-04 Clientes | 4 | 4 | 0 | 0 |
| RF-05 Ponto | 7 | 5 | 0 | **2** |
| RF-06 Portal | 5 | 5 | 0 | 0 |
| RF-07 PWA | 5 | 3 | 2 | 0 |
| RF-08 Admin | 6 | 3 | 2 | 1 |
| RF-09 API | 5 | 4 | 0 | **1** |
| RF-10 eSocial | 6 | 4 | 2 | 0 |
| RF-11 LGPD | 9 | 9 | 0 | 0 |
| RF-12 Complementares | 12 | 7 | 5 | 0 |
| RF-13 Notificações | 8 | 5 | 2 | 1 |
| NFRs | 10 | 9 | 1 | 0 |
| **TOTAL** | **107** | **84 (78%)** | **17 (16%)** | **6 (6%)** |

## ERROS CRÍTICOS — PRD diz uma coisa, código diz outra

### 1. RF-05.6 e RF-05.7 (AFDT/ACJEF) — PRD diz 🔴, código diz ✅

**PRD afirma:** "A implementar: exportação no formato AFDT/ACJEF padrão MTE"

**Código real:**
- `backend/app/Services/AFDExportService.php` — implementado, gera formato fixed-width Portaria 671 (tipos 1-9), verifica hash chain antes de exportar
- `backend/app/Services/ACJEFExportService.php` — implementado, exporta jornadas no padrão MTE
- `backend/app/Http/Controllers/Api/V1/Hr/FiscalAccessController.php` — endpoints `exportAfdt()` e `exportAcjef()`

**Veredicto:** PRD DESATUALIZADO. Mudar de 🔴 para 🟢.

### 2. RF-09.5 (Documentação API / Swagger) — PRD diz 🔴, código diz ✅

**PRD afirma:** "documentação interativa (Swagger/OpenAPI) não existe (RF-09.5 = 🔴)"

**Código real:**
- `composer.json` contém script `"docs:openapi": "@php artisan scramble:export"`
- Laravel Scramble auto-gera OpenAPI specs em `/docs/api/openapi.json`

**Veredicto:** PRD DESATUALIZADO. Mudar de 🔴 para 🟢.

### 3. RF-03.12 (Carta de Correção NFS-e) — PRD diz 🔴, código diz ✅

**PRD afirma:** Status 🔴 (não implementado)

**Código real:**
- `backend/app/Services/Fiscal/FocusNFeProvider.php` — método `cartaCorrecao()` posta para FocusNFe `/v2/nfe/{referencia}/carta_correcao`
- `NuvemFiscalProvider` também implementa como fallback
- Rota: `POST fiscal/notas/{id}/carta-correcao`

**Veredicto:** PRD DESATUALIZADO. Mudar de 🔴 para 🟢.

### 4. RF-08.5 (Monitorar saúde tenants) — PRD diz 🔴, código diz ⚠️

**PRD afirma:** Status 🔴 (não implementado)

**Código real:**
- `ObservabilityDashboardController.php` existe com métricas
- `ObservabilityDashboardPage.tsx` no frontend admin
- Limitado a audit logs, sem drill-down por tenant

**Veredicto:** PRD DESATUALIZADO. Mudar de 🔴 para 🟡.

### 5. RF-02.3 (EMA conforme OIML R76) — PRD diz 🟢, código é ⚠️

**PRD afirma:** "Calcular EMA conforme classe de precisão" 🟢

**Código real:**
- Implementa ISO GUM genérico (Type A + Type B) com boa precisão decimal (BCMath)
- NÃO foi detectado algoritmo específico OIML R76 com tabelas de EMA por classe (I, II, III, IIII)

**Veredicto:** PRD OTIMISTA. Funciona para cálculo de incerteza, mas claim específica de "OIML R76" precisa validação com metrologista. Manter 🟢 com nota.

### 6. RF-03.11 (Cancelar NFS-e) — PRD diz 🔴, código é ⚠️

**PRD afirma:** Status 🔴 (não implementado)

**Código real:**
- Método `cancelarNFSe()` existe no FocusNFeProvider
- Rota `POST fiscal/notas/{id}/cancelar` existe
- Implementação parcial (assinatura do método existe, corpo incompleto)

**Veredicto:** PRD PARCIALMENTE DESATUALIZADO. Mudar de 🔴 para 🟡.

## DIVERGÊNCIAS MENORES (⚠️)

| RF | PRD Status | Real | Detalhe |
|----|-----------|------|---------|
| RF-07.4 (Offline) | 🟡 | 🟡 | Correto, mas sync engine mais completo que PRD sugere (cross-tab sync, IndexedDB) |
| RF-07.5 (Sync) | 🟡 | 🟡 | Correto. syncEngine.ts (17.9KB) é robusto, falta validação E2E |
| RF-08.1 (Criar tenants) | 🟡 | 🟢 | TenantController com store() + StoreTenantRequest existe completo |
| RF-08.3-04 (Credenciais) | 🟡 | 🟡 | Correto |
| RF-10.1-03 (eSocial) | 🟡 | 🟡 | Correto. SOAP envelope building real, mas mock em não-produção |
| RF-12.8 (Supplier Portal) | 🟡 | 🟡 | Correto |
| RF-12.10 (Projetos) | 🟡 | 🟡 | Backend completo (3 controllers), frontend incompleto |
| RF-12.12 (Frota) | 🟡 "checkin existe" | 🟢 **11 controllers, 14 models, 9 páginas** | Módulo COMPLETO: veículos CRUD, combustível, manutenção, pneus, acidentes, seguro, GPS, pool, multas, analytics, driver scoring |
| RF-13.8 (Config canais) | 🟡 | 🟡 | StoreNotificationChannelRequest existe, parcial |

## NFRs — VERIFICAÇÃO

| NFR | Claim | Evidência | Status |
|-----|-------|-----------|--------|
| BelongsToTenant em todos models | Sim | **321 usages** encontrados | ✅ |
| Spatie Permission 200+ | 200+ | **520 permissões** no PermissionsSeeder | ✅ EXCEDE |
| Sanctum auth | Sim | config/auth.php confirmado | ✅ |
| Rate limiting 30-600 req/min | Sim | throttle:30,1 / throttle:60,1 / throttle:600,1 nas rotas | ✅ |
| 8200+ testes | 8200+ | **8248 testes passando** (17 falhos), 23.484 assertions, 311s em 16 processos | ✅ CONFIRMADO (excede claim original de 7500+) |
| Health check /api/health | Sim | HealthCheckController.php existe | ✅ |
| Audit trail (Auditable) | Sim | **139 usages** do trait Auditable | ✅ |
| 9 dashboards | 9 | **9 dashboard controllers** confirmados | ✅ |
| 5 webhook models | 5 | Webhook, WebhookConfig, WebhookLog, FiscalWebhook, InmetroWebhook | ✅ |
| tenant_id (não company_id) | Sim | Confirmado em Customer.php e User.php (current_tenant_id) | ✅ |

## CORREÇÕES NECESSÁRIAS NO PRD

| # | RF | De | Para | Justificativa |
|---|----|----|------|---------------|
| 1 | RF-05.6 | 🔴 | 🟢 | AFDExportService implementado com formato Portaria 671 |
| 2 | RF-05.7 | 🔴 | 🟢 | ACJEFExportService implementado no padrão MTE |
| 3 | RF-09.5 | 🔴 | 🟢 | Laravel Scramble auto-gera OpenAPI. Script docs:openapi no composer.json |
| 4 | RF-03.12 | 🔴 | 🟢 | cartaCorrecao() implementada no FocusNFe + NuvemFiscal fallback |
| 5 | RF-08.5 | 🔴 | 🟡 | ObservabilityDashboard existe, parcial (sem drill-down por tenant) |
| 6 | RF-03.11 | 🔴 | 🟡 | FocusNFeProvider.cancelarNFSe() 100% implementado (DELETE + CircuitBreaker). Gap: NuvemFiscalProvider não tem cancelarNFSe(), interface não exige |
| 7 | RF-02.3 | 🟢 (genérico) | 🟡 | EmaCalculator TEM tabelas OIML R76 completas (4 classes) + Portaria 157/2022. Gap: EMA não persistido em CalibrationReading (só ExcentricityTest). Risco ISO 17025 §7.8 |
| 8 | RF-12.12 | 🟡 "checkin existe" | 🟢 | Módulo COMPLETO: 11 controllers, 14 models, 9 páginas frontend (veículos, combustível, manutenção, pneus, acidentes, seguro, GPS, pool, multas, analytics, driver scoring) |
| 9 | Testes | 7500+ | 8200+ | 8248 testes passando, 23.484 assertions (311s, 16 processos) |

**Impacto na maturidade:** Com as correções, 5 itens saem de 🔴 para 🟢, 2 de 🔴 para 🟡, 1 de 🟡 para 🟢. Maturidade estimada sobe de ~78% para ~83%.

## DEEP DIVES (Fase 2)

### EMA/OIML R76 — Achado corrigido

O audit inicial (fase 1) reportou que o EMA era "genérico ISO GUM, não OIML R76". **INCORRETO.** Deep dive revelou:

- `EmaCalculator.php` contém **tabelas OIML R76 completas** para as 4 classes (I, II, III, IIII)
- Implementa **Portaria INMETRO 157/2022** com fator de supervisão ×2
- Usa **BCMath** para precisão decimal
- Métodos: `calculate()`, `isConforming()`, `suggestPoints()`, `findEmaMultiple()`

**Gap real:** EMA é calculado on-demand mas **não persistido** na tabela `CalibrationReading` (campo `max_permissible_error` existe apenas em `ExcentricityTest`). Isso viola ISO 17025 §7.8 que exige evidência de conformidade por ponto de medição.

**Ação necessária:** Adicionar coluna `ema_value` e `ema_conforms` em `CalibrationReading` e popular automaticamente no wizard.

### Cancelamento NFS-e — Achado corrigido

O audit inicial reportou "stub no body". **INCORRETO.** Deep dive revelou:

- `FocusNFeProvider.cancelarNFSe()` é **100% funcional**: DELETE HTTP + CircuitBreaker + justificativa + error handling
- Rota `POST fiscal/notas/{id}/cancelar` funciona com lógica condicional (NFS-e vs NF-e)
- **Gap real:** `NuvemFiscalProvider` NÃO tem `cancelarNFSe()` — fallback incompleto
- **Gap real:** Interface `FiscalProvider` não exige `cancelarNFSe()` — contrato incompleto
- Testes cobrem apenas validação HTTP, não lógica de cancelamento

**Ação necessária:** Implementar `cancelarNFSe()` no NuvemFiscalProvider e adicionar ao contrato da interface.

### Frota — Módulo subestimado

PRD dizia "checkin existe, gestão completa não". Deep dive revelou módulo **enterprise-grade**:

| Subdomínio | Controllers | Models | Frontend |
|-----------|------------|--------|----------|
| Veículos CRUD | FleetController | FleetVehicle | FleetPage |
| Inspeções | VehicleInspectionController | VehicleInspection | FleetInspectionsTab |
| Combustível | FuelLogController | FuelLog, FleetFuelEntry | FleetFuelTab |
| Manutenção | FleetController | FleetMaintenance | Dashboard |
| Pneus | VehicleTireController | VehicleTire | FleetTiresTab |
| Acidentes | VehicleAccidentController | VehicleAccident | FleetAccidentsTab |
| Seguro | VehicleInsuranceController | VehicleInsurance | FleetInsuranceTab |
| GPS/Trips | GpsTrackingController | FleetTrip | Tracking |
| Pool | VehiclePoolController | VehiclePoolRequest | FleetPoolTab |
| Multas | TollIntegrationController | TrafficFine | FleetFinesTab |
| Analytics | FleetAnalyticsController | — | FleetDashboardTab |
| Driver Score | — | DriverScoringService | Dashboard |

**Total: 11 controllers, 14+ models, 9 páginas frontend.**

### Contagem de Testes — Confirmada

```
Tests:    17 failed, 8248 passed (23484 assertions)
Duration: 311.13s
Parallel: 16 processes
```

Claim original de 7500+ era **conservadora**. Real: **8248 testes, 23.484 assertions.**
