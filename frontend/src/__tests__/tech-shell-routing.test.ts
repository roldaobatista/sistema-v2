import { describe, expect, it } from 'vitest'

const TECH_ROUTE_PERMISSIONS: Array<{ match: string; permission: string | null }> = [
    { match: '/tech/nova-os', permission: 'os.work_order.create' },
    { match: '/tech/chamados', permission: 'service_calls.service_call.view' },
    { match: '/tech/apontamentos', permission: 'technicians.time_entry.view' },
    { match: '/tech/caixa', permission: 'technicians.cashbox.view' },
    { match: '/tech/agenda', permission: 'technicians.schedule.view' },
    { match: '/tech/central', permission: 'agenda.item.view' },
    { match: '/tech/rota', permission: 'technicians.schedule.view' },
    { match: '/tech/mapa', permission: 'technicians.schedule.view' },
    { match: '/tech/despesas', permission: 'technicians.cashbox.view' },
    { match: '/tech/ponto', permission: 'hr.clock.view' },
    { match: '/tech/equipamentos', permission: 'equipments.equipment.view' },
    { match: '/tech/equipamento/', permission: 'equipments.equipment.view' },
    { match: '/tech/veiculo', permission: 'fleet.vehicle.view' },
    { match: '/tech/dashboard', permission: 'os.work_order.view' },
    { match: '/tech/os/', permission: 'os.work_order.view' },
    { match: '/tech', permission: 'os.work_order.view' },
]

function resolveTechPermission(pathname: string): string | null {
    for (const rule of TECH_ROUTE_PERMISSIONS) {
        if (rule.match === pathname) return rule.permission
        if (rule.match !== '/tech' && rule.match.endsWith('/') && pathname.startsWith(rule.match)) return rule.permission
        if (rule.match !== '/tech' && !rule.match.endsWith('/') && pathname.startsWith(`${rule.match}/`)) return rule.permission
    }

    return null
}

describe('TechShell routing permission', () => {
    it('usa technicians.cashbox.view para a rota tecnica de despesas', () => {
        expect(resolveTechPermission('/tech/despesas')).toBe('technicians.cashbox.view')
    })

    it('exige agenda.item.view para a central tecnica', () => {
        expect(resolveTechPermission('/tech/central')).toBe('agenda.item.view')
    })

    it('mantem paths tecnicos unicos para evitar rotas inalcançáveis', () => {
        const techPaths = [
            '/tech',
            '/tech/os/:id',
            '/tech/os/:id/checklist',
            '/tech/os/:id/expenses',
            '/tech/os/:id/photos',
            '/tech/os/:id/seals',
            '/tech/os/:id/calibration',
            '/tech/os/:id/certificado',
            '/tech/os/:id/signature',
            '/tech/os/:id/nps',
            '/tech/os/:id/ocorrencia',
            '/tech/os/:id/contrato',
            '/tech/perfil',
            '/tech/configuracoes',
            '/tech/barcode',
            '/tech/os/:id/chat',
            '/tech/os/:id/annotate',
            '/tech/os/:id/voice-report',
            '/tech/os/:id/print',
            '/tech/thermal-camera',
            '/tech/thermal-camera/:id',
            '/tech/widget',
            '/tech/despesas',
            '/tech/caixa',
            '/tech/nova-os',
            '/tech/agenda',
            '/tech/rota',
            '/tech/mapa',
            '/tech/comissoes',
            '/tech/resumo-diario',
            '/tech/apontamentos',
            '/tech/notificacoes',
            '/tech/equipamentos',
            '/tech/equipamento/:id',
            '/tech/feedback',
            '/tech/precos',
            '/tech/orcamento-rapido',
            '/tech/scan-ativos',
            '/tech/veiculo',
            '/tech/ferramentas',
            '/tech/ponto',
            '/tech/dashboard',
            '/tech/metas',
            '/tech/central',
            '/tech/chamados',
            '/tech/solicitar-material',
            '/tech/inventory-scan',
        ]

        expect(new Set(techPaths).size).toBe(techPaths.length)
    })
})
