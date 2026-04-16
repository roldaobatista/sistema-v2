import { describe, it, expect } from 'vitest'

// Inline copies of the pure functions from App.tsx to test in isolation
// without importing the entire component tree and its 80+ page imports.

const commissionsModuleViewPermission = 'commissions.rule.view|commissions.event.view|commissions.settlement.view|commissions.dispute.view|commissions.goal.view|commissions.campaign.view|commissions.recurring.view'

const routePermissionRules: Array<{ match: string; permission: string | null }> = [
    { match: '/agenda/regras', permission: 'agenda.manage.rules' },
    { match: '/agenda/dashboard', permission: 'agenda.manage.kpis' },
    { match: '/agenda', permission: 'agenda.item.view' },
    { match: '/iam/usuarios', permission: 'iam.user.view' },
    { match: '/iam/roles', permission: 'iam.role.view' },
    { match: '/iam/permissoes', permission: 'iam.role.view' },
    { match: '/admin/audit-log', permission: 'iam.audit_log.view' },
    { match: '/cadastros/clientes/fusao', permission: 'cadastros.customer.update' },
    { match: '/cadastros/clientes', permission: 'cadastros.customer.view' },
    { match: '/cadastros/produtos', permission: 'cadastros.product.view' },
    { match: '/cadastros/servicos', permission: 'cadastros.service.view' },
    { match: '/cadastros/historico-precos', permission: 'cadastros.product.view' },
    { match: '/cadastros/exportacao-lote', permission: 'cadastros.customer.view' },
    { match: '/cadastros/fornecedores', permission: 'cadastros.supplier.view' },
    { match: '/orcamentos/novo', permission: 'quotes.quote.create' },
    { match: '/chamados/novo', permission: 'service_calls.service_call.create' },
    { match: '/equipamentos/novo', permission: 'equipments.equipment.create' },
    { match: '/inmetro/instrumentos', permission: 'inmetro.intelligence.view' },
    { match: '/inmetro/importacao', permission: 'inmetro.intelligence.import' },
    { match: '/orcamentos/', permission: 'quotes.quote.view' },
    { match: '/orcamentos', permission: 'quotes.quote.view' },
    { match: '/chamados', permission: 'service_calls.service_call.view' },
    { match: '/os/nova', permission: 'os.work_order.create' },
    { match: '/os/checklists-servico', permission: 'os.work_order.view' },
    { match: '/os/checklists', permission: 'technicians.checklist.view' },
    { match: '/os/agenda', permission: 'os.work_order.view' },
    { match: '/os/mapa', permission: 'os.work_order.view' },
    { match: '/os/dashboard', permission: 'os.work_order.view' },
    { match: '/os', permission: 'os.work_order.view' },
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
    { match: '/financeiro/faturamento', permission: 'finance.receivable.view' },
    { match: '/financeiro/conciliacao-bancaria', permission: 'finance.receivable.view' },
    { match: '/financeiro/plano-contas', permission: 'finance.chart.view' },
    { match: '/financeiro/categorias-pagar', permission: 'finance.payable.view' },
    { match: '/estoque', permission: 'estoque.movement.view' },
    { match: '/relatorios', permission: 'reports.os_report.view' },
    { match: '/notificacoes', permission: 'notifications.notification.view' },
    { match: '/importacao', permission: 'import.data.view' },
    { match: '/inmetro', permission: 'inmetro.intelligence.view' },
    { match: '/equipamentos', permission: 'equipments.equipment.view' },
    { match: '/agenda-calibracoes', permission: 'equipments.equipment.view' },
    { match: '/calibracao/templates', permission: 'equipments.calibration.view' },
    { match: '/configuracoes/filiais', permission: 'platform.branch.view' },
    { match: '/configuracoes/empresas', permission: 'platform.tenant.view' },
    { match: '/configuracoes/whatsapp/logs', permission: 'whatsapp.log.view' },
    { match: '/configuracoes/auditoria', permission: 'iam.audit_log.view' },
    { match: '/configuracoes', permission: 'platform.settings.view' },
    { match: '/financeiro/cobranca-automatica', permission: 'finance.receivable.view' },
    { match: '/crm/pipeline', permission: 'crm.pipeline.view' },
    { match: '/crm/clientes', permission: 'crm.deal.view' },
    { match: '/crm/templates', permission: 'crm.message.view' },
    { match: '/crm', permission: 'crm.deal.view' },
    { match: '/tv/cameras', permission: 'tv.camera.manage' },
    { match: '/seguranca', permission: 'platform.settings.manage' },
    { match: '/share', permission: 'quotes.quote.create|crm.deal.view|os.work_order.create' },
    { match: '/rh/recrutamento', permission: 'hr.recruitment.view' },
    { match: '/perfil', permission: null },
    { match: '/', permission: 'platform.dashboard.view' },
]

function resolveRequiredPermission(pathname: string): string | null {
    if (/^\/orcamentos\/[^/]+\/editar$/.test(pathname)) {
        return 'quotes.quote.update'
    }
    for (const rule of routePermissionRules) {
        if (rule.match === pathname) return rule.permission
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

// =============================================================================
// resolveRequiredPermission
// =============================================================================

describe('resolveRequiredPermission', () => {
    it('returns exact match permission for known routes', () => {
        expect(resolveRequiredPermission('/cadastros/clientes')).toBe('cadastros.customer.view')
        expect(resolveRequiredPermission('/os')).toBe('os.work_order.view')
        expect(resolveRequiredPermission('/os/checklists')).toBe('technicians.checklist.view')
        expect(resolveRequiredPermission('/os/checklists-servico')).toBe('os.work_order.view')
        expect(resolveRequiredPermission('/financeiro/receber')).toBe('finance.receivable.view')
        expect(resolveRequiredPermission('/relatorios')).toBe('reports.os_report.view')
        expect(resolveRequiredPermission('/rh/recrutamento')).toBe('hr.recruitment.view')
    })

    it('returns null for /perfil (public authenticated route)', () => {
        expect(resolveRequiredPermission('/perfil')).toBeNull()
    })

    it('returns dashboard permission for root /', () => {
        expect(resolveRequiredPermission('/')).toBe('platform.dashboard.view')
    })

    it('returns null for unknown routes', () => {
        expect(resolveRequiredPermission('/rota-que-nao-existe')).toBeNull()
    })

    it('resolves /orcamentos/:id/editar via regex to quotes.quote.update', () => {
        expect(resolveRequiredPermission('/orcamentos/123/editar')).toBe('quotes.quote.update')
        expect(resolveRequiredPermission('/orcamentos/abc/editar')).toBe('quotes.quote.update')
    })

    it('resolves sub-paths correctly using startsWith', () => {
        // /cadastros/clientes matches, so /cadastros/clientes/123 should also match
        expect(resolveRequiredPermission('/cadastros/clientes/123')).toBe('cadastros.customer.view')
        expect(resolveRequiredPermission('/os/123')).toBe('os.work_order.view')
        expect(resolveRequiredPermission('/crm/pipeline/5')).toBe('crm.pipeline.view')
    })

    it('respects rule ordering — more specific rules first', () => {
        // /agenda/regras is listed BEFORE /central, so it should match the more specific rule
        expect(resolveRequiredPermission('/agenda/regras')).toBe('agenda.manage.rules')
        expect(resolveRequiredPermission('/agenda/dashboard')).toBe('agenda.manage.kpis')
        expect(resolveRequiredPermission('/agenda')).toBe('agenda.item.view')
    })

    it('resolves /financeiro/comissoes/dashboard before /financeiro/comissoes', () => {
        expect(resolveRequiredPermission('/financeiro/comissoes/dashboard')).toBe('commissions.rule.view')
        expect(resolveRequiredPermission('/financeiro/comissoes')).toBe(commissionsModuleViewPermission)
    })

    it('resolves /cadastros/clientes/fusao before /cadastros/clientes', () => {
        expect(resolveRequiredPermission('/cadastros/clientes/fusao')).toBe('cadastros.customer.update')
    })

    it('resolves /orcamentos/novo (create) vs /orcamentos (view)', () => {
        expect(resolveRequiredPermission('/orcamentos/novo')).toBe('quotes.quote.create')
        expect(resolveRequiredPermission('/orcamentos')).toBe('quotes.quote.view')
    })

    it('resolves all INMETRO routes correctly', () => {
        expect(resolveRequiredPermission('/inmetro')).toBe('inmetro.intelligence.view')
        expect(resolveRequiredPermission('/inmetro/instrumentos')).toBe('inmetro.intelligence.view')
        expect(resolveRequiredPermission('/inmetro/importacao')).toBe('inmetro.intelligence.import')
    })

    it('resolves all equipment routes', () => {
        expect(resolveRequiredPermission('/equipamentos')).toBe('equipments.equipment.view')
        expect(resolveRequiredPermission('/equipamentos/novo')).toBe('equipments.equipment.create')
        expect(resolveRequiredPermission('/agenda-calibracoes')).toBe('equipments.equipment.view')
    })

    it('resolves all settings routes', () => {
        expect(resolveRequiredPermission('/configuracoes')).toBe('platform.settings.view')
        expect(resolveRequiredPermission('/configuracoes/filiais')).toBe('platform.branch.view')
        expect(resolveRequiredPermission('/configuracoes/empresas')).toBe('platform.tenant.view')
        expect(resolveRequiredPermission('/configuracoes/whatsapp/logs')).toBe('whatsapp.log.view')
    })

    it('resolves recently hardened routes', () => {
        expect(resolveRequiredPermission('/financeiro/cobranca-automatica')).toBe('finance.receivable.view')
        expect(resolveRequiredPermission('/calibracao/templates')).toBe('equipments.calibration.view')
        expect(resolveRequiredPermission('/tv/cameras')).toBe('tv.camera.manage')
        expect(resolveRequiredPermission('/seguranca')).toBe('platform.settings.manage')
        expect(resolveRequiredPermission('/share')).toBe('quotes.quote.create|crm.deal.view|os.work_order.create')
    })
})

// =============================================================================
// hasPermissionExpression
// =============================================================================

describe('hasPermissionExpression', () => {
    it('returns true for single matching permission', () => {
        const has = (p: string) => p === 'finance.receivable.view'
        expect(hasPermissionExpression('finance.receivable.view', has)).toBe(true)
    })

    it('returns false for single non-matching permission', () => {
        const has = () => false
        expect(hasPermissionExpression('finance.receivable.view', has)).toBe(false)
    })

    it('returns true if ANY of the OR permissions match (pipe-separated)', () => {
        const has = (p: string) => p === 'finance.payable.view'
        expect(hasPermissionExpression('finance.receivable.view|finance.payable.view', has)).toBe(true)
    })

    it('returns false if NONE of the OR permissions match', () => {
        const has = () => false
        expect(hasPermissionExpression('finance.receivable.view|finance.payable.view', has)).toBe(false)
    })

    it('trims whitespace in expressions', () => {
        const has = (p: string) => p === 'a'
        expect(hasPermissionExpression(' a | b ', has)).toBe(true)
    })

    it('filters out empty strings from split', () => {
        const has = (p: string) => p === 'a'
        expect(hasPermissionExpression('|a||', has)).toBe(true)
    })
})
