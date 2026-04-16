import { BrowserRouter, Routes, Route, Navigate, useLocation, Outlet } from 'react-router-dom'
import { lazy, Suspense, useEffect, useRef, useState } from 'react'
import { QueryClientProvider } from '@tanstack/react-query'
import { useAuthStore } from '@/stores/auth-store'
import { QueryDevtoolsLoader } from '@/components/dev/QueryDevtoolsLoader'
import { TooltipProvider } from '@/components/ui/tooltip'
import { AppLayout } from '@/components/layout/AppLayout'
import { CommandPalette } from '@/components/layout/CommandPalette'
import { PortalLayout } from './components/layout/PortalLayout'
import { queryClient } from '@/lib/query-client'
import { usePortalAuthStore } from './stores/portal-auth-store'
import TechShell from '@/components/layout/TechShell'
import { TechAutoRedirect } from '@/components/auth/TechAutoRedirect'
import { Toaster } from '@/components/ui/sonner'
import { ErrorBoundary } from '@/components/ErrorBoundary'
import TechProfilePage from '@/pages/tech/TechProfilePage'

// --- Lazy-loaded pages (code splitting) ---
const LoginPage = lazy(() => import('@/pages/LoginPage').then(m => ({ default: m.LoginPage })))
const ForgotPasswordPage = lazy(() => import('@/pages/ForgotPasswordPage').then(m => ({ default: m.ForgotPasswordPage })))
const ResetPasswordPage = lazy(() => import('@/pages/ResetPasswordPage').then(m => ({ default: m.ResetPasswordPage })))
const DashboardPage = lazy(() => import('@/pages/DashboardPage').then(m => ({ default: m.DashboardPage })))
const UsersPage = lazy(() => import('@/pages/iam/UsersPage').then(m => ({ default: m.UsersPage })))
const RolesPage = lazy(() => import('@/pages/iam/RolesPage').then(m => ({ default: m.RolesPage })))
const PermissionsMatrixPage = lazy(() => import('@/pages/iam/PermissionsMatrixPage').then(m => ({ default: m.PermissionsMatrixPage })))
const AuditLogPage = lazy(() => import('@/pages/admin/AuditLogPage').then(m => ({ default: m.AuditLogPage })))
const ObservabilityDashboardPage = lazy(() => import('@/pages/admin/ObservabilityDashboardPage').then(m => ({ default: m.ObservabilityDashboardPage })))
const CustomersPage = lazy(() => import('@/pages/cadastros/CustomersPage').then(m => ({ default: m.CustomersPage })))
const Customer360Page = lazy(() => import('@/pages/cadastros/Customer360Page').then(m => ({ default: m.Customer360Page })))
const CustomerMergePage = lazy(() => import('@/pages/cadastros/CustomerMergePage').then(m => ({ default: m.CustomerMergePage })))
const PriceHistoryPage = lazy(() => import('@/pages/cadastros/PriceHistoryPage').then(m => ({ default: m.PriceHistoryPage })))
const BatchExportPage = lazy(() => import('@/pages/cadastros/BatchExportPage').then(m => ({ default: m.BatchExportPage })))
const ProductsPage = lazy(() => import('@/pages/cadastros/ProductsPage').then(m => ({ default: m.ProductsPage })))
const ServicesPage = lazy(() => import('@/pages/cadastros/ServicesPage').then(m => ({ default: m.ServicesPage })))
const SuppliersPage = lazy(() => import('@/pages/cadastros/SuppliersPage').then(m => ({ default: m.SuppliersPage })))
const CatalogAdminPage = lazy(() => import('@/pages/catalogo/CatalogAdminPage').then(m => ({ default: m.CatalogAdminPage })))
const CatalogPublicPage = lazy(() => import('@/pages/catalogo/CatalogPublicPage'))
const WorkOrdersListPage = lazy(() => import('@/pages/os/WorkOrdersListPage').then(m => ({ default: m.WorkOrdersListPage })))
const WorkOrderKanbanPage = lazy(() => import('@/pages/os/WorkOrderKanbanPage').then(m => ({ default: m.WorkOrderKanbanPage })))
const WorkOrderDashboardPage = lazy(() => import('@/pages/os/WorkOrderDashboardPage'))
const WorkOrderMapPage = lazy(() => import('@/pages/os/WorkOrderMapPage'))
const OSCalendarPage = lazy(() => import('@/pages/os/OSCalendarPage'))
const ContractsPage = lazy(() => import('@/pages/contratos/ContractsPage'))

// Portal do Cliente
const PortalLoginPage = lazy(() => import('./pages/portal/PortalLoginPage').then(m => ({ default: m.PortalLoginPage })))
const PortalDashboardPage = lazy(() => import('./pages/portal/PortalDashboardPage').then(m => ({ default: m.PortalDashboardPage })))
const PortalWorkOrdersPage = lazy(() => import('./pages/portal/PortalWorkOrdersPage').then(m => ({ default: m.PortalWorkOrdersPage })))
const PortalQuotesPage = lazy(() => import('./pages/portal/PortalQuotesPage').then(m => ({ default: m.PortalQuotesPage })))
const PortalFinancialsPage = lazy(() => import('./pages/portal/PortalFinancialsPage').then(m => ({ default: m.PortalFinancialsPage })))
const PortalServiceCallPage = lazy(() => import('./pages/portal/PortalServiceCallPage').then(m => ({ default: m.PortalServiceCallPage })))
const PortalCertificatesPage = lazy(() => import('./pages/portal/PortalCertificatesPage').then(m => ({ default: m.PortalCertificatesPage })))
const PortalEquipmentPage = lazy(() => import('./pages/portal/PortalEquipmentPage').then(m => ({ default: m.PortalEquipmentPage })))
const GoogleCalendarSyncPage = lazy(() => import('@/pages/configuracoes/GoogleCalendarSyncPage').then(m => ({ default: m.GoogleCalendarSyncPage })))
const ExpenseLimitsConfigPage = lazy(() => import('@/pages/configuracoes/ExpenseLimitsConfigPage').then(m => ({ default: m.ExpenseLimitsConfigPage })))

const WorkOrderCreatePage = lazy(() => import('@/pages/os/WorkOrderCreatePage').then(m => ({ default: m.WorkOrderCreatePage })))
const WorkOrderDetailPage = lazy(() => import('@/pages/os/WorkOrderDetailPage').then(m => ({ default: m.WorkOrderDetailPage })))
const RecurringContractsPage = lazy(() => import('@/pages/os/RecurringContractsPage').then(m => ({ default: m.RecurringContractsPage })))
const SchedulesPage = lazy(() => import('@/pages/tecnicos/SchedulesPage').then(m => ({ default: m.SchedulesPage })))
const TimeEntriesPage = lazy(() => import('@/pages/tecnicos/TimeEntriesPage').then(m => ({ default: m.TimeEntriesPage })))
const TechnicianCashPage = lazy(() => import('@/pages/tecnicos/TechnicianCashPage').then(m => ({ default: m.TechnicianCashPage })))
const AccountsReceivablePage = lazy(() => import('@/pages/financeiro/AccountsReceivablePage').then(m => ({ default: m.AccountsReceivablePage })))
const AccountsPayablePage = lazy(() => import('@/pages/financeiro/AccountsPayablePage').then(m => ({ default: m.AccountsPayablePage })))
const CommissionsPage = lazy(() => import('@/pages/financeiro/CommissionsPage').then(m => ({ default: m.CommissionsPage })))
const CommissionDashboardPage = lazy(() => import('@/pages/financeiro/CommissionDashboardPage').then(m => ({ default: m.CommissionDashboardPage })))
const ExpensesPage = lazy(() => import('@/pages/financeiro/ExpensesPage').then(m => ({ default: m.ExpensesPage })))
const FuelingLogsPage = lazy(() => import('@/pages/financeiro/FuelingLogsPage').then(m => ({ default: m.FuelingLogsPage })))
const PaymentMethodsPage = lazy(() => import('@/pages/financeiro/PaymentMethodsPage').then(m => ({ default: m.PaymentMethodsPage })))
const PaymentsPage = lazy(() => import('@/pages/financeiro/PaymentsPage').then(m => ({ default: m.PaymentsPage })))
const CashFlowPage = lazy(() => import('@/pages/financeiro/CashFlowPage').then(m => ({ default: m.CashFlowPage })))
const CashFlowWeeklyDashboardPage = lazy(() => import('@/pages/financeiro/CashFlowWeeklyDashboardPage').then(m => ({ default: m.CashFlowWeeklyDashboardPage })))
const InvoicesPage = lazy(() => import('@/pages/financeiro/InvoicesPage').then(m => ({ default: m.InvoicesPage })))
const BankReconciliationPage = lazy(() => import('@/pages/financeiro/BankReconciliationPage').then(m => ({ default: m.BankReconciliationPage })))
const ReconciliationRulesPage = lazy(() => import('@/pages/financeiro/ReconciliationRulesPage'))
const ReconciliationDashboardPage = lazy(() => import('@/pages/financeiro/ReconciliationDashboardPage').then(m => ({ default: m.ReconciliationDashboardPage })))
const FinanceiroDashboardPage = lazy(() => import('@/pages/financeiro/FinanceiroDashboardPage').then(m => ({ default: m.FinanceiroDashboardPage })))
const ConsolidatedFinancialPage = lazy(() => import('@/pages/financeiro/ConsolidatedFinancialPage').then(m => ({ default: m.ConsolidatedFinancialPage })))
const ChartOfAccountsPage = lazy(() => import('@/pages/financeiro/ChartOfAccountsPage').then(m => ({ default: m.ChartOfAccountsPage })))
const SlaPoliciesPage = lazy(() => import('@/pages/os/SlaPoliciesPage').then(m => ({ default: m.SlaPoliciesPage })))
const SlaDashboardPage = lazy(() => import('@/pages/os/SlaDashboardPage').then(m => ({ default: m.SlaDashboardPage })))
const WorkOrderTemplatesPage = lazy(() => import('@/pages/os/WorkOrderTemplatesPage').then(m => ({ default: m.WorkOrderTemplatesPage })))
const ChecklistPage = lazy(() => import('@/pages/operational/checklists/ChecklistPage').then(m => ({ default: m.ChecklistPage })))
const NotificationsPage = lazy(() => import('@/pages/notificacoes/NotificationsPage').then(m => ({ default: m.NotificationsPage })))
const AccountPayableCategoriesPage = lazy(() => import('@/pages/financeiro/AccountPayableCategoriesPage').then(m => ({ default: m.AccountPayableCategoriesPage })))
const BankAccountsPage = lazy(() => import('@/pages/financeiro/BankAccountsPage').then(m => ({ default: m.BankAccountsPage })))
const FundTransfersPage = lazy(() => import('@/pages/financeiro/FundTransfersPage').then(m => ({ default: m.FundTransfersPage })))
const FiscalNotesPage = lazy(() => import('@/pages/fiscal/FiscalNotesPage'))
const FiscalConfigPage = lazy(() => import('@/pages/fiscal/FiscalConfigPage'))
const FiscalDashboardPage = lazy(() => import('@/pages/fiscal/FiscalDashboard'))
const ServiceChecklistsPage = lazy(() => import('@/pages/os/ServiceChecklistsPage').then(m => ({ default: m.ServiceChecklistsPage })))
const PartsKitsPage = lazy(() => import('@/pages/os/PartsKitsPage').then(m => ({ default: m.PartsKitsPage })))

const ReportsPage = lazy(() => import('@/pages/relatorios/ReportsPage').then(m => ({ default: m.ReportsPage })))
const AnalyticsHubPage = lazy(() => import('@/pages/analytics/AnalyticsHubPage').then(m => ({ default: m.AnalyticsHubPage })))
const SettingsPage = lazy(() => import('@/pages/configuracoes/SettingsPage').then(m => ({ default: m.SettingsPage })))
const LookupsPage = lazy(() => import('@/pages/configuracoes/LookupsPage').then(m => ({ default: m.LookupsPage })))
const BranchesPage = lazy(() => import('@/pages/configuracoes/BranchesPage').then(m => ({ default: m.BranchesPage })))
const ProfilePage = lazy(() => import('@/pages/configuracoes/ProfilePage').then(m => ({ default: m.ProfilePage })))
const TenantManagementPage = lazy(() => import('@/pages/configuracoes/TenantManagementPage').then(m => ({ default: m.TenantManagementPage })))
const QuotesListPage = lazy(() => import('@/pages/orcamentos/QuotesListPage').then(m => ({ default: m.QuotesListPage })))
const QuoteCreatePage = lazy(() => import('@/pages/orcamentos/QuoteCreatePage').then(m => ({ default: m.QuoteCreatePage })))
const QuoteDetailPage = lazy(() => import('@/pages/orcamentos/QuoteDetailPage').then(m => ({ default: m.QuoteDetailPage })))
const QuoteEditPage = lazy(() => import('@/pages/orcamentos/QuoteEditPage').then(m => ({ default: m.QuoteEditPage })))
const QuotePublicApprovalPage = lazy(() => import('@/pages/orcamentos/QuotePublicApprovalPage').then(m => ({ default: m.QuotePublicApprovalPage })))
const QuotesDashboardPage = lazy(() => import('@/pages/orcamentos/QuotesDashboardPage'))
const ServiceCallsPage = lazy(() => import('@/pages/chamados/ServiceCallsPage').then(m => ({ default: m.ServiceCallsPage })))
const ServiceCallCreatePage = lazy(() => import('@/pages/chamados/ServiceCallCreatePage').then(m => ({ default: m.ServiceCallCreatePage })))
const ServiceCallMapPage = lazy(() => import('@/pages/chamados/ServiceCallMapPage').then(m => ({ default: m.ServiceCallMapPage })))
const TechnicianAgendaPage = lazy(() => import('@/pages/chamados/TechnicianAgendaPage').then(m => ({ default: m.TechnicianAgendaPage })))
const ServiceCallDetailPage = lazy(() => import('@/pages/chamados/ServiceCallDetailPage').then(m => ({ default: m.ServiceCallDetailPage })))
const ServiceCallEditPage = lazy(() => import('@/pages/chamados/ServiceCallEditPage').then(m => ({ default: m.ServiceCallEditPage })))
const ServiceCallKanbanPage = lazy(() => import('@/pages/chamados/ServiceCallKanbanPage'))
const ServiceCallDashboardPage = lazy(() => import('@/pages/chamados/ServiceCallDashboardPage'))
const ImportPage = lazy(() => import('@/pages/importacao/ImportPage'))
const AuvoImportPage = lazy(() => import('@/pages/integracao/AuvoImportPage'))
const EmailInboxPage = lazy(() => import('@/pages/emails/EmailInboxPage'))
const EmailComposePage = lazy(() => import('@/pages/emails/EmailComposePage'))
const EmailSettingsPage = lazy(() => import('@/pages/emails/EmailSettingsPage'))
const EquipmentListPage = lazy(() => import('@/pages/equipamentos/EquipmentListPage'))
const EquipmentModelsPage = lazy(() => import('@/pages/equipamentos/EquipmentModelsPage').then(m => ({ default: m.default })))
const EquipmentDetailPage = lazy(() => import('@/pages/equipamentos/EquipmentDetailPage'))
const EquipmentEditPage = lazy(() => import('@/pages/equipamentos/EquipmentEditPage').then(m => ({ default: m.default })))
const EquipmentCreatePage = lazy(() => import('@/pages/equipamentos/EquipmentCreatePage'))
const EquipmentCalendarPage = lazy(() => import('@/pages/equipamentos/EquipmentCalendarPage'))
const StandardWeightsPage = lazy(() => import('@/pages/equipamentos/StandardWeightsPage'))
const CrmDashboardPage = lazy(() => import('@/pages/CrmDashboardPage').then(m => ({ default: m.CrmDashboardPage })))
const CrmPipelinePage = lazy(() => import('@/pages/CrmPipelinePage').then(m => ({ default: m.CrmPipelinePage })))
const MessageTemplatesPage = lazy(() => import('@/pages/MessageTemplatesPage').then(m => ({ default: m.MessageTemplatesPage })))
const CrmForecastPage = lazy(() => import('@/pages/crm/CrmForecastPage').then(m => ({ default: m.CrmForecastPage })))
const CrmGoalsPage = lazy(() => import('@/pages/crm/CrmGoalsPage').then(m => ({ default: m.CrmGoalsPage })))
const CrmAlertsPage = lazy(() => import('@/pages/crm/CrmAlertsPage').then(m => ({ default: m.CrmAlertsPage })))
const CrmCalendarPage = lazy(() => import('@/pages/crm/CrmCalendarPage').then(m => ({ default: m.CrmCalendarPage })))
const CrmScoringPage = lazy(() => import('@/pages/crm/CrmScoringPage').then(m => ({ default: m.CrmScoringPage })))
const CrmSequencesPage = lazy(() => import('@/pages/crm/CrmSequencesPage').then(m => ({ default: m.CrmSequencesPage })))
const CrmLossAnalyticsPage = lazy(() => import('@/pages/crm/CrmLossAnalyticsPage').then(m => ({ default: m.CrmLossAnalyticsPage })))
const CrmTerritoriesPage = lazy(() => import('@/pages/crm/CrmTerritoriesPage').then(m => ({ default: m.CrmTerritoriesPage })))
const CrmRenewalsPage = lazy(() => import('@/pages/crm/CrmRenewalsPage').then(m => ({ default: m.CrmRenewalsPage })))
const CrmReferralsPage = lazy(() => import('@/pages/crm/CrmReferralsPage').then(m => ({ default: m.CrmReferralsPage })))
const CrmWebFormsPage = lazy(() => import('@/pages/crm/CrmWebFormsPage').then(m => ({ default: m.CrmWebFormsPage })))
const CrmRevenueIntelligencePage = lazy(() => import('@/pages/crm/CrmRevenueIntelligencePage').then(m => ({ default: m.CrmRevenueIntelligencePage })))
const CrmCompetitorsPage = lazy(() => import('@/pages/crm/CrmCompetitorsPage').then(m => ({ default: m.CrmCompetitorsPage })))
const CrmVelocityPage = lazy(() => import('@/pages/crm/CrmVelocityPage').then(m => ({ default: m.CrmVelocityPage })))
const CrmCohortPage = lazy(() => import('@/pages/crm/CrmCohortPage').then(m => ({ default: m.CrmCohortPage })))
const CrmProposalsPage = lazy(() => import('@/pages/crm/CrmProposalsPage').then(m => ({ default: m.CrmProposalsPage })))
// CRM Field Management (20 novas funcionalidades)
const CrmVisitCheckinsPage = lazy(() => import('@/pages/crm/CrmVisitCheckinsPage').then(m => ({ default: m.CrmVisitCheckinsPage })))
const CrmVisitRoutesPage = lazy(() => import('@/pages/crm/CrmVisitRoutesPage').then(m => ({ default: m.CrmVisitRoutesPage })))
const CrmVisitReportsPage = lazy(() => import('@/pages/crm/CrmVisitReportsPage').then(m => ({ default: m.CrmVisitReportsPage })))
const CrmPortfolioMapPage = lazy(() => import('@/pages/crm/CrmPortfolioMapPage').then(m => ({ default: m.CrmPortfolioMapPage })))
const CrmForgottenClientsPage = lazy(() => import('@/pages/crm/CrmForgottenClientsPage').then(m => ({ default: m.CrmForgottenClientsPage })))
const CrmContactPoliciesPage = lazy(() => import('@/pages/crm/CrmContactPoliciesPage').then(m => ({ default: m.CrmContactPoliciesPage })))
const CrmSmartAgendaPage = lazy(() => import('@/pages/crm/CrmSmartAgendaPage').then(m => ({ default: m.CrmSmartAgendaPage })))
const CrmPostVisitWorkflowPage = lazy(() => import('@/pages/crm/CrmPostVisitWorkflowPage').then(m => ({ default: m.CrmPostVisitWorkflowPage })))
const CrmQuickNotesPage = lazy(() => import('@/pages/crm/CrmQuickNotesPage').then(m => ({ default: m.CrmQuickNotesPage })))
const CrmCommitmentsPage = lazy(() => import('@/pages/crm/CrmCommitmentsPage').then(m => ({ default: m.CrmCommitmentsPage })))
const CrmNegotiationHistoryPage = lazy(() => import('@/pages/crm/CrmNegotiationHistoryPage').then(m => ({ default: m.CrmNegotiationHistoryPage })))
const CrmClientSummaryPage = lazy(() => import('@/pages/crm/CrmClientSummaryPage').then(m => ({ default: m.CrmClientSummaryPage })))
const CrmRfmPage = lazy(() => import('@/pages/crm/CrmRfmPage').then(m => ({ default: m.CrmRfmPage })))
const CrmCoveragePage = lazy(() => import('@/pages/crm/CrmCoveragePage').then(m => ({ default: m.CrmCoveragePage })))
const CrmProductivityPage = lazy(() => import('@/pages/crm/CrmProductivityPage').then(m => ({ default: m.CrmProductivityPage })))
const CrmOpportunitiesPage = lazy(() => import('@/pages/crm/CrmOpportunitiesPage').then(m => ({ default: m.CrmOpportunitiesPage })))
const CrmImportantDatesPage = lazy(() => import('@/pages/crm/CrmImportantDatesPage').then(m => ({ default: m.CrmImportantDatesPage })))
const CrmVisitSurveysPage = lazy(() => import('@/pages/crm/CrmVisitSurveysPage').then(m => ({ default: m.CrmVisitSurveysPage })))
const CrmAccountPlansPage = lazy(() => import('@/pages/crm/CrmAccountPlansPage').then(m => ({ default: m.CrmAccountPlansPage })))
const CrmGamificationPage = lazy(() => import('@/pages/crm/CrmGamificationPage').then(m => ({ default: m.CrmGamificationPage })))
const CrmNpsDashboardPage = lazy(() => import('@/pages/crm/CrmNpsDashboardPage').then(m => ({ default: m.CrmNpsDashboardPage })))
const StockDashboardPage = lazy(() => import('@/pages/estoque/StockDashboardPage').then(m => ({ default: m.StockDashboardPage })))
const StockMovementsPage = lazy(() => import('@/pages/estoque/StockMovementsPage').then(m => ({ default: m.StockMovementsPage })))
const WarehousesPage = lazy(() => import('@/pages/estoque/WarehousesPage').then(m => ({ default: m.WarehousesPage })))
const InventoryListPage = lazy(() => import('@/pages/estoque/InventoryListPage'))
const InventoryExecutionPage = lazy(() => import('@/pages/estoque/InventoryExecutionPage'))
const InventoryCreatePage = lazy(() => import('@/pages/estoque/InventoryCreatePage'))
const InventoryPwaListPage = lazy(() => import('@/pages/estoque/InventoryPwaListPage'))
const InventoryPwaCountPage = lazy(() => import('@/pages/estoque/InventoryPwaCountPage'))
const BatchManagementPage = lazy(() => import('@/pages/estoque/BatchManagementPage'))
const KardexPage = lazy(() => import('@/pages/estoque/KardexPage'))
const StockIntelligencePage = lazy(() => import('@/pages/estoque/StockIntelligencePage'))
const StockLabelsPage = lazy(() => import('@/pages/estoque/StockLabelsPage'))
const StockIntegrationPage = lazy(() => import('@/pages/estoque/StockIntegrationPage'))
const StockTransfersPage = lazy(() => import('@/pages/estoque/StockTransfersPage'))
const UsedStockItemsPage = lazy(() => import('@/pages/estoque/UsedStockItemsPage'))
const SerialNumbersPage = lazy(() => import('@/pages/estoque/SerialNumbersPage'))
const AgendaPage = lazy(() => import('@/pages/agenda/AgendaPage').then(m => ({ default: m.AgendaPage })))
const AgendaDashboardPage = lazy(() => import('@/pages/agenda/AgendaDashboardPage').then(m => ({ default: m.AgendaDashboardPage })))
const AgendaRulesPage = lazy(() => import('@/pages/agenda/AgendaRulesPage').then(m => ({ default: m.AgendaRulesPage })))
const AgendaKanbanPage = lazy(() => import('@/pages/agenda/AgendaKanbanPage').then(m => ({ default: m.AgendaKanbanPage })))
const InmetroDashboardPage = lazy(() => import('@/pages/inmetro/InmetroDashboardPage').then(m => ({ default: m.InmetroDashboardPage })))
const InmetroLeadsPage = lazy(() => import('@/pages/inmetro/InmetroLeadsPage').then(m => ({ default: m.InmetroLeadsPage })))
const InmetroImportPage = lazy(() => import('@/pages/inmetro/InmetroImportPage').then(m => ({ default: m.InmetroImportPage })))

const InmetroOwnerDetailPage = lazy(() => import('@/pages/inmetro/InmetroOwnerDetailPage').then(m => ({ default: m.InmetroOwnerDetailPage })))
const InmetroInstrumentsPage = lazy(() => import('@/pages/inmetro/InmetroInstrumentsPage').then(m => ({ default: m.InmetroInstrumentsPage })))
const InmetroMapPage = lazy(() => import('@/pages/inmetro/InmetroMapPage').then(m => ({ default: m.InmetroMapPage })))
const InmetroMarketPage = lazy(() => import('@/pages/inmetro/InmetroMarketPage').then(m => ({ default: m.InmetroMarketPage })))
const InmetroProspectionPage = lazy(() => import('@/pages/inmetro/InmetroProspectionPage'))
const InmetroExecutivePage = lazy(() => import('@/pages/inmetro/InmetroExecutivePage'))
const InmetroCompliancePage = lazy(() => import('@/pages/inmetro/InmetroCompliancePage'))
const InmetroWebhooksPage = lazy(() => import('@/pages/inmetro/InmetroWebhooksPage'))
const InmetroCompetitorPage = lazy(() => import('@/pages/inmetro/InmetroCompetitorPage'))
const InmetroSealManagement = lazy(() => import('@/pages/inmetro/InmetroSealManagement'))
const InmetroSealReportPage = lazy(() => import('@/pages/inmetro/InmetroSealReportPage'))

// 200 Features — Novos Módulos
const FleetPage = lazy(() => import('@/pages/fleet/FleetPage'))
const HRPage = lazy(() => import('@/pages/rh/HRPage'))
const ClockInPage = lazy(() => import('@/pages/rh/ClockInPage'))
const EspelhoPontoPage = lazy(() => import('@/pages/rh/EspelhoPontoPage'))
const GeofenceLocationsPage = lazy(() => import('@/pages/rh/GeofenceLocationsPage'))
const ClockAdjustmentsPage = lazy(() => import('@/pages/rh/ClockAdjustmentsPage'))
const JourneyPage = lazy(() => import('@/pages/rh/JourneyPage'))
const JourneyRulesPage = lazy(() => import('@/pages/rh/JourneyRulesPage'))
const JourneyDayPage = lazy(() => import('@/pages/rh/JourneyDayPage'))
const JourneyPoliciesPage = lazy(() => import('@/pages/rh/JourneyPoliciesPage'))
const JourneyApprovalPage = lazy(() => import('@/pages/rh/JourneyApprovalPage'))
const TravelRequestsPage = lazy(() => import('@/pages/rh/TravelRequestsPage'))
const CertificationsPage = lazy(() => import('@/pages/rh/CertificationsPage'))
const BiometricConsentPage = lazy(() => import('@/pages/rh/BiometricConsentPage'))
const PayrollJourneyPage = lazy(() => import('@/pages/rh/PayrollJourneyPage'))
const HourBankDetailPage = lazy(() => import('@/pages/rh/HourBankDetailPage'))
const HourBankPage = lazy(() => import('@/pages/rh/HourBankPage'))
const HolidaysPage = lazy(() => import('@/pages/rh/HolidaysPage'))
const LeavesManagementPage = lazy(() => import('@/pages/rh/LeavesManagementPage'))
const FiscalCompliancePage = lazy(() => import('@/pages/rh/FiscalCompliancePage'))
const LeavesPage = lazy(() => import('@/pages/rh/LeavesPage'))
const VacationBalancePage = lazy(() => import('@/pages/rh/VacationBalancePage'))
const EmployeeDocumentsPage = lazy(() => import('@/pages/rh/EmployeeDocumentsPage'))
const OnboardingPage = lazy(() => import('@/pages/rh/OnboardingPage'))
const QualityPage = lazy(() => import('@/pages/qualidade/QualityPage'))
const QualityAuditsPage = lazy(() => import('@/pages/qualidade/QualityAuditsPage'))
const QualityAuditDetailPage = lazy(() => import('@/pages/qualidade/QualityAuditDetailPage'))
const ManagementReviewPage = lazy(() => import('@/pages/qualidade/ManagementReviewPage'))
const OperationalDashboardPage = lazy(() => import('@/pages/operacional/OperationalDashboardPage'))
const AutomationPage = lazy(() => import('@/pages/automacao/AutomationPage'))
const AdvancedFeaturesPage = lazy(() => import('@/pages/avancado/AdvancedFeaturesPage'))
const AlertsPage = lazy(() => import('@/pages/alertas/AlertsPage'))
const CalibrationReadingsPage = lazy(() => import('@/pages/calibracao/CalibrationReadingsPage'))
const CalibrationWizardPage = lazy(() => import('@/pages/calibracao/CalibrationWizardPage'))
const CalibrationListPage = lazy(() => import('@/pages/calibracao/CalibrationListPage'))
const WhatsAppConfigPage = lazy(() => import('@/pages/configuracoes/WhatsAppConfigPage'))
const AgingReceivablesPage = lazy(() => import('@/pages/financeiro/AgingReceivablesPage').then(m => ({ default: m.AgingReceivablesPage })))
const DebtRenegotiationPage = lazy(() => import('@/pages/financeiro/DebtRenegotiationPage'))
const WeightAssignmentsPage = lazy(() => import('@/pages/equipamentos/WeightAssignmentsPage'))
const ToolCalibrationsPage = lazy(() => import('@/pages/estoque/ToolCalibrationsPage'))
const EquipmentQrPublicPage = lazy(() => import('@/pages/equipamentos/EquipmentQrPublicPage'))
const CertificateTemplatesPage = lazy(() => import('@/pages/calibracao/CertificateTemplatesPage'))
const WhatsAppLogPage = lazy(() => import('@/pages/configuracoes/WhatsAppLogPage'))
const CollectionAutomationPage = lazy(() => import('@/pages/financeiro/CollectionAutomationPage'))
const ExpenseReimbursementsPage = lazy(() => import('@/pages/financeiro/ExpenseReimbursementsPage').then(m => ({ default: m.ExpenseReimbursementsPage })))
const FinancialChecksPage = lazy(() => import('@/pages/financeiro/FinancialChecksPage').then(m => ({ default: m.FinancialChecksPage })))
const SupplierContractsPage = lazy(() => import('@/pages/financeiro/SupplierContractsPage').then(m => ({ default: m.SupplierContractsPage })))
const SupplierAdvancesPage = lazy(() => import('@/pages/financeiro/SupplierAdvancesPage').then(m => ({ default: m.SupplierAdvancesPage })))
const ReceivablesSimulatorPage = lazy(() => import('@/pages/financeiro/ReceivablesSimulatorPage').then(m => ({ default: m.ReceivablesSimulatorPage })))
const BatchPaymentApprovalPage = lazy(() => import('@/pages/financeiro/BatchPaymentApprovalPage').then(m => ({ default: m.BatchPaymentApprovalPage })))
const ExpenseAllocationPage = lazy(() => import('@/pages/financeiro/ExpenseAllocationPage').then(m => ({ default: m.ExpenseAllocationPage })))
const TaxCalculatorPage = lazy(() => import('@/pages/financeiro/TaxCalculatorPage').then(m => ({ default: m.TaxCalculatorPage })))
const DrePage = lazy(() => import('@/pages/financeiro/DrePage').then(m => ({ default: m.DrePage })))
const FixedAssetsPage = lazy(() => import('@/pages/financeiro/FixedAssetsPage').then(m => ({ default: m.FixedAssetsPage })))
const FixedAssetsDashboardPage = lazy(() => import('@/pages/financeiro/FixedAssetsDashboardPage').then(m => ({ default: m.FixedAssetsDashboardPage })))
const FixedAssetsDepreciationPage = lazy(() => import('@/pages/financeiro/FixedAssetsDepreciationPage').then(m => ({ default: m.FixedAssetsDepreciationPage })))
const FixedAssetsMovementsPage = lazy(() => import('@/pages/financeiro/FixedAssetsMovementsPage').then(m => ({ default: m.FixedAssetsMovementsPage })))
const FixedAssetsInventoryPage = lazy(() => import('@/pages/financeiro/FixedAssetsInventoryPage').then(m => ({ default: m.FixedAssetsInventoryPage })))
const FixedAssetsReportsPage = lazy(() => import('@/pages/financeiro/FixedAssetsReportsPage').then(m => ({ default: m.FixedAssetsReportsPage })))
const ProjectsPage = lazy(() => import('@/pages/projects/ProjectsPage').then(m => ({ default: m.ProjectsPage })))
const OrgChartPage = lazy(() => import('@/pages/rh/OrgChartPage'))
const SkillsMatrixPage = lazy(() => import('@/pages/rh/SkillsMatrixPage'))
const PerformancePage = lazy(() => import('@/pages/rh/PerformancePage'))
const BenefitsPage = lazy(() => import('@/pages/rh/BenefitsPage'))
const PayrollPage = lazy(() => import('@/pages/rh/PayrollPage'))
const PayslipPage = lazy(() => import('@/pages/rh/PayslipPage'))
const RescissionPage = lazy(() => import('@/pages/rh/RescissionPage'))
const RecruitmentPage = lazy(() => import('@/pages/rh/RecruitmentPage'))
const RecruitmentKanbanPage = lazy(() => import('@/pages/rh/RecruitmentKanbanPage'))
const ESocialPage = lazy(() => import('@/pages/rh/ESocialPage'))
const TaxTablesPage = lazy(() => import('@/pages/rh/TaxTablesPage'))
const AuditTrailPage = lazy(() => import('@/pages/rh/AuditTrailPage'))
const PerformanceReviewDetailPage = lazy(() => import('@/pages/rh/PerformanceReviewDetailPage'))
const TvDashboard = lazy(() => import('@/pages/tv/TvDashboard'))
const TvCamerasPage = lazy(() => import('@/pages/tv/TvCamerasPage'))
const AIAnalyticsPage = lazy(() => import('@/pages/ia/AIAnalyticsPage'))
const PeopleAnalyticsPage = lazy(() => import('@/pages/rh/PeopleAnalyticsPage'))
const AccountingReportsPage = lazy(() => import('@/pages/rh/AccountingReportsPage'))
const CltViolationsPage = lazy(() => import('@/pages/rh/CltViolationsPage').then(m => ({ default: m.CltViolationsPage })))

// PWA Share Target
const ShareTargetPage = lazy(() => import('@/pages/ShareTargetPage'))

// Tech (PWA Mobile)
const TechWorkOrdersPage = lazy(() => import('@/pages/tech/TechWorkOrdersPage'))
const TechWorkOrderDetailPage = lazy(() => import('@/pages/tech/TechWorkOrderDetailPage'))
const TechChecklistPage = lazy(() => import('@/pages/tech/TechChecklistPage'))
const TechExpensePage = lazy(() => import('@/pages/tech/TechExpensePage'))
const TechPhotosPage = lazy(() => import('@/pages/tech/TechPhotosPage'))
const TechSignaturePage = lazy(() => import('@/pages/tech/TechSignaturePage'))
const TechSealsPage = lazy(() => import('@/pages/tech/TechSealsPage'))
const TechSettingsPage = lazy(() => import('@/pages/tech/TechSettingsPage'))
const TechBarcodePage = lazy(() => import('@/pages/tech/TechBarcodePage'))
const TechChatPage = lazy(() => import('@/pages/tech/TechChatPage'))
const TechPhotoAnnotationPage = lazy(() => import('@/pages/tech/TechPhotoAnnotationPage'))
const TechVoiceReportPage = lazy(() => import('@/pages/tech/TechVoiceReportPage'))
const TechBluetoothPrintPage = lazy(() => import('@/pages/tech/TechBluetoothPrintPage'))
const TechThermalCameraPage = lazy(() => import('@/pages/tech/TechThermalCameraPage'))
const TechWidgetPage = lazy(() => import('@/pages/tech/TechWidgetPage'))
const TechExpensesOverviewPage = lazy(() => import('@/pages/tech/TechExpensesOverviewPage'))
const TechCashManagementPage = lazy(() => import('@/pages/tech/TechCashPage'))
const TechCreateWorkOrderPage = lazy(() => import('@/pages/tech/TechCreateWorkOrderPage'))
const TechSchedulePage = lazy(() => import('@/pages/tech/TechSchedulePage'))
const TechTimeEntriesPage = lazy(() => import('@/pages/tech/TechTimeEntriesPage'))
const TechNotificationsPage = lazy(() => import('@/pages/tech/TechNotificationsPage'))
const TechEquipmentSearchPage = lazy(() => import('@/pages/tech/TechEquipmentSearchPage'))
const TechAssetScanPage = lazy(() => import('@/pages/tech/TechAssetScanPage'))
const TechRoutePage = lazy(() => import('@/pages/tech/TechRoutePage'))
const TechNpsPage = lazy(() => import('@/pages/tech/TechNpsPage'))
const TechQuickQuotePage = lazy(() => import('@/pages/tech/TechQuickQuotePage'))
const TechCalibrationReadingsPage = lazy(() => import('@/pages/tech/TechCalibrationReadingsPage'))
const TechCertificatePage = lazy(() => import('@/pages/tech/TechCertificatePage'))
const TechCommissionsPage = lazy(() => import('@/pages/tech/TechCommissionsPage'))
const TechDaySummaryPage = lazy(() => import('@/pages/tech/TechDaySummaryPage'))
const TechFeedbackPage = lazy(() => import('@/pages/tech/TechFeedbackPage'))
const TechPriceTablePage = lazy(() => import('@/pages/tech/TechPriceTablePage'))
const TechEquipmentHistoryPage = lazy(() => import('@/pages/tech/TechEquipmentHistoryPage'))
const TechVehicleCheckinPage = lazy(() => import('@/pages/tech/TechVehicleCheckinPage'))
const TechComplaintPage = lazy(() => import('@/pages/tech/TechComplaintPage').then(m => ({ default: m.default })))
const TechContractInfoPage = lazy(() => import('@/pages/tech/TechContractInfoPage').then(m => ({ default: m.default })))
const TechToolInventoryPage = lazy(() => import('@/pages/tech/TechToolInventoryPage'))
const TechVanStockPage = lazy(() => import('@/pages/tech/TechVanStockPage'))
const TechTimeClockPage = lazy(() => import('@/pages/tech/TechTimeClockPage'))
const TechHourBankPage = lazy(() => import('@/pages/tech/TechHourBankPage'))
const TechClockCorrectionPage = lazy(() => import('@/pages/tech/TechClockCorrectionPage'))
const TechJourneyPage = lazy(() => import('@/pages/tech/TechJourneyPage'))
const TechDashboardPage = lazy(() => import('@/pages/tech/TechDashboardPage'))
const TechGoalsPage = lazy(() => import('@/pages/tech/TechGoalsPage'))
const TechServiceCallsPage = lazy(() => import('@/pages/tech/TechServiceCallsPage'))
const TechMaterialRequestPage = lazy(() => import('@/pages/tech/TechMaterialRequestPage'))
const TechAgendaPage = lazy(() => import('@/pages/tech/TechAgendaPage'))
const TechMapViewPage = lazy(() => import('@/pages/tech/TechMapViewPage'))
const QrInventoryScanPage = lazy(() => import('@/pages/tech/QrInventoryScanPage'))
const CeoCockpitPage = lazy(() => import('@/pages/ceo/CeoCockpitPage'))
const LabAdvancedPage = lazy(() => import('@/pages/laboratorio/LabAdvancedPage'))
const SecurityPage = lazy(() => import('@/pages/seguranca/SecurityPage'))
const SalesAdvancedPage = lazy(() => import('@/pages/vendas/SalesAdvancedPage'))
const PaymentReceiptsPage = lazy(() => import('@/pages/financeiro/PaymentReceiptsPage'))
const WorkSchedulesPage = lazy(() => import('@/pages/rh/WorkSchedulesPage'))
const DocumentVersionsPage = lazy(() => import('@/pages/qualidade/DocumentVersionsPage'))
const EquipmentMaintenancePage = lazy(() => import('@/pages/equipamentos/EquipmentMaintenancePage'))
const PortalTicketsPage = lazy(() => import('@/pages/portal/PortalTicketsPage'))
const PortalWorkOrderDetailPage = lazy(() => import('@/pages/portal/PortalWorkOrderDetailPage'))

const commissionsModuleViewPermission = 'commissions.rule.view|commissions.event.view|commissions.settlement.view|commissions.dispute.view|commissions.goal.view|commissions.campaign.view|commissions.recurring.view'

const routePermissionRules: Array<{ match: string; permission: string | null }> = [
  { match: '/agenda/regras', permission: 'agenda.manage.rules' },
  { match: '/agenda/dashboard', permission: 'agenda.manage.kpis' },
  { match: '/agenda/kanban', permission: 'agenda.item.view' },
  { match: '/agenda', permission: 'agenda.item.view' },
  { match: '/iam/usuarios', permission: 'iam.user.view' },
  { match: '/iam/roles', permission: 'iam.role.view' },
  { match: '/iam/permissoes', permission: 'iam.role.view' },
  { match: '/iam/auditoria', permission: 'iam.audit_log.view' },
  { match: '/admin/audit-log', permission: 'iam.audit_log.view' },
  { match: '/cadastros/clientes/fusao', permission: 'cadastros.customer.update' },
  { match: '/cadastros/clientes', permission: 'cadastros.customer.view' },
  { match: '/cadastros/produtos', permission: 'cadastros.product.view' },
  { match: '/cadastros/servicos', permission: 'cadastros.service.view' },
  { match: '/catalogo', permission: 'catalog.view' },
  { match: '/cadastros/historico-precos', permission: 'cadastros.product.view' },
  { match: '/cadastros/exportacao-lote', permission: 'cadastros.customer.view' },
  { match: '/cadastros/fornecedores', permission: 'cadastros.supplier.view' },
  { match: '/orcamentos/novo', permission: 'quotes.quote.create' },
  { match: '/chamados/novo', permission: 'service_calls.service_call.create' },
  { match: '/equipamentos/novo', permission: 'equipments.equipment.create' },
  { match: '/inmetro/instrumentos', permission: 'inmetro.intelligence.view' },
  { match: '/inmetro/mapa', permission: 'inmetro.intelligence.view' },
  { match: '/inmetro/importacao', permission: 'inmetro.intelligence.import' },
  { match: '/inmetro/prospeccao', permission: 'inmetro.intelligence.view' },
  { match: '/inmetro/executivo', permission: 'inmetro.intelligence.view' },
  { match: '/inmetro/compliance', permission: 'inmetro.intelligence.view' },
  { match: '/inmetro/webhooks', permission: 'inmetro.intelligence.view' },
  { match: '/orcamentos/', permission: 'quotes.quote.view' },
  { match: '/orcamentos', permission: 'quotes.quote.view' },
  { match: '/chamados/kanban', permission: 'service_calls.service_call.view' },
  { match: '/chamados/dashboard', permission: 'service_calls.service_call.view' },
  { match: '/chamados', permission: 'service_calls.service_call.view' },
  { match: '/os/nova', permission: 'os.work_order.create' },
  { match: '/os/checklists-servico', permission: 'os.work_order.view' },
  { match: '/os/kits-pecas', permission: 'os.work_order.view' },
  { match: '/os/checklists', permission: 'technicians.checklist.view' },
  { match: '/os/agenda', permission: 'os.work_order.view' },
  { match: '/os/mapa', permission: 'os.work_order.view' },
  { match: '/os/dashboard', permission: 'os.work_order.view' },
  { match: '/contratos', permission: 'contracts.contract.view' },
  { match: '/os', permission: 'os.work_order.view' },
  { match: '/fiscal/notas', permission: 'fiscal.note.view' },
  { match: '/fiscal/configuracoes', permission: 'fiscal.config.view' },
  { match: '/fiscal', permission: 'fiscal.note.view' },
  { match: '/tecnicos/agenda', permission: 'technicians.schedule.view' },
  { match: '/tecnicos/apontamentos', permission: 'technicians.time_entry.view' },
  { match: '/tecnicos/caixa', permission: 'technicians.cashbox.view' },
  { match: '/financeiro/receber', permission: 'finance.receivable.view' },
  { match: '/financeiro/pagar', permission: 'finance.payable.view' },
  { match: '/financeiro/comissoes/dashboard', permission: 'commissions.rule.view' },
  { match: '/financeiro/comissoes', permission: commissionsModuleViewPermission },
  { match: '/financeiro/despesas', permission: 'expenses.expense.view' },
  { match: '/financeiro/pagamentos', permission: 'finance.receivable.view|finance.payable.view' },
  { match: '/financeiro/formas-pagamento', permission: 'finance.payable.view' },
  { match: '/financeiro/fluxo-caixa', permission: 'finance.cashflow.view' },
  { match: '/financeiro/fluxo-caixa-semanal', permission: 'finance.cashflow.view' },
  { match: '/financeiro/faturamento', permission: 'finance.receivable.view' },
  { match: '/financeiro/conciliacao-bancaria', permission: 'finance.receivable.view' },
  { match: '/financeiro/regras-conciliacao', permission: 'finance.receivable.view' },
  { match: '/financeiro/dashboard-conciliacao', permission: 'finance.receivable.view' },
  { match: '/financeiro/consolidado', permission: 'finance.cashflow.view' },
  { match: '/financeiro/plano-contas', permission: 'finance.chart.view' },
  { match: '/financeiro/categorias-pagar', permission: 'finance.payable.view' },
  { match: '/financeiro/contas-bancarias', permission: 'financial.bank_account.view' },
  { match: '/financeiro/transferencias-tecnicos', permission: 'financial.fund_transfer.view' },

  { match: '/estoque/movimentacoes', permission: 'estoque.movement.view' },
  { match: '/estoque/armazens', permission: 'estoque.warehouse.view' },
  { match: '/estoque/inventario-pwa', permission: 'estoque.view' },
  { match: '/estoque/etiquetas', permission: 'estoque.label.print' },
  { match: '/estoque/transferencias', permission: 'estoque.movement.view' },
  { match: '/estoque/movimentar-qr', permission: 'estoque.movement.create' },
  { match: '/estoque', permission: 'estoque.movement.view' },
  { match: '/relatorios', permission: 'reports.os_report.view' },
  { match: '/notificacoes', permission: 'notifications.notification.view' },
  { match: '/importacao', permission: 'import.data.view' },
  { match: '/integracao/auvo', permission: 'auvo.import.view' },
  { match: '/emails/configuracoes', permission: 'email.account.view' },
  { match: '/emails/compose', permission: 'email.inbox.send' },
  { match: '/emails', permission: 'email.inbox.view' },
  { match: '/inmetro/selos', permission: 'inmetro.intelligence.view' },
  { match: '/inmetro', permission: 'inmetro.intelligence.view' },
  { match: '/equipamentos/modelos', permission: 'equipments.equipment_model.view' },
  { match: '/equipamentos/pesos-padrao', permission: 'equipments.standard_weight.view' },
  { match: '/equipamentos/atribuicao-pesos', permission: 'equipments.standard_weight.view' },
  { match: '/equipamentos', permission: 'equipments.equipment.view' },
  { match: '/agenda-calibracoes', permission: 'equipments.equipment.view' },
  { match: '/calibracao/wizard', permission: 'calibration.reading.view' },
  { match: '/calibracao/leituras', permission: 'calibration.reading.view' },
  { match: '/calibracao/templates', permission: 'equipments.calibration.view' },
  { match: '/calibracoes', permission: 'calibration.reading.view' },
  { match: '/estoque/calibracoes-ferramentas', permission: 'calibration.tool.view' },
  { match: '/configuracoes/filiais', permission: 'platform.branch.view' },
  { match: '/configuracoes/empresas', permission: 'platform.tenant.view' },
  { match: '/configuracoes/whatsapp', permission: 'whatsapp.config.view' },
  { match: '/configuracoes/whatsapp/logs', permission: 'whatsapp.log.view' },
  { match: '/configuracoes/auditoria', permission: 'iam.audit_log.view' },
  { match: '/configuracoes/logs-auditoria', permission: 'iam.audit_log.view' },
  { match: '/configuracoes/observabilidade', permission: 'platform.settings.view' },
  { match: '/configuracoes/cadastros-auxiliares', permission: 'lookups.view' },
  { match: '/configuracoes', permission: 'platform.settings.view' },
  { match: '/financeiro/regua-cobranca', permission: 'finance.receivable.view' },
  { match: '/financeiro/cobranca-automatica', permission: 'finance.receivable.view' },
  { match: '/financeiro/renegociacao', permission: 'finance.renegotiation.view' },
  { match: '/financeiro/reembolsos', permission: 'expenses.expense.view' },
  { match: '/financeiro/cheques', permission: 'finance.payable.view' },
  { match: '/financeiro/contratos-fornecedores', permission: 'finance.payable.view' },
  { match: '/financeiro/adiantamentos-fornecedores', permission: 'finance.payable.view' },
  { match: '/financeiro/simulador-recebiveis', permission: 'finance.receivable.view' },
  { match: '/financeiro/aprovacao-lote', permission: 'finance.payable.view' },
  { match: '/financeiro/alocacao-despesas', permission: 'expenses.expense.view' },
  { match: '/financeiro/calculadora-tributos', permission: 'finance.dre.view' },
  { match: '/financeiro/dre', permission: 'finance.dre.view' },
  { match: '/financeiro/ativos/dashboard', permission: 'fixed_assets.dashboard.view' },
  { match: '/financeiro/ativos/depreciacao', permission: 'fixed_assets.depreciation.view|fixed_assets.depreciation.run' },
  { match: '/financeiro/ativos/movimentacoes', permission: 'fixed_assets.asset.view' },
  { match: '/financeiro/ativos/inventario', permission: 'fixed_assets.inventory.manage' },
  { match: '/financeiro/ativos/relatorios', permission: 'fixed_assets.asset.view' },
  { match: '/financeiro/ativos', permission: 'fixed_assets.asset.view' },
  { match: '/projetos', permission: 'projects.project.view|projects.dashboard.view' },
  { match: '/financeiro', permission: 'finance.cashflow.view|finance.receivable.view|finance.payable.view' },
  { match: '/alertas', permission: 'alerts.alert.view' },
  { match: '/qualidade/auditorias/', permission: 'quality.audit.view' },
  { match: '/qualidade/auditorias', permission: 'quality.audit.view' },
  { match: '/operacional', permission: 'os.work_order.view' },
  { match: '/qualidade/documentos', permission: 'quality.document.view' },
  { match: '/qualidade/revisao-direcao', permission: 'quality.management_review.view' },
  { match: '/crm/pipeline', permission: 'crm.pipeline.view' },
  { match: '/crm/clientes', permission: 'crm.deal.view' },
  { match: '/crm/templates', permission: 'crm.message.view' },
  { match: '/crm/forecast', permission: 'crm.forecast.view' },
  { match: '/crm/goals', permission: 'crm.goal.view' },
  { match: '/crm/alerts', permission: 'crm.deal.view' },
  { match: '/crm/calendar', permission: 'crm.deal.view' },
  { match: '/crm/scoring', permission: 'crm.scoring.view' },
  { match: '/crm/sequences', permission: 'crm.sequence.view' },
  { match: '/crm/loss-analytics', permission: 'crm.deal.view' },
  { match: '/crm/territories', permission: 'crm.territory.view' },
  { match: '/crm/renewals', permission: 'crm.renewal.view' },
  { match: '/crm/referrals', permission: 'crm.referral.view' },
  { match: '/crm/web-forms', permission: 'crm.form.view' },
  { match: '/crm/revenue', permission: 'crm.forecast.view' },
  { match: '/crm/competitors', permission: 'crm.deal.view' },
  { match: '/crm/velocity', permission: 'crm.deal.view' },
  { match: '/crm/cohort', permission: 'crm.forecast.view' },
  { match: '/crm/proposals', permission: 'crm.proposal.view' },
  { match: '/crm/nps', permission: 'crm.deal.view' },
  { match: '/crm', permission: 'crm.deal.view' },
  { match: '/perfil', permission: null },
  { match: '/analytics', permission: 'reports.analytics.view' },
  // Novos módulos (200 Features)
  { match: '/frota', permission: 'fleet.vehicle.view' },
  { match: '/rh/ponto', permission: 'hr.clock.view' },
  { match: '/rh/geofences', permission: 'hr.geofence.manage' },
  { match: '/rh/ajustes-ponto', permission: 'hr.adjustment.approve' },
  { match: '/rh/jornada/regras', permission: 'hr.journey.manage' },
  { match: '/rh/jornada/timeline', permission: 'hr.clock.view' },
  { match: '/rh/jornada/politicas', permission: 'hr.clock.manage' },
  { match: '/rh/jornada/aprovacoes', permission: 'hr.clock.manage' },
  { match: '/rh/viagens', permission: 'hr.clock.view' },
  { match: '/rh/certificacoes', permission: 'hr.clock.view' },
  { match: '/rh/consentimento-biometrico', permission: 'hr.clock.view' },
  { match: '/rh/folha-jornada', permission: 'hr.payroll.view' },
  { match: '/rh/jornada', permission: 'hr.journey.view' },
  { match: '/rh/banco-horas', permission: 'hr.journey.view' },
  { match: '/rh/feriados', permission: 'hr.holiday.manage' },
  { match: '/rh/ferias', permission: 'hr.leave.view' },
  { match: '/rh/saldo-ferias', permission: 'hr.leave.view' },
  { match: '/rh/documentos', permission: 'hr.document.view' },
  { match: '/rh/onboarding', permission: 'hr.onboarding.view' },
  { match: '/rh/organograma', permission: 'hr.organization.view' },
  { match: '/rh/skills', permission: 'hr.skills.view' },
  { match: '/rh/desempenho', permission: 'hr.performance.view' },
  { match: '/rh', permission: 'hr.schedule.view' },
  { match: '/qualidade', permission: 'quality.procedure.view' },
  { match: '/automacao', permission: 'automation.rule.view' },
  { match: '/avancado', permission: 'advanced.follow_up.view' },
  { match: '/ia', permission: 'ai.analytics.view' },
  { match: '/ceo-cockpit', permission: 'platform.dashboard.view' },
  { match: '/tv/dashboard', permission: 'tv.dashboard.view' },
  { match: '/tv/cameras', permission: 'tv.camera.manage' },
  { match: '/laboratorio', permission: 'qualidade.view' },
  { match: '/seguranca', permission: 'platform.settings.manage' },
  { match: '/vendas/avancado', permission: 'crm.deal.view' },
  { match: '/financeiro/recibos', permission: 'finance.receivable.view' },
  { match: '/rh/escalas', permission: 'rh.work_schedule.view' },
  { match: '/rh/rescisoes', permission: 'hr.payroll.view' },
  { match: '/rh/recrutamento', permission: 'hr.recruitment.view' },
  { match: '/rh/esocial', permission: 'hr.esocial.view' },
  { match: '/rh/tabelas-fiscais', permission: 'hr.payroll.view' },
  { match: '/rh/audit-trail', permission: 'hr.timeclock.manage' },
  { match: '/rh/violacoes-clt', permission: 'hr.fiscal.access' },
  { match: '/equipamentos/manutencoes', permission: 'equipments.equipment.view' },
  { match: '/share', permission: 'quotes.quote.create|crm.deal.view|os.work_order.create' },
  { match: '/', permission: 'platform.dashboard.view' },
]

function resolveRequiredPermission(pathname: string): string | null {
  if (/^\/orcamentos\/[^/]+\/editar$/.test(pathname)) {
    return 'quotes.quote.update'
  }
  if (/^\/chamados\/[^/]+\/editar$/.test(pathname)) {
    return 'service_calls.service_call.update'
  }
  if (/^\/equipamentos\/[^/]+\/editar$/.test(pathname)) {
    return 'equipments.equipment.update'
  }

  for (const rule of routePermissionRules) {
    if (rule.match === pathname) {
      return rule.permission
    }
    if (rule.match !== '/' && rule.match.endsWith('/') && pathname.startsWith(rule.match)) {
      return rule.permission
    }
    if (rule.match !== '/' && !rule.match.endsWith('/') && pathname.startsWith(`${rule.match}/`)) {
      return rule.permission
    }
  }
  return null
}

function hasPermissionExpression(
  expression: string,
  hasPermission: (permission: string) => boolean
): boolean {
  return expression
    .split('|')
    .map(item => item.trim())
    .filter(Boolean)
    .some(permission => hasPermission(permission))
}

function PageLoader() {
  return (
    <div className="flex items-center justify-center py-20">
      <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary-500 border-t-transparent" />
    </div>
  )
}

function AccessDeniedState({ permission }: { permission: string }) {
  return (
    <div className="mx-auto mt-20 max-w-xl rounded-2xl border border-red-200 bg-red-50/70 p-6 text-center shadow-sm">
      <h2 className="text-lg font-semibold text-red-800">Acesso negado</h2>
      <p className="mt-2 text-sm text-red-700">
        Você não possui a permissão necessária para acessar esta página.
      </p>
      <p className="mt-2 text-xs font-mono text-red-600">{permission}</p>
    </div>
  )
}

function NotFoundPage() {
  return (
    <div className="mx-auto mt-20 max-w-xl rounded-2xl border border-surface-200 bg-white p-8 text-center shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
      <p className="text-sm font-semibold uppercase tracking-wide text-surface-500">404</p>
      <h1 className="mt-2 text-2xl font-bold text-surface-900 dark:text-white">Página não encontrada</h1>
      <p className="mt-3 text-sm text-surface-600 dark:text-surface-300">
        A rota solicitada não existe ou foi removida.
      </p>
      <a
        href="/"
        className="mt-6 inline-flex rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700"
      >
        Voltar ao dashboard
      </a>
    </div>
  )
}

function ProtectedLayout() {
  const { isAuthenticated, fetchMe, logout, user } = useAuthStore()
  const [timedOut, setTimedOut] = useState(false)
  const fetchedRef = useRef(false)

  const FETCH_ME_TIMEOUT_MS = 20_000

  useEffect(() => {
    if (isAuthenticated && !fetchedRef.current) {
      fetchedRef.current = true
      fetchMe().catch(() => {
        logout()
      })
    }
  }, [isAuthenticated, fetchMe, logout])

  useEffect(() => {
    if (isAuthenticated && !user) {
      const timer = setTimeout(() => {
        setTimedOut(true)
        logout()
      }, FETCH_ME_TIMEOUT_MS)
      return () => clearTimeout(timer)
    }
  }, [isAuthenticated, user, logout])

  if (!isAuthenticated) {
    return (
      <>
        <div className="fixed inset-0 flex items-center justify-center bg-background" aria-hidden="true">
          <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary-500 border-t-transparent" />
        </div>
        <Navigate to="/login" replace />
      </>
    )
  }

  if (!user) {
    return (
      <AppLayout>
        <div className="flex flex-col items-center justify-center py-20 gap-4">
          <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary-500 border-t-transparent" />
          <p className="text-sm text-surface-500">
            {timedOut ? 'Sessão expirada. Redirecionando...' : 'Carregando permissões...'}
          </p>
        </div>
      </AppLayout>
    )
  }

  return (
    <AppLayout>
      <ErrorBoundary>
        <PermissionGate />
      </ErrorBoundary>
    </AppLayout>
  )
}

function PermissionGate() {
  const location = useLocation()
  const { hasPermission, hasRole } = useAuthStore()
  const requiredPermission = resolveRequiredPermission(location.pathname)
  const isSuperAdmin = hasRole('super_admin')

  if (requiredPermission && !isSuperAdmin && !hasPermissionExpression(requiredPermission, hasPermission)) {
    return <AccessDeniedState permission={requiredPermission} />
  }

  return <Outlet />
}

function GuestRoute({ children }: { children: React.ReactNode }) {
  const { isAuthenticated } = useAuthStore()
  if (isAuthenticated) {
    return (
      <>
        <div className="fixed inset-0 flex items-center justify-center bg-background" aria-hidden="true">
          <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary-500 border-t-transparent" />
        </div>
        <Navigate to="/" replace />
      </>
    )
  }
  return <>{children}</>
}

function ProtectedPortalRoute({ children }: { children: React.ReactNode }) {
  const { isAuthenticated, user, fetchMe, logout } = usePortalAuthStore()
  const fetchedRef = useRef(false)
  const [timedOut, setTimedOut] = useState(false)

  useEffect(() => {
    if (isAuthenticated && !user && !fetchedRef.current) {
      fetchedRef.current = true
      fetchMe().catch(() => {
        logout().catch(() => undefined)
      })
    }
  }, [isAuthenticated, user, fetchMe, logout])

  useEffect(() => {
    if (!isAuthenticated || user) {
      setTimedOut(false)
      return
    }

    const timer = setTimeout(() => {
      setTimedOut(true)
      logout().catch(() => undefined)
    }, 20_000)

    return () => clearTimeout(timer)
  }, [isAuthenticated, user, logout])

  if (!isAuthenticated) {
    return (
      <>
        <div className="fixed inset-0 flex items-center justify-center bg-background" aria-hidden="true">
          <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary-500 border-t-transparent" />
        </div>
        <Navigate to="/portal/login" replace />
      </>
    )
  }
  if (!user) {
    return (
      <PortalLayout>
        <div className="flex flex-col items-center justify-center py-20 gap-4">
          <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary-500 border-t-transparent" />
          <p className="text-sm text-surface-500">
            {timedOut ? 'Sessao expirada. Redirecionando...' : 'Carregando portal...'}
          </p>
        </div>
      </PortalLayout>
    )
  }
  return <PortalLayout><ErrorBoundary>{children}</ErrorBoundary></PortalLayout>
}

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <TooltipProvider delayDuration={300}>
        <BrowserRouter>
          <Toaster position="top-right" richColors closeButton duration={4000} />
          <CommandPalette />
          <Suspense fallback={<PageLoader />}>
            <Routes>
              {/* Rotas públicas */}
              <Route
                path="/login"
                element={
                  <GuestRoute>
                    <LoginPage />
                  </GuestRoute>
                }
              />
              <Route
                path="/esqueci-senha"
                element={
                  <GuestRoute>
                    <ForgotPasswordPage />
                  </GuestRoute>
                }
              />
              <Route
                path="/redefinir-senha"
                element={
                  <GuestRoute>
                    <ResetPasswordPage />
                  </GuestRoute>
                }
              />
              <Route path="/quotes/proposal/:magicToken" element={<QuotePublicApprovalPage />} />

              {/* Rotas autenticadas — layout único (não re-monta a cada navegação) */}
              <Route element={<ProtectedLayout />}>
                <Route path="/" element={<TechAutoRedirect><DashboardPage /></TechAutoRedirect>} />
                <Route path="/agenda" element={<AgendaPage />} />
                <Route path="/agenda/kanban" element={<AgendaKanbanPage />} />
                <Route path="/agenda/dashboard" element={<AgendaDashboardPage />} />
                <Route path="/agenda/regras" element={<AgendaRulesPage />} />
                <Route path="/iam/Roles" element={<RolesPage />} /> // Keeping this just in case, but using lowercase below
                <Route path="/iam/usuarios" element={<UsersPage />} />
                <Route path="/iam/roles" element={<RolesPage />} />
                <Route path="/iam/permissoes" element={<PermissionsMatrixPage />} />
                <Route path="/iam/auditoria" element={<AuditLogPage />} />

                <Route path="/cadastros/clientes" element={<CustomersPage />} />
                <Route path="/cadastros/clientes/fusao" element={<CustomerMergePage />} />
                <Route path="/cadastros/clientes/:id" element={<Customer360Page />} />
                <Route path="/cadastros/produtos" element={<ProductsPage />} />
                <Route path="/cadastros/servicos" element={<ServicesPage />} />
                <Route path="/cadastros/historico-precos" element={<PriceHistoryPage />} />
                <Route path="/cadastros/exportacao-lote" element={<BatchExportPage />} />
                <Route path="/cadastros/fornecedores" element={<SuppliersPage />} />
                <Route path="/catalogo" element={<CatalogAdminPage />} />
                <Route path="/orcamentos" element={<QuotesListPage />} />
                <Route path="/orcamentos/dashboard" element={<QuotesDashboardPage />} />
                <Route path="/orcamentos/novo" element={<QuoteCreatePage />} />
                <Route path="/orcamentos/:id" element={<QuoteDetailPage />} />
                <Route path="/orcamentos/:id/editar" element={<QuoteEditPage />} />
                <Route path="/chamados" element={<ServiceCallsPage />} />
                <Route path="/chamados/novo" element={<ServiceCallCreatePage />} />
                <Route path="/chamados/kanban" element={<ServiceCallKanbanPage />} />
                <Route path="/chamados/dashboard" element={<ServiceCallDashboardPage />} />
                <Route path="/chamados/mapa" element={<ServiceCallMapPage />} />
                <Route path="/chamados/agenda" element={<TechnicianAgendaPage />} />
                <Route path="/chamados/:id" element={<ServiceCallDetailPage />} />
                <Route path="/chamados/:id/editar" element={<ServiceCallEditPage />} />
                <Route path="/os" element={<WorkOrdersListPage />} />
                <Route path="/os/kanban" element={<WorkOrderKanbanPage />} />
                <Route path="/os/nova" element={<WorkOrderCreatePage />} />
                <Route path="/os/agenda" element={<OSCalendarPage />} />
                <Route path="/os/mapa" element={<WorkOrderMapPage />} />
                <Route path="/os/dashboard" element={<WorkOrderDashboardPage />} />
                <Route path="/os/:id" element={<WorkOrderDetailPage />} />
                <Route path="/os/contratos-recorrentes" element={<RecurringContractsPage />} />
                <Route path="/os/sla" element={<SlaPoliciesPage />} />
                <Route path="/os/sla-dashboard" element={<SlaDashboardPage />} />
                <Route path="/os/templates" element={<WorkOrderTemplatesPage />} />
                <Route path="/os/checklists" element={<ChecklistPage />} />
                <Route path="/os/checklists-servico" element={<ServiceChecklistsPage />} />
                <Route path="/os/kits-pecas" element={<PartsKitsPage />} />
                <Route path="/tecnicos/agenda" element={<SchedulesPage />} />
                <Route path="/tecnicos/apontamentos" element={<TimeEntriesPage />} />
                <Route path="/tecnicos/caixa" element={<TechnicianCashPage />} />
                <Route path="/financeiro" element={<FinanceiroDashboardPage />} />
                <Route path="/financeiro/receber" element={<AccountsReceivablePage />} />
                <Route path="/financeiro/pagar" element={<AccountsPayablePage />} />
                <Route path="/financeiro/comissoes" element={<CommissionsPage />} />
                <Route path="/financeiro/comissoes/dashboard" element={<CommissionDashboardPage />} />
                <Route path="/financeiro/despesas" element={<ExpensesPage />} />
                <Route path="/financeiro/abastecimento" element={<FuelingLogsPage />} />
                <Route path="/financeiro/pagamentos" element={<PaymentsPage />} />
                <Route path="/financeiro/formas-pagamento" element={<PaymentMethodsPage />} />
                <Route path="/financeiro/fluxo-caixa" element={<CashFlowPage />} />
                <Route path="/financeiro/fluxo-caixa-semanal" element={<CashFlowWeeklyDashboardPage />} />
                <Route path="/financeiro/faturamento" element={<InvoicesPage />} />
                <Route path="/financeiro/conciliacao-bancaria" element={<BankReconciliationPage />} />
                <Route path="/financeiro/regras-conciliacao" element={<ReconciliationRulesPage />} />
                <Route path="/financeiro/dashboard-conciliacao" element={<ReconciliationDashboardPage />} />
                <Route path="/financeiro/consolidado" element={<ConsolidatedFinancialPage />} />
                <Route path="/financeiro/plano-contas" element={<ChartOfAccountsPage />} />
                <Route path="/financeiro/categorias-pagar" element={<AccountPayableCategoriesPage />} />
                <Route path="/financeiro/contas-bancarias" element={<BankAccountsPage />} />
                <Route path="/financeiro/transferencias-tecnicos" element={<FundTransfersPage />} />
                <Route path="/fiscal" element={<FiscalDashboardPage />} />
                <Route path="/fiscal/notas" element={<FiscalNotesPage />} />
                <Route path="/fiscal/configuracoes" element={<FiscalConfigPage />} />
                <Route path="/estoque" element={<StockDashboardPage />} />
                <Route path="/estoque/armazens" element={<WarehousesPage />} />
                <Route path="/estoque/movimentacoes" element={<StockMovementsPage />} />
                <Route path="/estoque/lotes" element={<BatchManagementPage />} />
                <Route path="/estoque/inventarios" element={<InventoryListPage />} />
                <Route path="/estoque/inventarios/novo" element={<InventoryCreatePage />} />
                <Route path="/estoque/inventarios/:id" element={<InventoryExecutionPage />} />
                <Route path="/estoque/inventario-pwa" element={<InventoryPwaListPage />} />
                <Route path="/estoque/inventario-pwa/:warehouseId" element={<InventoryPwaCountPage />} />
                <Route path="/estoque/movimentar-qr" element={<QrInventoryScanPage />} />
                <Route path="/estoque/kardex" element={<KardexPage />} />
                <Route path="/estoque/calibracoes-ferramentas" element={<ToolCalibrationsPage />} />
                <Route path="/estoque/inteligencia" element={<StockIntelligencePage />} />
                <Route path="/estoque/etiquetas" element={<StockLabelsPage />} />
                <Route path="/estoque/integracao" element={<StockIntegrationPage />} />
                <Route path="/estoque/transferencias" element={<StockTransfersPage />} />
                <Route path="/estoque/pecas-usadas" element={<UsedStockItemsPage />} />
                <Route path="/estoque/numeros-serie" element={<SerialNumbersPage />} />
                <Route path="/relatorios" element={<ReportsPage />} />
                <Route path="/analytics" element={<AnalyticsHubPage />} />
                <Route path="/notificacoes" element={<NotificationsPage />} />
                <Route path="/importacao" element={<ImportPage />} />
                <Route path="/integracao/auvo" element={<AuvoImportPage />} />
                <Route path="/emails" element={<EmailInboxPage />} />
                <Route path="/emails/compose" element={<EmailComposePage />} />
                <Route path="/emails/configuracoes" element={<EmailSettingsPage />} />
                <Route path="/inmetro" element={<InmetroDashboardPage />} />
                <Route path="/inmetro/leads" element={<InmetroLeadsPage />} />
                <Route path="/inmetro/instrumentos" element={<InmetroInstrumentsPage />} />
                <Route path="/inmetro/importacao" element={<InmetroImportPage />} />
                <Route path="/inmetro/concorrentes" element={<InmetroCompetitorPage />} />
                <Route path="/inmetro/owners/:id" element={<InmetroOwnerDetailPage />} />
                <Route path="/inmetro/mapa" element={<InmetroMapPage />} />
                <Route path="/inmetro/mercado" element={<InmetroMarketPage />} />
                <Route path="/inmetro/prospeccao" element={<InmetroProspectionPage />} />
                <Route path="/inmetro/executivo" element={<InmetroExecutivePage />} />
                <Route path="/inmetro/compliance" element={<InmetroCompliancePage />} />
                <Route path="/inmetro/webhooks" element={<InmetroWebhooksPage />} />
                <Route path="/inmetro/selos" element={<InmetroSealManagement />} />
                <Route path="/inmetro/relatorio-selos" element={<InmetroSealReportPage />} />
                <Route path="/equipamentos" element={<EquipmentListPage />} />
                <Route path="/equipamentos/modelos" element={<EquipmentModelsPage />} />
                <Route path="/equipamentos/novo" element={<EquipmentCreatePage />} />
                <Route path="/equipamentos/pesos-padrao" element={<StandardWeightsPage />} />
                <Route path="/equipamentos/atribuicao-pesos" element={<WeightAssignmentsPage />} />
                <Route path="/equipamentos/:id" element={<EquipmentDetailPage />} />
                <Route path="/equipamentos/:id/editar" element={<EquipmentEditPage />} />
                <Route path="/agenda-calibracoes" element={<EquipmentCalendarPage />} />
                <Route path="/configuracoes" element={<SettingsPage />} />
                <Route path="/configuracoes/cadastros-auxiliares" element={<LookupsPage />} />
                <Route path="/configuracoes/filiais" element={<BranchesPage />} />
                <Route path="/configuracoes/empresas" element={<TenantManagementPage />} />
                <Route path="/configuracoes/auditoria" element={<AuditLogPage />} />
                <Route path="/configuracoes/logs-auditoria" element={<Navigate to="/configuracoes/auditoria" replace />} />
                <Route path="/configuracoes/observabilidade" element={<ObservabilityDashboardPage />} />
                <Route path="/configuracoes/whatsapp" element={<WhatsAppConfigPage />} />
                <Route path="/configuracoes/whatsapp/logs" element={<WhatsAppLogPage />} />
                <Route path="/configuracoes/google-calendar" element={<GoogleCalendarSyncPage />} />
                <Route path="/configuracoes/limites-despesas" element={<ExpenseLimitsConfigPage />} />
                <Route path="/perfil" element={<ProfilePage />} />
                <Route path="/crm" element={<CrmDashboardPage />} />
                <Route path="/crm/pipeline/:id" element={<CrmPipelinePage />} />
                <Route path="/crm/pipeline" element={<CrmPipelinePage />} />
                <Route path="/crm/clientes/:id" element={<Customer360Page />} />
                <Route path="/crm/templates" element={<MessageTemplatesPage />} />
                <Route path="/crm/forecast" element={<CrmForecastPage />} />
                <Route path="/crm/goals" element={<CrmGoalsPage />} />
                <Route path="/crm/alerts" element={<CrmAlertsPage />} />
                <Route path="/crm/calendar" element={<CrmCalendarPage />} />
                <Route path="/crm/scoring" element={<CrmScoringPage />} />
                <Route path="/crm/sequences" element={<CrmSequencesPage />} />
                <Route path="/crm/loss-analytics" element={<CrmLossAnalyticsPage />} />
                <Route path="/crm/territories" element={<CrmTerritoriesPage />} />
                <Route path="/crm/renewals" element={<CrmRenewalsPage />} />
                <Route path="/crm/referrals" element={<CrmReferralsPage />} />
                <Route path="/crm/web-forms" element={<CrmWebFormsPage />} />
                <Route path="/crm/revenue" element={<CrmRevenueIntelligencePage />} />
                <Route path="/crm/competitors" element={<CrmCompetitorsPage />} />
                <Route path="/crm/velocity" element={<CrmVelocityPage />} />
                <Route path="/crm/cohort" element={<CrmCohortPage />} />
                <Route path="/crm/proposals" element={<CrmProposalsPage />} />
                <Route path="/crm/visit-checkins" element={<CrmVisitCheckinsPage />} />
                <Route path="/crm/visit-routes" element={<CrmVisitRoutesPage />} />
                <Route path="/crm/visit-reports" element={<CrmVisitReportsPage />} />
                <Route path="/crm/portfolio-map" element={<CrmPortfolioMapPage />} />
                <Route path="/crm/forgotten-clients" element={<CrmForgottenClientsPage />} />
                <Route path="/crm/contact-policies" element={<CrmContactPoliciesPage />} />
                <Route path="/crm/smart-agenda" element={<CrmSmartAgendaPage />} />
                <Route path="/crm/post-visit-workflow" element={<CrmPostVisitWorkflowPage />} />
                <Route path="/crm/quick-notes" element={<CrmQuickNotesPage />} />
                <Route path="/crm/commitments" element={<CrmCommitmentsPage />} />
                <Route path="/crm/negotiation-history" element={<CrmNegotiationHistoryPage />} />
                <Route path="/crm/client-summary" element={<CrmClientSummaryPage />} />
                <Route path="/crm/rfm" element={<CrmRfmPage />} />
                <Route path="/crm/coverage" element={<CrmCoveragePage />} />
                <Route path="/crm/productivity" element={<CrmProductivityPage />} />
                <Route path="/crm/opportunities" element={<CrmOpportunitiesPage />} />
                <Route path="/crm/important-dates" element={<CrmImportantDatesPage />} />
                <Route path="/crm/visit-surveys" element={<CrmVisitSurveysPage />} />
                <Route path="/crm/account-plans" element={<CrmAccountPlansPage />} />
                <Route path="/crm/gamification" element={<CrmGamificationPage />} />
                <Route path="/crm/nps" element={<CrmNpsDashboardPage />} />
                <Route path="/contratos" element={<ContractsPage />} />
                <Route path="/frota" element={<FleetPage />} />
                <Route path="/rh" element={<HRPage />} />
                <Route path="/rh/ponto" element={<ClockInPage />} />
                <Route path="/rh/espelho" element={<EspelhoPontoPage />} />
                <Route path="/rh/geofences" element={<GeofenceLocationsPage />} />
                <Route path="/rh/ajustes-ponto" element={<ClockAdjustmentsPage />} />
                <Route path="/rh/jornada" element={<JourneyPage />} />
                <Route path="/rh/jornada/regras" element={<JourneyRulesPage />} />
                <Route path="/rh/jornada/timeline" element={<JourneyDayPage />} />
                <Route path="/rh/jornada/politicas" element={<JourneyPoliciesPage />} />
                <Route path="/rh/jornada/aprovacoes" element={<JourneyApprovalPage />} />
                <Route path="/rh/viagens" element={<TravelRequestsPage />} />
                <Route path="/rh/certificacoes" element={<CertificationsPage />} />
                <Route path="/rh/consentimento-biometrico" element={<BiometricConsentPage />} />
                <Route path="/rh/folha-jornada" element={<PayrollJourneyPage />} />
                <Route path="/rh/banco-horas" element={<HourBankDetailPage />} />
                <Route path="/rh/banco-horas" element={<HourBankPage />} />
                <Route path="/rh/feriados" element={<HolidaysPage />} />
                <Route path="/rh/licencas" element={<LeavesManagementPage />} />
                <Route path="/rh/fiscal" element={<FiscalCompliancePage />} />
                <Route path="/rh/ferias" element={<LeavesPage />} />
                <Route path="/rh/saldo-ferias" element={<VacationBalancePage />} />
                <Route path="/rh/documentos" element={<EmployeeDocumentsPage />} />
                <Route path="/rh/onboarding" element={<OnboardingPage />} />
                <Route path="/rh/organograma" element={<OrgChartPage />} />
                <Route path="/rh/skills" element={<SkillsMatrixPage />} />
                <Route path="/rh/desempenho" element={<PerformancePage />} />
                <Route path="/rh/desempenho/:id" element={<PerformanceReviewDetailPage />} />
                <Route path="/rh/beneficios" element={<BenefitsPage />} />
                <Route path="/rh/folha" element={<PayrollPage />} />
                <Route path="/rh/rescisoes" element={<RescissionPage />} />
                <Route path="/rh/holerites" element={<PayslipPage />} />
                <Route path="/rh/recrutamento" element={<RecruitmentPage />} />
                <Route path="/rh/recrutamento/:id" element={<RecruitmentKanbanPage />} />
                <Route path="/rh/esocial" element={<ESocialPage />} />
                <Route path="/rh/tabelas-fiscais" element={<TaxTablesPage />} />
                <Route path="/rh/audit-trail" element={<AuditTrailPage />} />
                <Route path="/rh/analytics" element={<PeopleAnalyticsPage />} />
                <Route path="/rh/relatorios" element={<AccountingReportsPage />} />
                <Route path="/rh/violacoes-clt" element={<CltViolationsPage />} />
                <Route path="/qualidade" element={<QualityPage />} />
                <Route path="/qualidade/auditorias" element={<QualityAuditsPage />} />
                <Route path="/qualidade/auditorias/:id" element={<QualityAuditDetailPage />} />
                <Route path="/qualidade/documentos" element={<DocumentVersionsPage />} />
                <Route path="/qualidade/revisao-direcao" element={<ManagementReviewPage />} />
                <Route path="/operacional" element={<OperationalDashboardPage />} />
                <Route path="/alertas" element={<AlertsPage />} />
                <Route path="/calibracoes" element={<CalibrationListPage />} />
                <Route path="/calibracao/leituras" element={<CalibrationReadingsPage />} />
                <Route path="/calibracao/:calibrationId/leituras" element={<CalibrationReadingsPage />} />
                <Route path="/calibracao/wizard/:equipmentId" element={<CalibrationWizardPage />} />
                <Route path="/calibracao/wizard/:equipmentId/:calibrationId" element={<CalibrationWizardPage />} />
                <Route path="/calibracao/templates" element={<CertificateTemplatesPage />} />
                <Route path="/financeiro/regua-cobranca" element={<AgingReceivablesPage />} />
                <Route path="/financeiro/cobranca-automatica" element={<CollectionAutomationPage />} />
                <Route path="/financeiro/renegociacao" element={<DebtRenegotiationPage />} />
                <Route path="/financeiro/reembolsos" element={<ExpenseReimbursementsPage />} />
                <Route path="/financeiro/cheques" element={<FinancialChecksPage />} />
                <Route path="/financeiro/contratos-fornecedores" element={<SupplierContractsPage />} />
                <Route path="/financeiro/adiantamentos-fornecedores" element={<SupplierAdvancesPage />} />
                <Route path="/financeiro/simulador-recebiveis" element={<ReceivablesSimulatorPage />} />
                <Route path="/financeiro/aprovacao-lote" element={<BatchPaymentApprovalPage />} />
                <Route path="/financeiro/alocacao-despesas" element={<ExpenseAllocationPage />} />
                <Route path="/financeiro/calculadora-tributos" element={<TaxCalculatorPage />} />
                <Route path="/financeiro/dre" element={<DrePage />} />
                <Route path="/financeiro/ativos" element={<FixedAssetsPage />} />
                <Route path="/financeiro/ativos/dashboard" element={<FixedAssetsDashboardPage />} />
                <Route path="/financeiro/ativos/depreciacao" element={<FixedAssetsDepreciationPage />} />
                <Route path="/financeiro/ativos/movimentacoes" element={<FixedAssetsMovementsPage />} />
                <Route path="/financeiro/ativos/inventario" element={<FixedAssetsInventoryPage />} />
                <Route path="/financeiro/ativos/relatorios" element={<FixedAssetsReportsPage />} />
                <Route path="/projetos" element={<ProjectsPage />} />
                <Route path="/automacao" element={<AutomationPage />} />
                <Route path="/avancado" element={<AdvancedFeaturesPage />} />
                <Route path="/ia" element={<AIAnalyticsPage />} />
                <Route path="/ceo-cockpit" element={<CeoCockpitPage />} />
                <Route path="/tv/dashboard" element={<TvDashboard />} />
                <Route path="/tv/cameras" element={<TvCamerasPage />} />
                <Route path="/laboratorio" element={<LabAdvancedPage />} />
                <Route path="/seguranca" element={<SecurityPage />} />
                <Route path="/vendas/avancado" element={<SalesAdvancedPage />} />
                <Route path="/financeiro/recibos" element={<PaymentReceiptsPage />} />
                <Route path="/rh/escalas" element={<WorkSchedulesPage />} />
                <Route path="/equipamentos/manutencoes" element={<EquipmentMaintenancePage />} />
                <Route path="/share" element={<ShareTargetPage />} />
                <Route path="*" element={<NotFoundPage />} />
              </Route>

              {/* Tech PWA (Mobile Offline) */}
              <Route path="/tech" element={<TechShell />}>
                <Route index element={<TechWorkOrdersPage />} />
                <Route path="os/:id" element={<TechWorkOrderDetailPage />} />
                <Route path="os/:id/checklist" element={<TechChecklistPage />} />
                <Route path="os/:id/expenses" element={<TechExpensePage />} />
                <Route path="os/:id/photos" element={<TechPhotosPage />} />
                <Route path="os/:id/seals" element={<TechSealsPage />} />
                <Route path="os/:id/calibration" element={<TechCalibrationReadingsPage />} />
                <Route path="os/:id/certificado" element={<TechCertificatePage />} />
                <Route path="os/:id/signature" element={<TechSignaturePage />} />
                <Route path="os/:id/nps" element={<TechNpsPage />} />
                <Route path="os/:id/ocorrencia" element={<TechComplaintPage />} />
                <Route path="os/:id/contrato" element={<TechContractInfoPage />} />
                <Route path="perfil" element={<TechProfilePage />} />
                <Route path="configuracoes" element={<TechSettingsPage />} />
                <Route path="barcode" element={<TechBarcodePage />} />
                <Route path="os/:id/chat" element={<TechChatPage />} />
                <Route path="os/:id/annotate" element={<TechPhotoAnnotationPage />} />
                <Route path="os/:id/voice-report" element={<TechVoiceReportPage />} />
                <Route path="os/:id/print" element={<TechBluetoothPrintPage />} />
                <Route path="thermal-camera" element={<TechThermalCameraPage />} />
                <Route path="thermal-camera/:id" element={<TechThermalCameraPage />} />
                <Route path="widget" element={<TechWidgetPage />} />
                <Route path="despesas" element={<TechExpensesOverviewPage />} />
                <Route path="caixa" element={<TechCashManagementPage />} />
                <Route path="nova-os" element={<TechCreateWorkOrderPage />} />
                <Route path="agenda" element={<TechSchedulePage />} />
                <Route path="rota" element={<TechRoutePage />} />
                <Route path="mapa" element={<TechMapViewPage />} />
                <Route path="comissoes" element={<TechCommissionsPage />} />
                <Route path="resumo-diario" element={<TechDaySummaryPage />} />
                <Route path="apontamentos" element={<TechTimeEntriesPage />} />
                <Route path="notificacoes" element={<TechNotificationsPage />} />
                <Route path="equipamentos" element={<TechEquipmentSearchPage />} />
                <Route path="equipamento/:id" element={<TechEquipmentHistoryPage />} />
                <Route path="feedback" element={<TechFeedbackPage />} />
                <Route path="precos" element={<TechPriceTablePage />} />
                <Route path="orcamento-rapido" element={<TechQuickQuotePage />} />
                <Route path="scan-ativos" element={<TechAssetScanPage />} />
                <Route path="veiculo" element={<TechVehicleCheckinPage />} />
                <Route path="ferramentas" element={<TechToolInventoryPage />} />
                <Route path="estoque" element={<TechVanStockPage />} />
                <Route path="ponto" element={<TechTimeClockPage />} />
                <Route path="banco-horas" element={<TechHourBankPage />} />
                <Route path="correcao-ponto" element={<TechClockCorrectionPage />} />
                <Route path="jornada" element={<TechJourneyPage />} />
                <Route path="dashboard" element={<TechDashboardPage />} />
                <Route path="metas" element={<TechGoalsPage />} />
                <Route path="central" element={<TechAgendaPage />} />
                <Route path="chamados" element={<TechServiceCallsPage />} />
                <Route path="solicitar-material" element={<TechMaterialRequestPage />} />
                <Route path="inventory-scan" element={<QrInventoryScanPage />} />
              </Route>

              {/* Rotas do Portal do Cliente */}
              <Route path="/portal/login" element={<PortalLoginPage />} />
              <Route path="/portal" element={<ProtectedPortalRoute><PortalDashboardPage /></ProtectedPortalRoute>} />
              <Route path="/portal/os" element={<ProtectedPortalRoute><PortalWorkOrdersPage /></ProtectedPortalRoute>} />
              <Route path="/portal/os/:id" element={<ProtectedPortalRoute><PortalWorkOrderDetailPage /></ProtectedPortalRoute>} />
              <Route path="/portal/orcamentos" element={<ProtectedPortalRoute><PortalQuotesPage /></ProtectedPortalRoute>} />
              <Route path="/portal/financeiro" element={<ProtectedPortalRoute><PortalFinancialsPage /></ProtectedPortalRoute>} />
              <Route path="/portal/chamados/novo" element={<ProtectedPortalRoute><PortalServiceCallPage /></ProtectedPortalRoute>} />
              <Route path="/portal/tickets" element={<ProtectedPortalRoute><PortalTicketsPage /></ProtectedPortalRoute>} />
              <Route path="/portal/certificados" element={<ProtectedPortalRoute><PortalCertificatesPage /></ProtectedPortalRoute>} />
              <Route path="/portal/equipamentos" element={<ProtectedPortalRoute><PortalEquipmentPage /></ProtectedPortalRoute>} />

              {/* Página pública QR Code Equipamento */}
              <Route path="/equipamento-qr/:token" element={<EquipmentQrPublicPage />} />

              {/* Catálogo público — link compartilhável com clientes */}
              <Route path="/catalogo/:slug" element={<CatalogPublicPage />} />

              {/* Fallback para URLs não encontradas */}
              <Route path="*" element={<NotFoundPage />} />
            </Routes>
          </Suspense>
        </BrowserRouter>
      </TooltipProvider>
      <QueryDevtoolsLoader />
    </QueryClientProvider>
  )
}
