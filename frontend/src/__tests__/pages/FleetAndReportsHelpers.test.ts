import { describe, expect, it } from 'vitest'
import { formatAccidentDate } from '@/pages/fleet/components/FleetAccidentsTab'
import { getTireAlertState } from '@/pages/fleet/components/FleetTiresTab'
import { getReportExportErrorMessage } from '@/pages/relatorios/ReportsPage'

describe('fleet and reports helper regressions', () => {
    it('returns fallback for invalid accident dates', () => {
        expect(formatAccidentDate(undefined)).toBe('—')
        expect(formatAccidentDate('not-a-date')).toBe('—')
    })

    it('classifies tire tread depth thresholds safely', () => {
        expect(getTireAlertState(null)).toBe('missing')
        expect(getTireAlertState(2.9)).toBe('critical')
        expect(getTireAlertState(3)).toBe('warning')
        expect(getTireAlertState(5)).toBe('ok')
    })

    it('prefers API message and protects forbidden export feedback', () => {
        expect(getReportExportErrorMessage({ response: { status: 403 } })).toBe('Sem permissão para exportar este relatório.')
        expect(getReportExportErrorMessage({ response: { status: 422, data: { message: 'Filtro inválido' } } })).toBe('Filtro inválido')
        expect(getReportExportErrorMessage({ response: { status: 500, data: { error: 'Falha genérica' } } })).toBe('Falha genérica')
        expect(getReportExportErrorMessage({})).toBe('Erro ao exportar relatório. Tente novamente.')
    })
})
