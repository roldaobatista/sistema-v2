import { useState, useRef } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { useDarkMode } from '@/hooks/useDarkMode'
import { AppBreadcrumb } from './AppBreadcrumb'
import {
    LayoutDashboard, Users, FileText, Wrench, DollarSign, BarChart3, Settings, List,
    Shield, ChevronLeft, ChevronRight, LogOut, Menu, X, Building2, Package,
    Briefcase, KeyRound, Grid3x3, Calendar, Clock, ArrowDownToLine, ArrowUpFromLine,
    Award, Receipt, WifiOff, Download, Phone, Upload, Truck, Scale,
    Weight, RotateCcw, TrendingUp, History, Warehouse, ArrowLeftRight, Bell,
    CheckSquare, Inbox, Heart, Zap, Search, Moon, Sun, Star, ClipboardCheck,
    MapPinned, BookOpen, ScrollText, QrCode, Network, User, BarChart,
    Monitor, Target, Crosshair, AlertTriangle, Share2, Gauge, Repeat, Trophy, Calculator,
    GitBranch, PieChart, Swords, Globe, Eye, Video,
    MapPin, Lightbulb, ShieldCheck, Route,
    UserX, Medal, Printer, Sliders, UserPlus, Brain, FileSignature, Landmark, Coins, FolderKanban, Activity,
} from 'lucide-react'
import { cn } from '@/lib/utils'
import { useAuthStore } from '@/stores/auth-store'
import { useUIStore } from '@/stores/ui-store'
import { usePWA } from '@/hooks/usePWA'
import { useAppMode } from '@/hooks/useAppMode'
import { useCurrentTenant as useTenantHook } from '@/hooks/useCurrentTenant'
import NotificationPanel from '@/components/notifications/NotificationPanel'
import { QuickReminderButton } from '@/components/agenda/QuickReminderButton'
import OfflineIndicator from '@/components/pwa/OfflineIndicator'
import { ModeSwitcher } from '@/components/pwa/ModeSwitcher'
import { InstallBanner } from '@/components/pwa/InstallBanner'
import { UpdateBanner } from '@/components/pwa/UpdateBanner'
import { SyncStatusPanel } from '@/components/pwa/SyncStatusPanel'
import { NetworkBadge } from '@/components/pwa/NetworkBadge'
import { TeamStatusWidget } from '@/components/pwa/TeamStatusWidget'
import { usePrefetchCriticalData } from '@/hooks/usePrefetchCriticalData'
import { useSwipeGesture } from '@/hooks/useSwipeGesture'
import { useCrossTabSync } from '@/hooks/useCrossTabSync'

interface NavItem {
    label: string
    icon: React.ElementType
    path: string
    permission?: string
    children?: Omit<NavItem, 'children'>[]
}

function hasPermissionExpression(expression: string, userPerms: string[]): boolean {
    return expression
        .split('|')
        .map(item => item.trim())
        .filter(Boolean)
        .some(permission => userPerms.includes(permission))
}

interface NavSection {
    label: string
    items: NavItem[]
}

const commissionsModuleViewPermission = 'commissions.rule.view|commissions.event.view|commissions.settlement.view|commissions.dispute.view|commissions.goal.view|commissions.campaign.view|commissions.recurring.view'

const navigationSections: NavSection[] = [
    {
        label: 'Workspace',
        items: [
            { label: 'Dashboard', icon: LayoutDashboard, path: '/' },
            {
                label: 'Agenda', icon: Calendar, path: '/agenda-group', permission: 'agenda.item.view',
                children: [
                    { label: 'Inbox', icon: Inbox, path: '/agenda' },
                    { label: 'Dashboard', icon: BarChart3, path: '/agenda/dashboard', permission: 'agenda.manage.kpis' },
                    { label: 'Automação', icon: Zap, path: '/agenda/regras', permission: 'agenda.manage.rules' },
                ],
            },
            { label: 'Notificações', icon: Bell, path: '/notificacoes', permission: 'notifications.notification.view' },
        ],
    },
    {
        label: 'Comercial & Vendas',
        items: [
            {
                label: 'Gestão CRM', icon: Briefcase, path: '/crm-gestao', permission: 'crm.deal.view',
                children: [
                    { label: 'Dashboard', icon: BarChart3, path: '/crm' },
                    { label: 'Pipeline', icon: Grid3x3, path: '/crm/pipeline' },
                    { label: 'Propostas', icon: Eye, path: '/crm/proposals', permission: 'crm.proposal.view' },
                    { label: 'Templates', icon: FileText, path: '/crm/templates' },
                    { label: 'Metas', icon: Trophy, path: '/crm/goals', permission: 'crm.goal.view' },
                    { label: 'Calendário', icon: Calendar, path: '/crm/calendar' },
                    { label: 'Alertas', icon: AlertTriangle, path: '/crm/alerts' },
                ]
            },
            {
                label: 'Inteligência', icon: Target, path: '/crm-inteligencia', permission: 'crm.deal.view',
                children: [
                    { label: 'Lead Scoring', icon: Crosshair, path: '/crm/scoring', permission: 'crm.scoring.view' },
                    { label: 'Previsão (Forecast)', icon: TrendingUp, path: '/crm/forecast', permission: 'crm.forecast.view' },
                    { label: 'Receita', icon: DollarSign, path: '/crm/revenue', permission: 'crm.forecast.view' },
                    { label: 'Coorte', icon: BarChart, path: '/crm/cohort', permission: 'crm.forecast.view' },
                    { label: 'Análise Perdas', icon: PieChart, path: '/crm/loss-analytics' },
                    { label: 'Concorrentes', icon: Swords, path: '/crm/competitors' },
                    { label: 'Velocidade', icon: Gauge, path: '/crm/velocity' },
                    { label: 'RFM', icon: BarChart, path: '/crm/rfm' },
                    { label: 'Produtividade', icon: TrendingUp, path: '/crm/productivity' },
                ]
            },
            {
                label: 'Carteira & Campo', icon: MapPinned, path: '/crm-campo', permission: 'crm.territory.view|crm.deal.view',
                children: [
                    { label: 'Oportunidades', icon: Lightbulb, path: '/crm/opportunities' },
                    { label: 'Territórios', icon: MapPinned, path: '/crm/territories', permission: 'crm.territory.view' },
                    { label: 'Cobertura Carteira', icon: Target, path: '/crm/coverage' },
                    { label: 'Roteiros & Atas', icon: Route, path: '/crm/visit-routes' },
                    { label: 'Visitas (Check-in)', icon: MapPin, path: '/crm/visit-checkins' },
                    { label: 'Clientes Esquecidos', icon: UserX, path: '/crm/forgotten-clients' },
                    { label: 'Planos de Ação', icon: Target, path: '/crm/account-plans' },
                    { label: 'Renovações', icon: Repeat, path: '/crm/renewals', permission: 'crm.renewal.view' },
                ]
            },
            {
                label: 'Engajamento', icon: Share2, path: '/crm-engajamento', permission: 'crm.deal.view',
                children: [
                    { label: 'Cadências', icon: GitBranch, path: '/crm/sequences', permission: 'crm.sequence.view' },
                    { label: 'Formulários', icon: Globe, path: '/crm/web-forms', permission: 'crm.form.view' },
                    { label: 'Indicações', icon: Share2, path: '/crm/referrals', permission: 'crm.referral.view' },
                    { label: 'Gamificação', icon: Medal, path: '/crm/gamification' },
                    { label: 'Ficha Cliente', icon: User, path: '/crm/client-summary' },
                    { label: 'Histórico', icon: History, path: '/crm/negotiation-history' },
                ]
            },
            {
                label: 'Orçamentos', icon: FileText, path: '/orcamentos', permission: 'quotes.quote.view',
                children: [
                    { label: 'Lista', icon: FileText, path: '/orcamentos' },
                    { label: 'Dashboard', icon: BarChart3, path: '/orcamentos/dashboard' },
                ],
            },
        ],
    },
    {
        label: 'Operacional & Field Service',
        items: [
            {
                label: 'Painel Operacional', icon: Activity, path: '/operacional', permission: 'os.work_order.view',
            },
            {
                label: 'Centro O.S.', icon: Wrench, path: '/os-centro', permission: 'os.work_order.view',
                children: [
                    { label: 'Lista', icon: FileText, path: '/os' },
                    { label: 'Kanban', icon: Grid3x3, path: '/os/kanban' },
                    { label: 'Agenda', icon: Calendar, path: '/os/agenda' },
                    { label: 'SLA Dashboard', icon: BarChart3, path: '/os/sla-dashboard' },
                ]
            },
            {
                label: 'Apoio de Campo', icon: Truck, path: '/os-campo', permission: 'os.work_order.view',
                children: [
                    { label: 'Mapa', icon: MapPin, path: '/os/mapa' },
                    { label: 'Técnicos (Agenda)', icon: Calendar, path: '/tecnicos/agenda', permission: 'technicians.schedule.view' },
                    { label: 'Apontamentos', icon: Clock, path: '/tecnicos/apontamentos', permission: 'technicians.time_entry.view' },
                    { label: 'Frota', icon: Truck, path: '/frota', permission: 'fleet.vehicle.view' },
                ]
            },
            {
                label: 'Chamados', icon: Phone, path: '/chamados', permission: 'service_calls.service_call.view',
                children: [
                    { label: 'Lista', icon: FileText, path: '/chamados' },
                    { label: 'Mapa', icon: Scale, path: '/chamados/mapa' },
                    { label: 'Agenda', icon: Calendar, path: '/chamados/agenda' },
                ],
            },
            {
                label: 'Projetos', icon: FolderKanban, path: '/projetos', permission: 'projects.project.view|projects.dashboard.view'
            },
            {
                label: 'War Room (TV)', icon: Monitor, path: '/tv/dashboard', permission: 'tv.dashboard.view',
                children: [
                    { label: 'War Room', icon: Monitor, path: '/tv/dashboard' },
                    { label: 'Câmeras', icon: Video, path: '/tv/cameras', permission: 'tv.camera.manage' },
                ],
            },
        ],
    },
    {
        label: 'Cadastros & Estoque',
        items: [
            {
                label: 'Gestão de Estoque', icon: Warehouse, path: '/estoque-gestao', permission: 'estoque.movement.view',
                children: [
                    { label: 'Dashboard', icon: BarChart3, path: '/estoque' },
                    { label: 'Ponto de Retirada (Mov.)', icon: ArrowLeftRight, path: '/estoque/movimentacoes' },
                    { label: 'Armazéns', icon: Warehouse, path: '/estoque/armazens' },
                    { label: 'Transferências', icon: ArrowLeftRight, path: '/estoque/transferencias' },
                    { label: 'Meu inventário', icon: ClipboardCheck, path: '/estoque/inventario-pwa', permission: 'estoque.view' },
                    { label: 'Movimento via QR', icon: QrCode, path: '/estoque/movimentar-qr', permission: 'estoque.movement.create' },
                    { label: 'Peças Usadas', icon: Package, path: '/estoque/pecas-usadas' },
                ]
            },
            {
                label: 'Bens & Inventário', icon: Package, path: '/estoque-bens', permission: 'estoque.movement.view',
                children: [
                    { label: 'Inventário Físico', icon: ClipboardCheck, path: '/estoque/inventarios' },
                    { label: 'Kardex', icon: ScrollText, path: '/estoque/kardex' },
                    { label: 'Nº de Série', icon: ScrollText, path: '/estoque/numeros-serie' },
                    { label: 'Etiquetas', icon: Printer, path: '/estoque/etiquetas', permission: 'estoque.label.print' },
                ]
            },
            {
                label: 'Cadastros Gerais', icon: Package, path: '/cadastros', permission: 'cadastros.customer.view',
                children: [
                    { label: 'Clientes', icon: Users, path: '/cadastros/clientes' },
                    { label: 'Fornecedores', icon: Truck, path: '/cadastros/fornecedores', permission: 'cadastros.supplier.view' },
                    { label: 'Produtos', icon: Package, path: '/cadastros/produtos', permission: 'cadastros.product.view' },
                    { label: 'Serviços', icon: Briefcase, path: '/cadastros/servicos' },
                    { label: 'Catálogo Oficial', icon: BookOpen, path: '/catalogo', permission: 'catalog.view' },
                ],
            },
        ]
    },
    {
        label: 'Financeiro & Backoffice',
        items: [
            {
                label: 'Tesouraria', icon: DollarSign, path: '/financeiro/tesouraria',
                permission: `finance.receivable.view|finance.payable.view|finance.cashflow.view`,
                children: [
                    { label: 'Contas a Receber', icon: ArrowDownToLine, path: '/financeiro/receber', permission: 'finance.receivable.view' },
                    { label: 'Contas a Pagar', icon: ArrowUpFromLine, path: '/financeiro/pagar', permission: 'finance.payable.view' },
                    { label: 'Fluxo de Caixa', icon: BarChart3, path: '/financeiro/fluxo-caixa', permission: 'finance.cashflow.view' },
                    { label: 'Consolidado', icon: Building2, path: '/financeiro/consolidado', permission: 'finance.cashflow.view' },
                    { label: 'Contas Bancárias', icon: Building2, path: '/financeiro/contas-bancarias', permission: 'financial.bank_account.view' },
                ],
            },
            {
                label: 'Operações e Caixa', icon: Receipt, path: '/financeiro/operacoes',
                permission: `finance.receivable.view|finance.payable.view|expenses.expense.view`,
                children: [
                    { label: 'Pagamentos', icon: DollarSign, path: '/financeiro/pagamentos', permission: 'finance.receivable.view|finance.payable.view' },
                    { label: 'Faturamento', icon: FileText, path: '/financeiro/faturamento', permission: 'finance.receivable.view' },
                    { label: 'Despesas e Reembolsos', icon: Receipt, path: '/financeiro/despesas', permission: 'expenses.expense.view' },
                    { label: 'Cheques', icon: CheckSquare, path: '/financeiro/cheques', permission: 'finance.payable.view' },
                    { label: 'Recibos', icon: Receipt, path: '/financeiro/recibos', permission: 'finance.receivable.view|finance.payable.view' },
                    { label: 'Caixinha Técnicos', icon: ArrowLeftRight, path: '/tecnicos/caixa', permission: 'technicians.cashbox.view' },
                ]
            },
            {
                label: 'Cobrança e Crédito', icon: RotateCcw, path: '/financeiro/credito',
                permission: `finance.receivable.view|finance.renegotiation.view`,
                children: [
                    { label: 'Régua de Cobrança', icon: ArrowDownToLine, path: '/financeiro/regua-cobranca', permission: 'finance.receivable.view' },
                    { label: 'Renegociação', icon: RotateCcw, path: '/financeiro/renegociacao', permission: 'finance.renegotiation.view' },
                    { label: 'Simulador Recebíveis', icon: TrendingUp, path: '/financeiro/simulador-recebiveis', permission: 'finance.receivable.view' },
                ]
            },
            {
                label: 'Conciliação', icon: ArrowLeftRight, path: '/financeiro/conciliacao',
                permission: 'finance.receivable.view',
                children: [
                    { label: 'Conciliação Bancária', icon: ArrowLeftRight, path: '/financeiro/conciliacao-bancaria', permission: 'finance.receivable.view' },
                    { label: 'Dashboard Conciliação', icon: BarChart3, path: '/financeiro/dashboard-conciliacao', permission: 'finance.receivable.view' },
                ]
            },
            {
                label: 'Comissões', icon: Award, path: '/financeiro/comissoes',
                permission: commissionsModuleViewPermission,
                children: [
                    { label: 'Dashboard', icon: BarChart3, path: '/financeiro/comissoes/dashboard', permission: 'commissions.rule.view' },
                    { label: 'Apuração Comissões', icon: Award, path: '/financeiro/comissoes' },
                ]
            },
            {
                label: 'Controladoria & Fiscal', icon: Calculator, path: '/financeiro/controladoria',
                permission: 'finance.dre.view|finance.chart.view',
                children: [
                    { label: 'DRE', icon: BarChart3, path: '/financeiro/dre', permission: 'finance.dre.view' },
                    { label: 'Plano de Contas', icon: FileText, path: '/financeiro/plano-contas', permission: 'finance.chart.view' },
                    { label: 'Calculadora Tributos', icon: Calculator, path: '/financeiro/calculadora-tributos', permission: 'finance.dre.view' },
                    { label: 'Contratos Fornecedores', icon: ScrollText, path: '/financeiro/contratos-fornecedores', permission: 'finance.payable.view' },
                ]
            },
            {
                label: 'Ativos Imobilizados', icon: Landmark, path: '/financeiro/ativos',
                permission: 'fixed_assets.asset.view',
                children: [
                    { label: 'Dashboard Patrimonial', icon: Landmark, path: '/financeiro/ativos/dashboard', permission: 'fixed_assets.dashboard.view' },
                    { label: 'Inventário', icon: ClipboardCheck, path: '/financeiro/ativos/inventario', permission: 'fixed_assets.inventory.manage' },
                    { label: 'Depreciação', icon: Coins, path: '/financeiro/ativos/depreciacao', permission: 'fixed_assets.depreciation.view|fixed_assets.depreciation.run' },
                ]
            },
        ],
    },
    {
        label: 'Recursos Humanos',
        items: [
            { label: 'Visão Geral do RH', icon: Users, path: '/rh' },
            {
                label: 'Gestão de Ponto', icon: Clock, path: '/rh-ponto', permission: 'hr.clock.view',
                children: [
                    { label: 'Espelho de Ponto', icon: FileSignature, path: '/rh/espelho', permission: 'hr.clock.view' },
                    { label: 'Ajustes de Ponto', icon: Sliders, path: '/rh/ajustes-ponto', permission: 'hr.adjustment.view' },
                    { label: 'Jornada', icon: Calendar, path: '/rh/jornada', permission: 'hr.journey.view' },
                    { label: 'Geofences', icon: MapPinned, path: '/rh/geofences', permission: 'hr.geofence.view' },
                    { label: 'Exportação Fiscal', icon: ShieldCheck, path: '/rh/fiscal', permission: 'hr.fiscal.view' },
                ]
            },
            {
                label: 'Departamento Pessoal', icon: FileText, path: '/rh-dp', permission: 'hr.leave.view|hr.payroll.view',
                children: [
                    { label: 'Folha de Pagamento', icon: DollarSign, path: '/rh/folha', permission: 'hr.payroll.view' },
                    { label: 'Meus Holerites', icon: Receipt, path: '/rh/holerites' },
                    { label: 'Férias/Licenças', icon: Sun, path: '/rh/ferias', permission: 'hr.leave.view' },
                    { label: 'Rescisões', icon: UserX, path: '/rh/rescisoes', permission: 'hr.payroll.view' },
                    { label: 'eSocial', icon: Globe, path: '/rh/esocial', permission: 'hr.esocial.view' },
                ]
            },
            {
                label: 'Talentos e Cultura', icon: Brain, path: '/rh-talentos', permission: 'hr.performance.view|hr.recruitment.view',
                children: [
                    { label: 'Avaliações', icon: ClipboardCheck, path: '/rh/desempenho', permission: 'hr.performance.view' },
                    { label: 'Skills Matrix', icon: Brain, path: '/rh/skills', permission: 'hr.skills.view' },
                    { label: 'Benefícios', icon: Heart, path: '/rh/beneficios', permission: 'hr.benefits.view' },
                    { label: 'Recrutamento', icon: Briefcase, path: '/rh/recrutamento', permission: 'hr.recruitment.view' },
                    { label: 'Onboarding', icon: UserPlus, path: '/rh/onboarding', permission: 'hr.onboarding.view' },
                    { label: 'Organograma', icon: Network, path: '/rh/organograma', permission: 'hr.organization.view' },
                ]
            },
        ],
    },
    {
        label: 'Qualidade & Metrologia',
        items: [
            {
                label: 'Equipamentos e Lab', icon: Scale, path: '/equipamentos', permission: 'equipments.equipment.view',
                children: [
                    { label: 'Equipamentos', icon: Scale, path: '/equipamentos' },
                    { label: 'Calibrações', icon: BookOpen, path: '/calibracoes', permission: 'calibration.reading.view' },
                    { label: 'Pesos Padrão', icon: Weight, path: '/equipamentos/pesos-padrao', permission: 'equipments.standard_weight.view' },
                    { label: 'Calib. Ferramentas', icon: Wrench, path: '/estoque/calibracoes-ferramentas', permission: 'calibration.tool.view' },
                ]
            },
            {
                label: 'Sistema da Qualidade (SGI)', icon: ClipboardCheck, path: '/qualidade', permission: 'quality.procedure.view',
                children: [
                    { label: 'SGI Dashboard', icon: ClipboardCheck, path: '/qualidade' },
                    { label: 'Auditorias', icon: Search, path: '/qualidade/auditorias', permission: 'quality.audit.view' },
                    { label: 'Documentos Pop/Inst', icon: FileText, path: '/qualidade/documentos', permission: 'quality.document.view' },
                    { label: 'Revisão pela direção', icon: Users, path: '/qualidade/revisao-direcao', permission: 'quality.management_review.view' },
                ]
            },
            {
                label: 'Gestão INMETRO', icon: Shield, path: '/inmetro', permission: 'inmetro.intelligence.view',
                children: [
                    { label: 'Instrumentos INMETRO', icon: Scale, path: '/inmetro/instrumentos' },
                    { label: 'Mapa Inmetro', icon: Search, path: '/inmetro/mapa' },
                    { label: 'Importação RF', icon: Upload, path: '/inmetro/importacao' },
                ]
            },
        ],
    },
    {
        label: 'Business Intelligence',
        items: [
            { label: 'Analytics Hub', icon: BarChart3, path: '/analytics', permission: 'reports.analytics.view' },
            { label: 'Relatórios Legados', icon: FileText, path: '/relatorios', permission: 'reports.os_report.view' },
        ],
    },
    {
        label: 'Configurações de Acesso',
        items: [
            {
                label: 'Gestão IAM', icon: Shield, path: '/iam', permission: 'iam.user.view',
                children: [
                    { label: 'Usuários', icon: Users, path: '/iam/usuarios' },
                    { label: 'Roles', icon: KeyRound, path: '/iam/roles', permission: 'iam.role.view' },
                    { label: 'Permissões', icon: Grid3x3, path: '/iam/permissoes', permission: 'iam.role.view' },
                ]
            },
            {
                label: 'Configurações', icon: Settings, path: '/configuracoes', permission: 'platform.settings.view',
                children: [
                    { label: 'Filiais e Matriz', icon: Building2, path: '/configuracoes/filiais', permission: 'platform.branch.view' },
                    { label: 'Empresas (Tenants)', icon: Building2, path: '/configuracoes/empresas', permission: 'platform.tenant.view' },
                    { label: 'Tabelas Auxiliares', icon: List, path: '/configuracoes/cadastros-auxiliares', permission: 'lookups.view' },
                    { label: 'Automações Webhooks', icon: Zap, path: '/automacao', permission: 'automation.rule.view' },
                    { label: 'Integrações Externas', icon: Download, path: '/integracao/auvo', permission: 'auvo.import.view' },
                    { label: 'Importação XLSX', icon: Upload, path: '/importacao', permission: 'import.data.view' },
                ]
            },
            {
                label: 'Comunicação', icon: Phone, path: '/comunicacao', permission: 'whatsapp.config.view',
                children: [
                    { label: 'WhatsApp Instância', icon: Phone, path: '/configuracoes/whatsapp', permission: 'whatsapp.config.view' },
                    { label: 'WhatsApp Histórico', icon: History, path: '/configuracoes/whatsapp/logs', permission: 'whatsapp.log.view' },
                    { label: 'E-mail Integrado', icon: Inbox, path: '/emails', permission: 'email.inbox.view' },
                ]
            },
            {
                label: 'Auditoria Sists', icon: Activity, path: '/configuracoes/observabilidade', permission: 'platform.settings.view',
                children: [
                    { label: 'Logs Sistema', icon: History, path: '/configuracoes/auditoria', permission: 'iam.audit_log.view' },
                    { label: 'Observabilidade APM', icon: Activity, path: '/configuracoes/observabilidade', permission: 'platform.settings.view' },
                ]
            },
        ],
    },
]

const crmNavItem = navigationSections.find(s => s.label === 'Comercial & Vendas')?.items.find(i => i.path === '/crm-gestao')
const salesOnlySections: NavSection[] = [
    {
        label: 'Comercial',
        items: [
            ...(crmNavItem ? [crmNavItem] : []),
            {
                label: 'Orçamentos', icon: FileText, path: '/orcamentos', permission: 'quotes.quote.view',
                children: [
                    { label: 'Lista', icon: FileText, path: '/orcamentos' },
                    { label: 'Dashboard', icon: BarChart3, path: '/orcamentos/dashboard' },
                ],
            },
            { label: 'Clientes', icon: Users, path: '/cadastros/clientes', permission: 'cadastros.customer.view' },
            { label: 'Leads INMETRO', icon: Target, path: '/inmetro/leads', permission: 'inmetro.intelligence.view' },
            {
                label: 'Chamados', icon: Phone, path: '/chamados', permission: 'service_calls.service_call.view',
                children: [
                    { label: 'Lista', icon: FileText, path: '/chamados' },
                    { label: 'Mapa', icon: Scale, path: '/chamados/mapa' },
                    { label: 'Agenda', icon: Calendar, path: '/chamados/agenda' },
                ],
            },
        ],
    },
]

const tecnicoVendedorSections: NavSection[] = [
    {
        label: 'Comercial',
        items: [
            { label: 'Dashboard CRM', icon: BarChart3, path: '/crm', permission: 'crm.deal.view' },
            {
                label: 'Pipeline', icon: Target, path: '/crm/pipeline', permission: 'crm.pipeline.view',
                children: [
                    { label: 'Pipeline', icon: Target, path: '/crm/pipeline' },
                    { label: 'Calendário', icon: Calendar, path: '/crm/calendar' },
                    { label: 'Metas', icon: Target, path: '/crm/goals', permission: 'crm.goal.view' },
                    { label: 'Propostas', icon: FileText, path: '/crm/proposals', permission: 'crm.proposal.view' },
                ],
            },
            {
                label: 'Orçamentos', icon: FileText, path: '/orcamentos', permission: 'quotes.quote.view',
                children: [
                    { label: 'Lista', icon: FileText, path: '/orcamentos' },
                    { label: 'Dashboard', icon: BarChart3, path: '/orcamentos/dashboard' },
                ],
            },
            { label: 'Clientes', icon: Users, path: '/cadastros/clientes', permission: 'cadastros.customer.view' },
            { label: 'Leads INMETRO', icon: Target, path: '/inmetro/leads', permission: 'inmetro.intelligence.view' },
            { label: 'Templates', icon: FileText, path: '/crm/templates', permission: 'crm.message.view' },
            {
                label: 'Chamados', icon: Phone, path: '/chamados', permission: 'service_calls.service_call.view',
                children: [
                    { label: 'Lista', icon: FileText, path: '/chamados' },
                    { label: 'Mapa', icon: Scale, path: '/chamados/mapa' },
                ],
            },
        ],
    },
]

function filterNavByPermission(items: NavItem[], userPerms: string[], isSuperAdmin: boolean): NavItem[] {
    if (isSuperAdmin) return items

    const filtered: NavItem[] = []

    for (const item of items) {
        const canAccessItem = !item.permission || hasPermissionExpression(item.permission, userPerms)

        if (!item.children) {
            if (canAccessItem) filtered.push(item)
            continue
        }

        const allowedChildren = (item.children || []).filter(
            child => !child.permission || hasPermissionExpression(child.permission, userPerms)
        )

        if (!canAccessItem && allowedChildren.length === 0) continue

        filtered.push({
            ...item,
            path: canAccessItem ? item.path : allowedChildren[0].path,
            children: allowedChildren,
        })
    }

    return filtered
}

function filterNavSectionsByPermission(sections: NavSection[], userPerms: string[], isSuperAdmin: boolean): NavSection[] {
    return sections
        .map(section => ({
            ...section,
            items: filterNavByPermission(section.items, userPerms, isSuperAdmin),
        }))
        .filter(section => section.items.length > 0)
}

export function AppLayout({ children }: { children: React.ReactNode }) {
    const location = useLocation()
    const { user, logout, hasRole, hasPermission } = useAuthStore()
    const { sidebarCollapsed, toggleSidebar, sidebarMobileOpen, toggleMobileSidebar } = useUIStore()
    const { isInstallable, isOnline, install } = usePWA()
    const { currentMode } = useAppMode()
    useCrossTabSync()
    const { currentTenant, tenants, switchTenant, isSwitching } = useTenantHook()

    // PWA: Pre-cache data based on current mode
    usePrefetchCriticalData()

    // PWA: Swipe gesture for mobile sidebar
    const mainContentRef = useRef<HTMLDivElement>(null)
    useSwipeGesture(mainContentRef, {
        onSwipeRight: () => { if (!sidebarCollapsed && window.innerWidth < 1024) toggleMobileSidebar() },
        onSwipeLeft: () => { if (sidebarMobileOpen && window.innerWidth < 1024) toggleMobileSidebar() },
        enabled: window.innerWidth < 1024,
    })

    const baseSections =
        currentMode === 'vendedor'
            ? hasRole('tecnico_vendedor') && !hasRole('comercial') && !hasRole('vendedor')
                ? tecnicoVendedorSections
                : salesOnlySections
            : navigationSections

    const filteredSections = filterNavSectionsByPermission(
        baseSections,
        user?.permissions ?? [],
        hasRole('super_admin')
    )
    const filteredNav = filteredSections.flatMap(s => s.items)
    const canViewNotifications = hasRole('super_admin') || hasPermission('notifications.notification.view')

    const { isDark: darkMode, toggle: toggleDarkMode } = useDarkMode()

    const [favorites, setFavorites] = useState<string[]>(() => {
        try {
            return JSON.parse(localStorage.getItem('sidebar-favorites') ?? '[]')
        } catch { return [] }
    })

    const toggleFavorite = (path: string) => {
        setFavorites(prev => {
            const next = prev.includes(path) ? (prev || []).filter(p => p !== path) : [...prev, path]
            localStorage.setItem('sidebar-favorites', JSON.stringify(next))
            return next
        })
    }

    const favoriteItems = (filteredNav || []).filter(item => favorites.includes(item.path))

    const [expandedGroups, setExpandedGroups] = useState<Set<string>>(
        () => {
            const initial = new Set<string>()
            ;(filteredNav || []).forEach(item => {
                if (item.children?.some(child => location.pathname === child.path)) {
                    initial.add(item.path)
                }
            })
            return initial
        }
    )

    const toggleGroup = (path: string) => {
        setExpandedGroups((prev) => {
            const next = new Set(prev)
            if (next.has(path)) next.delete(path)
            else next.add(path)
            return next
        })
    }

    const isActive = (path: string) => location.pathname === path

    // Modo TV standalone: só o conteúdo do War Room, sem sidebar/header (ex.: Smart TV)
    const isTvStandalone =
        location.pathname === '/tv/dashboard' &&
        new URLSearchParams(location.search).get('standalone') === '1'
    if (isTvStandalone) {
        return (
            <div className="h-screen w-screen overflow-hidden bg-background">
                {children}
            </div>
        )
    }

    return (
        <div className="flex h-screen overflow-hidden bg-background">
            {sidebarMobileOpen && (
                <div className="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm lg:hidden" onClick={toggleMobileSidebar} />
            )}

            <aside
                data-sidebar
                className={cn(
                    'fixed inset-y-0 left-0 z-50 flex flex-col border-r border-surface-200 bg-white text-surface-500 transition-[width,transform] duration-200 ease-out',
                    'dark:bg-[#09090B] dark:text-zinc-400 dark:border-white/[0.06]',
                    'lg:relative lg:z-auto',
                    sidebarCollapsed ? 'w-[var(--sidebar-collapsed)]' : 'w-[var(--sidebar-width)]',
                    sidebarMobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
                )}
            >
                <div className="flex h-[var(--topbar-height)] items-center gap-3 border-b border-surface-200 dark:border-white/[0.06] px-4">
                    <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-[var(--radius-md)] bg-blue-600 text-white font-bold text-sm shadow-lg shadow-blue-500/20">
                        K
                    </div>
                    {!sidebarCollapsed && (
                        <span className="truncate font-bold text-surface-900 dark:text-white text-sm tracking-tight">
                            KALIBRIUM
                        </span>
                    )}
                </div>

                <nav className="flex-1 overflow-y-auto px-2 py-2">
                    {favoriteItems.length > 0 && (
                        <div>
                            {!sidebarCollapsed && (
                                <div className="px-2.5 pt-1 pb-1.5 text-label text-blue-500/70 dark:text-blue-400/70 flex items-center gap-1">
                                    <Star className="h-3 w-3 fill-blue-500 text-blue-500/70 dark:fill-blue-400 dark:text-blue-400/70" />
                                    Favoritos
                                </div>
                            )}
                            <div className="space-y-0.5">
                                {(favoriteItems || []).map(item => (
                                    <Link
                                        key={`fav-${item.path}`}
                                        to={item.path}
                                        className={cn(
                                            'group relative flex w-full items-center gap-2.5 rounded-[var(--radius-sm)] px-2.5 py-[7px] text-sm font-medium transition-colors duration-100',
                                            isActive(item.path)
                                                ? 'bg-blue-50 text-surface-900 dark:bg-white/8 dark:text-white'
                                                : 'text-surface-500 hover:bg-surface-100 hover:text-surface-700 dark:text-surface-400 dark:hover:bg-white/5 dark:hover:text-surface-200',
                                            sidebarCollapsed && 'justify-center px-2'
                                        )}
                                    >
                                        {isActive(item.path) && (
                                            <span className="absolute left-0 top-1.5 bottom-1.5 w-[2px] rounded-full bg-blue-500" />
                                        )}
                                        <item.icon className={cn(
                                            'h-4 w-4 shrink-0 transition-colors duration-100',
                                            isActive(item.path) ? 'text-blue-600 dark:text-blue-400' : 'text-surface-400 group-hover:text-surface-600 dark:text-surface-500 dark:group-hover:text-surface-300'
                                        )} />
                                        {!sidebarCollapsed && (
                                            <span className="flex-1 text-left truncate">{item.label}</span>
                                        )}
                                    </Link>
                                ))}
                            </div>
                            <div className={cn('border-t border-surface-100 dark:border-white/6', sidebarCollapsed ? 'my-1.5 mx-2' : 'mt-1.5 mx-2')} />
                        </div>
                    )}

                    {(filteredSections || []).map((section, sectionIdx) => (
                        <div key={section.label}>
                            {!sidebarCollapsed && (
                                <div className={cn(
                                    'px-2.5 pt-3 pb-1.5 text-label text-surface-400 dark:text-surface-500',
                                    sectionIdx > 0 && 'mt-1.5 border-t border-surface-100 dark:border-white/6 pt-3.5'
                                )}>
                                    {section.label}
                                </div>
                            )}
                            {sidebarCollapsed && sectionIdx > 0 && (
                                <div className="my-1.5 mx-2 border-t border-surface-100 dark:border-white/6" />
                            )}
                            <div className="space-y-0.5">
                                {(section.items || []).map((item) => (
                                    <div key={item.path}>
                                        {item.children ? (
                                            <button
                                                onClick={() => toggleGroup(item.path)}
                                                className={cn(
                                                    'group flex w-full items-center gap-2.5 rounded-[var(--radius-sm)] px-2.5 py-[7px] text-sm font-medium transition-colors duration-100',
                                                    item.children.some(c => isActive(c.path))
                                                        ? 'bg-blue-50 text-surface-900 dark:bg-white/8 dark:text-white'
                                                        : 'text-surface-500 hover:bg-surface-100 hover:text-surface-700 dark:text-surface-400 dark:hover:bg-white/5 dark:hover:text-surface-200',
                                                    sidebarCollapsed && 'justify-center px-2'
                                                )}
                                            >
                                                <item.icon className={cn(
                                                    'h-4 w-4 shrink-0 transition-colors duration-100',
                                                    item.children.some(c => isActive(c.path)) ? 'text-blue-600 dark:text-blue-400' : 'text-surface-400 group-hover:text-surface-600 dark:text-surface-500 dark:group-hover:text-surface-300'
                                                )} />
                                                {!sidebarCollapsed && (
                                                    <>
                                                        <span className="flex-1 text-left truncate">{item.label}</span>
                                                        <ChevronRight
                                                            className={cn(
                                                                'h-3.5 w-3.5 text-surface-400 dark:text-surface-500 transition-transform duration-150',
                                                                expandedGroups.has(item.path) && 'rotate-90'
                                                            )}
                                                        />
                                                    </>
                                                )}
                                            </button>
                                        ) : (
                                            <Link
                                                to={item.path}
                                                className={cn(
                                                    'group relative flex w-full items-center gap-2.5 rounded-[var(--radius-sm)] px-2.5 py-[7px] text-sm font-medium transition-colors duration-100',
                                                    isActive(item.path)
                                                        ? 'bg-blue-50 text-surface-900 dark:bg-white/8 dark:text-white'
                                                        : 'text-surface-500 hover:bg-surface-100 hover:text-surface-700 dark:text-surface-400 dark:hover:bg-white/5 dark:hover:text-surface-200',
                                                    sidebarCollapsed && 'justify-center px-2'
                                                )}
                                            >
                                                {isActive(item.path) && (
                                                    <span className="absolute left-0 top-1.5 bottom-1.5 w-[2px] rounded-full bg-blue-500" />
                                                )}
                                                <item.icon className={cn(
                                                    'h-4 w-4 shrink-0 transition-colors duration-100',
                                                    isActive(item.path) ? 'text-blue-600 dark:text-blue-400' : 'text-surface-400 group-hover:text-surface-600 dark:text-surface-500 dark:group-hover:text-surface-300'
                                                )} />
                                                {!sidebarCollapsed && (
                                                    <>
                                                        <span className="flex-1 text-left truncate">{item.label}</span>
                                                        <button
                                                            onClick={(e) => { e.preventDefault(); e.stopPropagation(); toggleFavorite(item.path) }}
                                                            className={cn(
                                                                'h-3.5 w-3.5 shrink-0 transition-all duration-150',
                                                                favorites.includes(item.path)
                                                                    ? 'text-blue-500 dark:text-blue-400 opacity-100'
                                                                    : 'text-surface-300 opacity-0 group-hover:opacity-100 hover:text-blue-500 dark:text-surface-600 dark:hover:text-blue-400'
                                                            )}
                                                            title={favorites.includes(item.path) ? 'Remover dos favoritos' : 'Adicionar aos favoritos'}
                                                        >
                                                            <Star className={cn('h-3.5 w-3.5', favorites.includes(item.path) && 'fill-blue-500 dark:fill-blue-400')} />
                                                        </button>
                                                    </>
                                                )}
                                            </Link>
                                        )}

                                        {item.children && !sidebarCollapsed && expandedGroups.has(item.path) && (
                                            <div className="ml-[18px] mt-0.5 space-y-0.5 border-l border-surface-200 dark:border-white/6 pl-2.5">
                                                {(item.children || []).map((child) => (
                                                    <Link
                                                        key={child.path}
                                                        to={child.path}
                                                        className={cn(
                                                            'relative flex w-full items-center gap-2 rounded-[var(--radius-sm)] px-2 py-[5px] text-xs font-medium transition-colors duration-100',
                                                            isActive(child.path)
                                                                ? 'bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300'
                                                                : 'text-surface-500 hover:bg-surface-100 hover:text-surface-700 dark:text-surface-500 dark:hover:bg-white/4 dark:hover:text-surface-300'
                                                        )}
                                                    >
                                                        <child.icon className="h-3.5 w-3.5" />
                                                        <span>{child.label}</span>
                                                    </Link>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                </nav>

                <div className="hidden border-t border-surface-200 dark:border-white/[0.06] p-2.5 lg:block">
                    <button
                        onClick={toggleSidebar}
                        className="flex w-full items-center justify-center rounded-[var(--radius-md)] p-2 text-surface-400 hover:bg-surface-100 hover:text-blue-500 dark:text-zinc-500 dark:hover:bg-white/[0.04] dark:hover:text-blue-400 transition-colors duration-150"
                    >
                        {sidebarCollapsed ? <ChevronRight className="h-3.5 w-3.5" /> : <ChevronLeft className="h-3.5 w-3.5" />}
                    </button>
                </div>
            </aside>

            <div className="flex flex-1 flex-col overflow-hidden">
                {!isOnline && (
                    <div className="flex items-center justify-center gap-2 bg-amber-500 px-4 py-1 text-xs font-medium text-white">
                        <WifiOff className="h-3 w-3" />
                        Você está offline — dados em cache serão exibidos
                    </div>
                )}

                <header data-header className={cn(
                    "flex h-[var(--topbar-height)] items-center justify-between px-4 lg:px-6",
                    "border-b border-black/[0.06] bg-white/80 backdrop-blur-xl",
                    "dark:border-white/[0.06] dark:bg-[#09090B]/80 dark:backdrop-blur-xl"
                )}>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={toggleMobileSidebar}
                            aria-label={sidebarMobileOpen ? 'Fechar menu lateral' : 'Abrir menu lateral'}
                            data-testid="menu-toggle"
                            className="rounded-[var(--radius-md)] p-1.5 text-surface-500 hover:bg-surface-100 dark:hover:bg-white/[0.04] lg:hidden"
                        >
                            {sidebarMobileOpen ? <X className="h-4.5 w-4.5" /> : <Menu className="h-4.5 w-4.5" />}
                        </button>
                    </div>

                    <div className="flex items-center gap-3">
                        <ModeSwitcher />
                        {isInstallable && (
                            <button onClick={install}
                                className="flex items-center gap-1.5 rounded-[var(--radius-pill)] bg-surface-100 dark:bg-white/[0.06] px-3 py-1.5 text-xs font-medium text-surface-700 dark:text-surface-300 transition-colors hover:bg-surface-200 dark:hover:bg-white/[0.08]">
                                <Download className="h-3 w-3" />
                                Instalar
                            </button>
                        )}

                        {tenants.length > 1 ? (
                            <select
                                value={currentTenant?.id ?? ''}
                                onChange={e => switchTenant(Number(e.target.value))}
                                disabled={isSwitching}
                                aria-label="Selecionar empresa"
                                className="hidden appearance-none rounded-[var(--radius-md)] border border-surface-200 dark:border-white/[0.08] bg-white dark:bg-white/[0.04] px-2.5 py-1.5 text-xs font-medium text-surface-700 dark:text-surface-300 sm:block focus:outline-none focus:ring-2 focus:ring-prix-500/15 cursor-pointer"
                            >
                                {(tenants || []).map(t => <option key={t.id} value={t.id} disabled={t.status === 'inactive'}>{t.name}{t.status === 'inactive' ? ' (Inativa)' : t.status === 'trial' ? ' (Teste)' : ''}</option>)}
                            </select>
                        ) : (
                            <span className="hidden items-center gap-1.5 rounded-[var(--radius-md)] border border-surface-200 dark:border-white/[0.06] bg-surface-50 dark:bg-white/[0.03] px-2.5 py-1.5 text-xs font-medium text-surface-600 dark:text-surface-400 sm:flex">
                                <Building2 className="h-3 w-3" />
                                {currentTenant?.name ?? '—'}
                            </span>
                        )}

                        <NetworkBadge />
                        <SyncStatusPanel />
                        {canViewNotifications ? <NotificationPanel /> : null}

                        {hasPermission('agenda.create.task') ? (
                            <QuickReminderButton className="rounded-[var(--radius-md)] p-1.5 text-surface-500 hover:bg-surface-100 dark:hover:bg-white/[0.04] hover:text-surface-700 dark:hover:text-white transition-colors" />
                        ) : null}

                        <button
                            onClick={toggleDarkMode}
                            className="rounded-[var(--radius-md)] p-1.5 text-surface-400 hover:bg-surface-100 dark:hover:bg-white/[0.04] hover:text-surface-600 dark:hover:text-white transition-all duration-200"
                            title={darkMode ? 'Modo Claro' : 'Modo Escuro'}
                        >
                            {darkMode ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
                        </button>

                        <Link to="/perfil" className="flex items-center gap-2.5 rounded-[var(--radius-md)] px-2 py-1.5 hover:bg-surface-50 dark:hover:bg-white/[0.04] transition-colors duration-150">
                            <div className="flex h-7 w-7 items-center justify-center rounded-[var(--radius-md)] prix-gradient text-white text-xs font-bold shadow-sm">
                                {user?.name?.charAt(0)?.toUpperCase() ?? 'U'}
                            </div>
                            <div className="hidden sm:flex flex-col">
                                <span className="text-sm font-semibold text-surface-800 dark:text-white leading-tight">
                                    {user?.name ?? 'Usuário'}
                                </span>
                                {user?.role_details?.[0] && (
                                    <span className="text-xs text-surface-400 leading-tight">
                                        {user.role_details[0].display_name}
                                    </span>
                                )}
                            </div>
                        </Link>

                        <button
                            onClick={logout}
                            className="rounded-[var(--radius-md)] p-1.5 text-surface-400 hover:bg-red-50 dark:hover:bg-red-500/10 hover:text-red-600 dark:hover:text-red-400 transition-colors duration-150"
                            title="Sair"
                        >
                            <LogOut className="h-3.5 w-3.5" />
                        </button>
                    </div>
                </header>

                <main ref={mainContentRef} className="flex-1 overflow-y-auto p-4 lg:p-6">
                    <AppBreadcrumb />
                    {children}
                </main>

                <UpdateBanner />
                <OfflineIndicator />
                <InstallBanner />
                {currentMode === 'gestao' && <TeamStatusWidget />}
            </div>
        </div>
    )
}
