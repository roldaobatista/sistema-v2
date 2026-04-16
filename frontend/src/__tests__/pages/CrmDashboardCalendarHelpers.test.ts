import { describe, expect, it } from 'vitest'

import { getDashboardTopCustomerName } from '@/pages/CrmDashboardPage'
import { isManagedCalendarEvent } from '@/pages/crm/CrmCalendarPage'

describe('CRM dashboard and calendar helpers', () => {
    it('usa customer_name como fallback no dashboard', () => {
        expect(getDashboardTopCustomerName({
            customer_id: 7,
            customer_name: 'Cliente Estrategico',
            total_value: 1200,
            deal_count: 2,
            customer: null,
        })).toBe('Cliente Estrategico')
    })

    it('permite gerenciar apenas eventos reais do calendario', () => {
        expect(isManagedCalendarEvent({
            id: 10,
            title: 'Evento',
            type: 'meeting',
            start_at: '2026-03-14T10:00:00Z',
            end_at: '2026-03-14T11:00:00Z',
        })).toBe(true)

        expect(isManagedCalendarEvent({
            id: 'activity-10',
            title: 'Atividade',
            type: 'activity',
            start_at: '2026-03-14T10:00:00Z',
            end_at: '2026-03-14T11:00:00Z',
            is_activity: true,
        })).toBe(false)

        expect(isManagedCalendarEvent({
            id: 'renewal-10',
            title: 'Renovacao',
            type: 'contract_renewal',
            start_at: '2026-03-14',
            end_at: '2026-03-14',
            is_renewal: true,
        })).toBe(false)
    })
})
