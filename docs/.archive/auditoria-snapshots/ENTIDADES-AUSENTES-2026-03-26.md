# Entidades Documentadas Ausentes no Código Fonte
**Auditoria cruzada Models vs Documentos de Módulos (2026-03-26)**
**Última atualização:** 2026-03-26 (pós Fase 4 completa)

## Entidades RESOLVIDAS (criadas durante Fase 4)

| Módulo | Entidade | Status | Resolução |
|---|---|---|---|
| **Helpdesk** | `TicketCategory` | ✅ CRIADO | Model, migration, controller, factory, 12 testes |
| **Helpdesk** | `EscalationRule` | ✅ CRIADO | Model, migration, controller, factory, 12 testes |
| **Helpdesk** | `SlaViolation` | ✅ CRIADO | Model, migration, controller (read-only), factory, 7 testes |
| **Contracts** | `ContractMeasurement` | ✅ CRIADO | Model, controller, factory, 14 testes |
| **Contracts** | `ContractAddendum` | ✅ CRIADO | Model, controller, factory, 13 testes |
| **Quality / SGQ** | `NonConformity` (RNC) | ✅ CRIADO | Model, migration, controller, factory, 18 testes (ISO-9001) |
| **TvDashboard** | `TvDashboardConfig` | ✅ CRIADO | Model, migration, controller, factory, KPI job |
| **Mobile / PWA** | `SyncQueueItem` | ✅ CRIADO | Model, migration, factory, 15 testes |
| **Mobile / PWA** | `KioskSession` | ✅ CRIADO | Model, migration, factory |
| **Mobile / PWA** | `OfflineMapRegion` | ✅ CRIADO | Model, migration, factory |

## Entidades AINDA AUSENTES

| Módulo | Entidade | Prioridade | Nota |
|---|---|---|---|
| **Lab** | `LabEnvironmentalLog` | Média | Registro contínuo de T/UR para ambiente ISO 17025. Pendente para Fase 5 (Compliance). |

**10 de 11 entidades resolvidas (91%). 1 pendente (Lab — prioridade média).**
