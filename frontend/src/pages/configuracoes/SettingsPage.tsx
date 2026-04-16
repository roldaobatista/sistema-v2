import React, { useState, useRef } from 'react'
import { Link } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
    Settings, History, Save, Hash, Bell, Calendar, ArrowRight, Shield,
    MessageSquare, Mail, Building2, GitBranch, ExternalLink,
    ChevronDown, ChevronUp, Download, CheckCircle2, Eye, Loader2,
    Upload, ImageIcon, Trash2,
} from 'lucide-react'
import api, { getApiErrorMessage } from '@/lib/api'
import { maskPhone } from '@/lib/form-masks'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { usePushNotifications } from '@/hooks/usePushNotifications'
import { toast } from 'sonner'
import { useAuthStore } from '@/stores/auth-store'

type Tab = 'settings' | 'numbering' | 'notifications' | 'audit'

interface NumberingSeq {
    id: number; entity: string; prefix: string; next_number: number; padding: number
}

interface AuditLogEntry {
    id: number
    created_at: string
    user?: { name?: string }
    action: string
    description: string
    ip_address?: string
    old_values?: Record<string, unknown>
    new_values?: Record<string, unknown>
    auditable_type?: string
}

const actionLabels: Record<string, { label: string; variant: string }> = {
    created: { label: 'Criado', variant: 'success' },
    updated: { label: 'Atualizado', variant: 'info' },
    deleted: { label: 'Excluído', variant: 'danger' },
    login: { label: 'Login', variant: 'default' },
    logout: { label: 'Logout', variant: 'default' },
    status_changed: { label: 'Status Alterado', variant: 'warning' },
}

const settingGroups: Record<string, { label: string; icon: React.ElementType }> = {
    general: { label: 'Empresa', icon: Building2 },
    os: { label: 'Ordens de Serviço', icon: Settings },
    quotes: { label: 'Orçamentos', icon: Settings },
    financial: { label: 'Financeiro', icon: Shield },
    notification: { label: 'Notificações', icon: Settings },
    whatsapp: { label: 'WhatsApp / Evolution API', icon: MessageSquare },
    smtp: { label: 'E-mail / SMTP', icon: Mail },
    crm: { label: 'CRM', icon: GitBranch },
}
interface SettingItem {
    id?: number; key: string; value: string; type: string; group: string
}

const defaultSettings: SettingItem[] = [
    // Empresa (dados cadastrais vêm do Tenant, não de SystemSetting)
    { key: 'company_logo_url', value: '', type: 'string', group: 'general' },
    { key: 'companyTagline', value: '', type: 'string', group: 'general' },
    { key: 'company_address', value: '', type: 'string', group: 'general' },
    // OS
    { key: 'default_warranty_days', value: '90', type: 'integer', group: 'os' },
    { key: 'auto_generate_os_number', value: 'true', type: 'boolean', group: 'os' },
    { key: 'require_approval_above', value: '500', type: 'integer', group: 'os' },
    { key: 'os_number_prefix', value: 'OS-', type: 'string', group: 'os' },
    // Orcamentos
    { key: 'quote_sequence_start', value: '1', type: 'integer', group: 'quotes' },
    { key: 'quote_default_validity_days', value: '30', type: 'integer', group: 'quotes' },
    // Financeiro
    { key: 'default_payment_method', value: 'pix', type: 'string', group: 'financial' },
    { key: 'late_fee_percentage', value: '2', type: 'integer', group: 'financial' },
    { key: 'auto_generate_invoice', value: 'true', type: 'boolean', group: 'financial' },
    // Notificacoes
    { key: 'notify_overdue', value: 'true', type: 'boolean', group: 'notification' },
    { key: 'notify_os_completed', value: 'true', type: 'boolean', group: 'notification' },
    { key: 'notify_new_deal', value: 'true', type: 'boolean', group: 'notification' },
    { key: 'notify_client_os_created', value: 'true', type: 'boolean', group: 'notification' },
    { key: 'notify_client_os_awaiting', value: 'true', type: 'boolean', group: 'notification' },
    { key: 'notify_client_os_completed', value: 'true', type: 'boolean', group: 'notification' },
    { key: 'notify_client_quote_ready', value: 'true', type: 'boolean', group: 'notification' },
    { key: 'alert_os_no_billing', value: 'true', type: 'boolean', group: 'notification' },
    { key: 'alert_contract_expiring', value: 'true', type: 'boolean', group: 'notification' },
    { key: 'alert_certificate_expiring', value: 'true', type: 'boolean', group: 'notification' },
    // WhatsApp
    { key: 'evolution_api_url', value: '', type: 'string', group: 'whatsapp' },
    { key: 'evolution_api_key', value: '', type: 'string', group: 'whatsapp' },
    { key: 'evolution_instance', value: '', type: 'string', group: 'whatsapp' },
    { key: 'whatsapp_enabled', value: 'false', type: 'boolean', group: 'whatsapp' },
    // SMTP
    { key: 'smtp_host', value: '', type: 'string', group: 'smtp' },
    { key: 'smtp_port', value: '587', type: 'integer', group: 'smtp' },
    { key: 'smtpUser', value: '', type: 'string', group: 'smtp' },
    { key: 'smtp_password', value: '', type: 'string', group: 'smtp' },
    { key: 'smtp_encryption', value: 'tls', type: 'string', group: 'smtp' },
    { key: 'smtp_from_name', value: '', type: 'string', group: 'smtp' },
    { key: 'smtp_from_email', value: '', type: 'string', group: 'smtp' },
    // CRM
    { key: 'crm_default_pipeline', value: 'Vendas', type: 'string', group: 'crm' },
    { key: 'crm_auto_create_activity', value: 'true', type: 'boolean', group: 'crm' },
    { key: 'crm_deal_rot_days', value: '14', type: 'integer', group: 'crm' },
    { key: 'crm_enable_scoring', value: 'true', type: 'boolean', group: 'crm' },
]
const settingLabels: Record<string, string> = {
    company_logo_url: 'Logo da Empresa',
    companyTagline: 'Slogan / Atividade (ex: Assistência Técnica)',
    company_address: 'Endereço Completo',
    default_warranty_days: 'Dias de Garantia Padrão',
    auto_generate_os_number: 'Gerar Número OS Automaticamente',
    require_approval_above: 'Exigir Aprovação Acima de (R$)',
    os_number_prefix: 'Prefixo da Numeração OS',
    quote_sequence_start: 'Início da Sequência dos Orçamentos',
    quote_default_validity_days: 'Prazo de Validade Padrão (dias)',
    default_payment_method: 'Forma de Pagamento Padrão',
    late_fee_percentage: 'Multa por Atraso (%)',
    auto_generate_invoice: 'Gerar Fatura Automaticamente ao Concluir OS',
    notify_overdue: 'Notificar Vencimentos',
    notify_os_completed: 'Notificar OS Concluída',
    notify_new_deal: 'Notificar Novo Negócio CRM',
    notify_client_os_created: 'Notificar Cliente: OS Criada',
    notify_client_os_awaiting: 'Notificar Cliente: OS Aguardando Aprovação',
    notify_client_os_completed: 'Notificar Cliente: OS Concluída',
    notify_client_quote_ready: 'Notificar Cliente: Orçamento Pronto',
    alert_os_no_billing: 'Alerta Admin: OS Concluída sem Faturamento',
    alert_contract_expiring: 'Alerta Admin: Contrato Vencendo',
    alert_certificate_expiring: 'Alerta Admin: Certificado Vencendo',
    evolution_api_url: 'URL da Evolution API',
    evolution_api_key: 'API Key',
    evolution_instance: 'Nome da Instância',
    whatsapp_enabled: 'WhatsApp Ativo',
    smtp_host: 'Servidor SMTP',
    smtp_port: 'Porta',
    smtpUser: 'Usuário',
    smtp_password: 'Senha',
    smtp_encryption: 'Criptografia (tls/ssl)',
    smtp_from_name: 'Nome do Remetente',
    smtp_from_email: 'E-mail do Remetente',
    crm_default_pipeline: 'Pipeline Padrão',
    crm_auto_create_activity: 'Criar Atividade Automática em Novo Negócio',
    crm_deal_rot_days: 'Dias Sem Atividade para "Rot" do Negócio',
    crm_enable_scoring: 'Ativar Lead Scoring',
}
const entityLabels: Record<string, string> = {
    equipment: 'Equipamentos',
    standard_weight: 'Pesos Padrão',
    work_order: 'Ordens de Serviço',
    quote: 'Orçamentos',
    certificate: 'Certificados',
    invoice: 'Faturas',
}

export function SettingsPage() {
    const { hasPermission } = useAuthStore()

    const qc = useQueryClient()
    const [tab, setTab] = useState<Tab>('settings')
    const [actionFilter, setActionFilter] = useState('')
    const [entityFilter, setEntityFilter] = useState('')
    const [dateFrom, setDateFrom] = useState('')
    const [dateTo, setDateTo] = useState('')
    const [expandedLog, setExpandedLog] = useState<number | null>(null)
    const [seqEdits, setSeqEdits] = useState<Record<number, Partial<NumberingSeq>>>({})

    // Current tenant data (read-only display)
    const { data: tenantRes } = useQuery({
        queryKey: ['current-tenant-info'],
        queryFn: async () => {
            // Get the list of tenants and find the current one
            const res = await api.get('/tenants')
            const tenants = res?.data?.data ?? res?.data ?? []
            // Typically the first tenant or filtered by current_tenant_id
            return tenants[0] ?? null
        },
        enabled: tab === 'settings',
    })
    const currentTenant = tenantRes ?? null

    // Settings
    const { data: settingsRes, isError } = useQuery({
        queryKey: ['settings'],
        queryFn: () => api.get('/settings'),
        enabled: tab === 'settings',
    })
    const serverSettings = settingsRes?.data?.data ?? settingsRes?.data ?? []

    const mergedSettings = (defaultSettings || []).map(d => {
        const existing = serverSettings.find((s: SettingItem) => s.key === d.key)
        return existing ? { ...d, ...existing } : d
    })

    const [localSettings, setLocalSettings] = useState<Record<string, string>>({})
    const [successMessage, setSuccessMessage] = useState<string | null>(null)

    const getVal = (key: string) => {
        const value = localSettings[key] ?? mergedSettings.find(s => s.key === key)?.value ?? ''

        return key === 'companyPhone' ? maskPhone(value) : value
    }

    const setVal = (key: string, value: string) => setLocalSettings(p => ({
        ...p,
        [key]: key === 'companyPhone' ? maskPhone(value) : value,
    }))

    const saveMut = useMutation({
        mutationFn: () => {
            const settings = (mergedSettings || []).map(s => ({
                key: s.key, value: localSettings[s.key] ?? s.value, type: s.type, group: s.group,
            }))
            return api.put('/settings', { settings })
        },
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['settings'] })
            setLocalSettings({})
            setSuccessMessage('Configurações salvas com sucesso!')
            setTimeout(() => setSuccessMessage(null), 4000)
        },
        onError: (err: unknown) => toast.error(getApiErrorMessage(err, 'Erro ao salvar configurações')),
    })

    // Numbering Sequences
    const { data: seqRes } = useQuery({
        queryKey: ['numbering-sequences'],
        queryFn: () => api.get('/numbering-sequences'),
        enabled: tab === 'numbering',
    })
    const sequences: NumberingSeq[] = seqRes?.data?.data ?? seqRes?.data ?? []

    const seqMut = useMutation({
        mutationFn: ({ id, data }: { id: number; data: Partial<NumberingSeq> }) =>
            api.put(`/numbering-sequences/${id}`, data),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['numbering-sequences'] })
            setSeqEdits({})
            setSuccessMessage('Numeração atualizada com sucesso!')
            setTimeout(() => setSuccessMessage(null), 4000)
        },
    })

    const getSeqVal = (seq: NumberingSeq, field: keyof NumberingSeq) =>
        seqEdits[seq.id]?.[field] ?? seq[field]

    const setSeqVal = (id: number, field: string, value: string | number) =>
        setSeqEdits(p => ({ ...p, [id]: { ...p[id], [field]: value } }))

    const previewNumber = (seq: NumberingSeq) => {
        const prefix = String(getSeqVal(seq, 'prefix'))
        const next = Number(getSeqVal(seq, 'next_number'))
        const pad = Number(getSeqVal(seq, 'padding'))
        return prefix + String(next).padStart(pad, '0')
    }

    // Audit
    const { data: auditRes } = useQuery({
        queryKey: ['audit-logs', actionFilter, entityFilter, dateFrom, dateTo],
        queryFn: () => api.get('/audit-logs', {
            params: {
                action: actionFilter || undefined,
                auditable_type: entityFilter || undefined,
                from: dateFrom || undefined,
                to: dateTo || undefined,
                per_page: 50,
            },
        }),
        enabled: tab === 'audit',
    })
    const auditLogs = auditRes?.data?.data ?? []

    const grouped = Object.entries(settingGroups).map(([group, cfg]) => ({
        group, label: cfg.label,
        items: (mergedSettings || []).filter(s => s.group === group),
    })).filter(g => g.items.length > 0)

    return (
        <div className="space-y-5">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-lg font-semibold text-surface-900 tracking-tight">Configurações</h1>
                    <p className="mt-0.5 text-[13px] text-surface-500">Parametrizações do sistema e logs de auditoria</p>
                </div>
                {tab === 'settings' && (
                    <Button icon={<Save className="h-4 w-4" />} onClick={() => saveMut.mutate()} loading={saveMut.isPending}>
                        Salvar
                    </Button>
                )}
            </div>

            {/* Tabs */}
            <div className="flex rounded-lg border border-default bg-surface-50 p-0.5 w-fit">
                {([{ key: 'settings' as const, label: 'Configurações', icon: Settings }, { key: 'numbering' as const, label: 'Numeração', icon: Hash }, { key: 'notifications' as const, label: 'Notificações', icon: Bell }, { key: 'audit' as const, label: 'Auditoria', icon: History }]).map(t => {
                    const Icon = t.icon
                    return (
                        <button key={t.key} onClick={() => setTab(t.key)}
                            className={cn('flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-all',
                                tab === t.key ? 'bg-surface-0 text-brand-700 shadow-card' : 'text-surface-500 hover:text-surface-700')}>
                            <Icon className="h-3.5 w-3.5" />{t.label}
                        </button>
                    )
                })}
            </div>

            {/* Settings Tab */}
            {tab === 'settings' && (
                <div className="space-y-5">
                    {(grouped || []).map(g => (
                        <div key={g.group} className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                            <h3 className="mb-4 flex items-center gap-2 text-sm font-semibold text-surface-700">
                                {(() => { const GIcon = settingGroups[g.group]?.icon ?? Shield; return <GIcon className="h-4 w-4 text-brand-500" /> })()}
                                {settingGroups[g.group]?.label ?? g.group}
                            </h3>
                            {/* Show tenant cadastral data (read-only) at top of 'general' group */}
                            {g.group === 'general' && currentTenant && (
                                <div className="mb-4 rounded-lg border border-brand-200 bg-brand-50/50 p-4">
                                    <div className="flex items-center justify-between mb-3">
                                        <span className="text-xs font-semibold uppercase text-brand-700 tracking-wider">Dados Cadastrais</span>
                                        <Link to="/configuracoes/empresas" className="inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:text-brand-700 transition-colors">
                                            <ExternalLink className="h-3 w-3" />
                                            Editar na página de Empresas
                                        </Link>
                                    </div>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div>
                                            <span className="text-[11px] text-surface-500 uppercase">Nome</span>
                                            <p className="text-sm font-medium text-surface-800">{currentTenant.name || '—'}</p>
                                        </div>
                                        <div>
                                            <span className="text-[11px] text-surface-500 uppercase">CNPJ/CPF</span>
                                            <p className="text-sm font-medium text-surface-800">{currentTenant.document || '—'}</p>
                                        </div>
                                        <div>
                                            <span className="text-[11px] text-surface-500 uppercase">E-mail</span>
                                            <p className="text-sm font-medium text-surface-800">{currentTenant.email || '—'}</p>
                                        </div>
                                        <div>
                                            <span className="text-[11px] text-surface-500 uppercase">Telefone</span>
                                            <p className="text-sm font-medium text-surface-800">{currentTenant.phone || '—'}</p>
                                        </div>
                                    </div>
                                </div>
                            )}
                            <div className="space-y-4">
                                {(g.items || []).map(s => {
                                    if (s.key === 'company_logo_url') {
                                        return <CompanyLogoUpload key={s.key} currentUrl={getVal(s.key)} onUploaded={(url) => setVal(s.key, url)} />
                                    }
                                    return (
                                        <div key={s.key} className="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:justify-between">
                                            <label className="text-sm font-medium text-surface-600">{settingLabels[s.key] ?? s.key}</label>
                                            {s.type === 'boolean' ? (
                                                <button onClick={() => setVal(s.key, getVal(s.key) === 'true' ? 'false' : 'true')}
                                                    aria-label={settingLabels[s.key] ?? s.key}
                                                    className={cn('relative h-6 w-11 rounded-full transition-colors',
                                                        getVal(s.key) === 'true' ? 'bg-brand-500' : 'bg-surface-300')}>
                                                    <span className={cn('absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white transition-transform shadow',
                                                        getVal(s.key) === 'true' && 'translate-x-5')} />
                                                </button>
                                            ) : (
                                                <input value={getVal(s.key)} onChange={(e: React.ChangeEvent<HTMLInputElement>) => setVal(s.key, e.target.value)}
                                                    type={s.type === 'integer' ? 'number' : 'text'}
                                                    aria-label={settingLabels[s.key] ?? s.key}
                                                    inputMode={s.key === 'companyPhone' ? 'tel' : undefined}
                                                    maxLength={s.key === 'companyPhone' ? 15 : undefined}
                                                    className="w-full max-w-xs rounded-lg border border-default bg-surface-50 px-3.5 py-2 text-sm focus:border-brand-400 focus:bg-surface-0 focus:outline-none focus:ring-2 focus:ring-brand-500/15" />
                                            )}
                                        </div>
                                    )
                                })}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* Numbering Tab */}
            {tab === 'numbering' && (
                <div className="space-y-4">
                    <p className="text-[13px] text-surface-500">Configure o prefixo, próximo número e padding de cada entidade. A numeração é aplicada automaticamente ao criar novos registros.</p>
                    <div className="rounded-xl border border-default bg-surface-0 shadow-card overflow-hidden">
                        <table className="w-full">
                            <thead>
                                <tr className="border-b border-subtle bg-surface-50">
                                    <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase text-surface-600">Entidade</th>
                                    <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase text-surface-600">Prefixo</th>
                                    <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase text-surface-600">Próximo Nº</th>
                                    <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase text-surface-600">Dígitos</th>
                                    <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase text-surface-600">
                                        <span className="flex items-center gap-1"><Eye className="h-3.5 w-3.5" /> Preview</span>
                                    </th>
                                    <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase text-surface-600">Ação</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-subtle">
                                {sequences.length === 0 ? (
                                    <tr><td colSpan={6} className="px-4 py-12 text-center text-[13px] text-surface-500">Nenhuma sequência configurada. Crie um registro de equipamento ou peso padrão para inicializar.</td></tr>
                                ) : (sequences || []).map((seq) => (
                                    <tr key={seq.id} className="hover:bg-surface-50 transition-colors">
                                        <td className="px-4 py-3 text-[13px] font-medium text-surface-900">{entityLabels[seq.entity] ?? seq.entity}</td>
                                        <td className="px-4 py-3">
                                            <input
                                                value={String(getSeqVal(seq, 'prefix'))}
                                                onChange={(e) => setSeqVal(seq.id, 'prefix', e.target.value)}
                                                aria-label={`Prefixo ${entityLabels[seq.entity] ?? seq.entity}`}
                                                className="w-24 rounded-lg border border-default bg-surface-50 px-2.5 py-1.5 text-sm font-mono focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/15" />
                                        </td>
                                        <td className="px-4 py-3">
                                            <input
                                                type="number" min={1}
                                                value={Number(getSeqVal(seq, 'next_number'))}
                                                onChange={(e) => setSeqVal(seq.id, 'next_number', parseInt(e.target.value) || 1)}
                                                aria-label={`Próximo número ${entityLabels[seq.entity] ?? seq.entity}`}
                                                className="w-24 rounded-lg border border-default bg-surface-50 px-2.5 py-1.5 text-sm font-mono focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/15" />
                                        </td>
                                        <td className="px-4 py-3">
                                            <input
                                                type="number" min={1} max={10}
                                                value={Number(getSeqVal(seq, 'padding'))}
                                                onChange={(e) => setSeqVal(seq.id, 'padding', parseInt(e.target.value) || 1)}
                                                aria-label={`Dígitos ${entityLabels[seq.entity] ?? seq.entity}`}
                                                className="w-20 rounded-lg border border-default bg-surface-50 px-2.5 py-1.5 text-sm font-mono focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/15" />
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="inline-flex items-center rounded-md bg-brand-50 px-2.5 py-1 text-sm font-mono font-semibold text-brand-700">
                                                {previewNumber(seq)}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Button
                                                size="sm"
                                                icon={<Save className="h-3.5 w-3.5" />}
                                                disabled={!seqEdits[seq.id]}
                                                loading={seqMut.isPending}
                                                onClick={() => seqEdits[seq.id] && seqMut.mutate({ id: seq.id, data: seqEdits[seq.id] })}
                                            >
                                                Salvar
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* Notifications Tab */}
            {tab === 'notifications' && <NotificationsTab />}

            {/* Audit Tab */}
            {tab === 'audit' && (
                <div className="space-y-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <select value={actionFilter} onChange={(e: React.ChangeEvent<HTMLSelectElement>) => setActionFilter(e.target.value)}
                            aria-label="Filtrar por ação"
                            className="rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                            <option value="">Todas ações</option>
                            {Object.entries(actionLabels).map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}
                        </select>
                        <select value={entityFilter} onChange={(e: React.ChangeEvent<HTMLSelectElement>) => setEntityFilter(e.target.value)}
                            aria-label="Filtrar por entidade"
                            className="rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                            <option value="">Todas entidades</option>
                            <option value="WorkOrder">Ordem de Serviço</option>
                            <option value="Quote">Orçamento</option>
                            <option value="Customer">Cliente</option>
                            <option value="CrmDeal">Negócio CRM</option>
                            <option value="Equipment">Equipamento</option>
                            <option value="Tenant">Empresa</option>
                            <option value="User">Usuário</option>
                        </select>
                        <div className="flex items-center gap-2">
                            <Calendar className="h-4 w-4 text-surface-400" />
                            <input type="date" value={dateFrom} onChange={(e: React.ChangeEvent<HTMLInputElement>) => setDateFrom(e.target.value)}
                                aria-label="Data início auditoria"
                                className="rounded-lg border border-default bg-surface-50 px-3 py-1.5 text-sm focus:border-brand-500 focus:outline-none" />
                            <ArrowRight className="h-3.5 w-3.5 text-surface-400" />
                            <input type="date" value={dateTo} onChange={(e: React.ChangeEvent<HTMLInputElement>) => setDateTo(e.target.value)}
                                aria-label="Data fim auditoria"
                                className="rounded-lg border border-default bg-surface-50 px-3 py-1.5 text-sm focus:border-brand-500 focus:outline-none" />
                        </div>
                        <button onClick={() => {
                            const rows = (auditLogs || []).map((l: AuditLogEntry) => `${l.created_at},${l.user?.name ?? ''},${l.action},"${l.description}",${l.ip_address ?? ''}`)
                            const csv = `Data,Usu\u00e1rio,A\u00e7\u00e3o,Descri\u00e7\u00e3o,IP\n${rows.join('\n')}`
                            const blob = new Blob([csv], { type: 'text/csv' })
                            const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'audit_logs.csv'; a.click()
                        }} className="inline-flex items-center gap-1.5 rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm font-medium text-surface-600 hover:bg-surface-50 transition-colors duration-100"
                            title="Exportar CSV">
                            <Download className="h-3.5 w-3.5" /> CSV
                        </button>
                    </div>
                    <div className="overflow-hidden rounded-xl border border-default bg-surface-0 shadow-card">
                        <table className="w-full">
                            <thead>
                                <tr className="border-b border-subtle bg-surface-50">
                                    <th className="w-8 px-2 py-3" />
                                    <th className="px-3.5 py-2.5 text-left text-xs font-semibold uppercase text-surface-600">Data</th>
                                    <th className="px-3.5 py-2.5 text-left text-xs font-semibold uppercase text-surface-600">Usuário</th>
                                    <th className="px-3.5 py-2.5 text-left text-xs font-semibold uppercase text-surface-600">Ação</th>
                                    <th className="px-3.5 py-2.5 text-left text-xs font-semibold uppercase text-surface-600">Descrição</th>
                                    <th className="hidden px-3.5 py-2.5 text-left text-xs font-semibold uppercase text-surface-600 md:table-cell">Entidade</th>
                                    <th className="hidden px-3.5 py-2.5 text-left text-xs font-semibold uppercase text-surface-600 md:table-cell">IP</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-subtle">
                                {auditLogs.length === 0 ? (
                                    <tr><td colSpan={7} className="px-4 py-12 text-center text-[13px] text-surface-500">Nenhum registro</td></tr>
                                ) : (auditLogs || []).map((log: AuditLogEntry) => {
                                    const hasChanges = log.old_values || log.new_values
                                    const isExpanded = expandedLog === log.id
                                    const entityName = log.auditable_type?.split('\\').pop() ?? ''
                                    return (
                                        <>
                                            <tr key={log.id} className={cn('hover:bg-surface-50 transition-colors duration-100', hasChanges && 'cursor-pointer')}
                                                onClick={() => hasChanges && setExpandedLog(isExpanded ? null : log.id)}>
                                                <td className="px-2 py-3 text-center">
                                                    {hasChanges && (isExpanded
                                                        ? <ChevronUp className="h-3.5 w-3.5 text-surface-400 mx-auto" />
                                                        : <ChevronDown className="h-3.5 w-3.5 text-surface-400 mx-auto" />)}
                                                </td>
                                                <td className="px-4 py-3 text-xs text-surface-500 whitespace-nowrap">{new Date(log.created_at).toLocaleString('pt-BR')}</td>
                                                <td className="px-4 py-3 text-[13px] font-medium text-surface-900">{log.user?.name ?? '\u2014'}</td>
                                                <td className="px-4 py-3">
                                                    <Badge variant={actionLabels[log.action]?.variant ?? 'default' as "default" | "success" | "warning" | "danger" | "info"}>
                                                        {actionLabels[log.action]?.label ?? log.action}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3 text-[13px] text-surface-600">{log.description}</td>
                                                <td className="hidden px-4 py-3 text-xs text-surface-400 md:table-cell">
                                                    {entityName && <span className="rounded bg-surface-100 px-1.5 py-0.5 text-xs font-medium">{entityName}</span>}
                                                </td>
                                                <td className="hidden px-4 py-3 text-xs text-surface-400 md:table-cell">{log.ip_address}</td>
                                            </tr>
                                            {isExpanded && hasChanges && (
                                                <tr key={`${log.id}-diff`}>
                                                    <td colSpan={7} className="px-6 py-4 bg-surface-50">
                                                        <div className="rounded-lg border border-default bg-surface-0 p-4">
                                                            <h4 className="text-xs font-semibold text-surface-700 mb-3">Alterações</h4>
                                                            <div className="space-y-2 text-xs font-mono">
                                                                {Object.keys({ ...(log.old_values ?? {}), ...(log.new_values ?? {}) }).map((key: string) => {
                                                                    const oldVal = log.old_values?.[key]
                                                                    const newVal = log.new_values?.[key]
                                                                    if (JSON.stringify(oldVal) === JSON.stringify(newVal)) return null
                                                                    return (
                                                                        <div key={key} className="flex items-start gap-2">
                                                                            <span className="font-semibold text-surface-600 min-w-[120px]">{key}:</span>
                                                                            <div className="flex flex-col gap-0.5">
                                                                                {oldVal !== undefined && (
                                                                                    <span className="rounded bg-red-50 px-1.5 py-0.5 text-red-700 line-through">
                                                                                        {typeof oldVal === 'object' ? JSON.stringify(oldVal) : String(oldVal)}
                                                                                    </span>
                                                                                )}
                                                                                {newVal !== undefined && (
                                                                                    <span className="rounded bg-emerald-50 px-1.5 py-0.5 text-emerald-700">
                                                                                        {typeof newVal === 'object' ? JSON.stringify(newVal) : String(newVal)}
                                                                                    </span>
                                                                                )}
                                                                            </div>
                                                                        </div>
                                                                    )
                                                                })}
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            )}
                                        </>
                                    )
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* Success Toast */}
            {successMessage && (
                <div className="fixed bottom-6 right-6 z-[70] flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm text-white shadow-xl animate-slide-up">
                    <CheckCircle2 className="h-4 w-4 flex-shrink-0" />
                    <span>{successMessage}</span>
                </div>
            )}
        </div>
    )
}

function NotificationsTab() {
    const { permission, isSubscribed, isLoading, subscribe, unsubscribe, sendTest } = usePushNotifications()

    const statusMap: Record<string, { text: string; color: string }> = {
        granted: { text: 'Permitido', color: 'text-emerald-600' },
        denied: { text: 'Bloqueado', color: 'text-red-600' },
        default: { text: 'Não solicitado', color: 'text-amber-600' },
        unsupported: { text: 'Não suportado', color: 'text-surface-400' },
    }
    const status = statusMap[permission] ?? statusMap.default

    return (
        <div className="space-y-5">
            {/* Push Notifications */}
            <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                <h3 className="mb-4 flex items-center gap-2 text-sm font-semibold text-surface-700">
                    <Bell className="h-4 w-4 text-brand-500" />
                    Push Notifications (Navegador)
                </h3>
                <div className="space-y-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm font-medium text-surface-700">Status da permissão</p>
                            <p className={cn('text-xs mt-0.5', status.color)}>{status.text}</p>
                        </div>
                        {permission === 'denied' && (
                            <p className="text-xs text-red-500 max-w-xs text-right">
                                Permissão bloqueada. Altere nas configurações do navegador.
                            </p>
                        )}
                    </div>

                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm font-medium text-surface-700">Receber notificações push</p>
                            <p className="text-xs text-surface-500 mt-0.5">
                                {isSubscribed
                                    ? 'Este dispositivo está recebendo notificações'
                                    : 'Ative para receber notificações neste dispositivo'}
                            </p>
                        </div>
                        <button
                            onClick={() => isSubscribed ? unsubscribe() : subscribe()}
                            disabled={isLoading || permission === 'denied' || permission === 'unsupported'}
                            aria-label={isSubscribed ? 'Desativar push' : 'Ativar push'}
                            className={cn('relative h-6 w-11 rounded-full transition-colors disabled:opacity-50',
                                isSubscribed ? 'bg-brand-500' : 'bg-surface-300')}
                        >
                            {isLoading ? (
                                <Loader2 className="absolute top-1 left-3 h-4 w-4 animate-spin text-white" />
                            ) : (
                                <span className={cn('absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white transition-transform shadow',
                                    isSubscribed && 'translate-x-5')} />
                            )}
                        </button>
                    </div>

                    {isSubscribed && (
                        <div className="pt-2 border-t border-subtle">
                            <button
                                onClick={async () => {
                                    await sendTest()
                                    toast.success('Notificação de teste enviada!')
                                }}
                                className="text-sm text-brand-600 hover:text-brand-700 font-medium"
                            >
                                Enviar notificação de teste →
                            </button>
                        </div>
                    )}
                </div>
            </div>

            {/* Channel Info */}
            <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                <h3 className="mb-4 flex items-center gap-2 text-sm font-semibold text-surface-700">
                    <Mail className="h-4 w-4 text-brand-500" />
                    Canais de Notificação
                </h3>
                <div className="space-y-3">
                    <div className="flex items-center gap-3 p-3 rounded-lg bg-surface-50">
                        <Mail className="h-5 w-5 text-surface-400" />
                        <div>
                            <p className="text-sm font-medium text-surface-700">E-mail</p>
                            <p className="text-xs text-surface-500">Configure SMTP na aba Configurações → E-mail/SMTP</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3 p-3 rounded-lg bg-surface-50">
                        <MessageSquare className="h-5 w-5 text-emerald-500" />
                        <div>
                            <p className="text-sm font-medium text-surface-700">WhatsApp</p>
                            <p className="text-xs text-surface-500">Configure Evolution API na aba Configurações → WhatsApp</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3 p-3 rounded-lg bg-surface-50">
                        <Bell className="h-5 w-5 text-brand-500" />
                        <div>
                            <p className="text-sm font-medium text-surface-700">Push (Browser/PWA)</p>
                            <p className="text-xs text-surface-500">Configure VAPID keys no .env do servidor</p>
                        </div>
                    </div>
                </div>
            </div>

            {/* Alert Settings Info */}
            <div className="rounded-xl border border-default bg-surface-0 p-5 shadow-card">
                <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold text-surface-700">
                    <Shield className="h-4 w-4 text-amber-500" />
                    Alertas de Negócio
                </h3>
                <p className="text-xs text-surface-500 mb-3">
                    Ative/desative alertas individuais na aba <strong>Configurações → Notificações</strong>.
                    Os alertas são enviados automaticamente via WhatsApp e Push para os administradores.
                </p>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    {[
                        { label: 'OS sem faturamento', icon: '⚠️' },
                        { label: 'Contrato vencendo', icon: '📋' },
                        { label: 'Certificado vencendo', icon: '⚖️' },
                    ].map(alert => (
                        <div key={alert.label} className="flex items-center gap-2 p-2.5 rounded-lg border border-subtle bg-surface-50">
                            <span>{alert.icon}</span>
                            <span className="text-xs font-medium text-surface-600">{alert.label}</span>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    )
}

function CompanyLogoUpload({ currentUrl, onUploaded }: { currentUrl: string; onUploaded: (url: string) => void }) {
    const fileRef = useRef<HTMLInputElement>(null)
    const qc = useQueryClient()
    const [uploading, setUploading] = useState(false)
    const [preview, setPreview] = useState<string | null>(null)

    // Fetch logo from tenant-settings (per-tenant)
    const { data: tenantLogoRes } = useQuery({
        queryKey: ['tenant-settings', 'company_logo_url'],
        queryFn: () => api.get('/tenant-settings/company_logo_url'),
    })
    const tenantLogoUrl = tenantLogoRes?.data?.value ?? tenantLogoRes?.data?.data?.value ?? ''
    const displayUrl = preview || tenantLogoUrl || currentUrl || null

    const handleUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0]
        if (!file) return

        setPreview(URL.createObjectURL(file))
        setUploading(true)

        try {
            const form = new FormData()
            form.append('logo', file)
            const res = await api.post('/settings/logo', form, {
                headers: { 'Content-Type': 'multipart/form-data' },
            })
            onUploaded(res.data.url ?? res.data.data?.url ?? '')
            qc.invalidateQueries({ queryKey: ['tenant-settings'] })
            toast.success('Logo atualizado com sucesso!')
        } catch {
            toast.error('Erro ao enviar logo')
            setPreview(null)
        } finally {
            setUploading(false)
        }
    }

    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <label className="text-sm font-medium text-surface-600">Logo da Empresa</label>
            <div className="flex items-center gap-3">
                {displayUrl ? (
                    <div className="relative group">
                        <img
                            src={displayUrl}
                            alt="Logo da empresa"
                            className="h-16 w-16 rounded-lg border border-default object-contain bg-surface-0 p-1"
                        />
                        <button
                            type="button"
                            onClick={() => { onUploaded(''); setPreview(null) }}
                            className="absolute -top-1.5 -right-1.5 hidden group-hover:flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-white shadow"
                            title="Remover logo"
                        >
                            <Trash2 className="h-3 w-3" />
                        </button>
                    </div>
                ) : (
                    <div className="flex h-16 w-16 items-center justify-center rounded-lg border-2 border-dashed border-surface-300 bg-surface-50">
                        <ImageIcon className="h-6 w-6 text-surface-400" />
                    </div>
                )}
                <div>
                    <button
                        type="button"
                        onClick={() => fileRef.current?.click()}
                        disabled={uploading}
                        className={cn(
                            'inline-flex items-center gap-1.5 rounded-lg border border-default bg-surface-0 px-3 py-1.5 text-sm font-medium text-surface-700',
                            'hover:bg-surface-50 transition-colors disabled:opacity-50'
                        )}
                    >
                        {uploading ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Upload className="h-3.5 w-3.5" />}
                        {uploading ? 'Enviando...' : 'Enviar Logo'}
                    </button>
                    <p className="mt-1 text-[11px] text-surface-400">PNG, JPG ou SVG. Max 2MB.</p>
                </div>
                <input ref={fileRef} type="file" accept="image/png,image/jpeg,image/svg+xml,image/webp" className="hidden" onChange={handleUpload} />
            </div>
        </div>
    )
}
