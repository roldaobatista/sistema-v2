import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

const currentDir = dirname(fileURLToPath(import.meta.url))

function source(relativePath: string): string {
    return readFileSync(resolve(currentDir, '..', '..', relativePath), 'utf8')
}

describe('contratos estaticos de rotas backend/frontend', () => {
    it('usa rotas reais para limites de despesa e certificado fiscal', () => {
        const expenseLimits = source('pages/configuracoes/ExpenseLimitsConfigPage.tsx')
        expect(expenseLimits).not.toContain('/finance/expense-categories')
        expect(expenseLimits).toContain('/expense-categories')
        expect(expenseLimits).toContain('/expense-categories/batch-limits')

        const fiscalConfig = source('pages/fiscal/FiscalConfigPage.tsx')
        expect(fiscalConfig).not.toMatch(/api\.get\('\/fiscal\/config\/certificate'\)/)
        expect(fiscalConfig).toContain('/fiscal/config/certificate/status')
    })

    it('usa rotas reais para alertas globais', () => {
        const alerts = source('pages/alertas/AlertsPage.tsx')
        expect(alerts).not.toContain('api.put(`/alerts/${id}/acknowledge`)')
        expect(alerts).not.toContain('api.put(`/alerts/${id}/resolve`)')
        expect(alerts).not.toContain('api.put(`/alerts/${id}/dismiss`)')
        expect(alerts).not.toContain("api.post('/alerts/generate')")
        expect(alerts).toContain('api.post(`/alerts/${id}/acknowledge`)')
        expect(alerts).toContain('api.post(`/alerts/${id}/resolve`)')
        expect(alerts).toContain('api.post(`/alerts/${id}/dismiss`)')
        expect(alerts).toContain("api.post('/alerts/run-engine')")
    })

    it('usa rotas reais para selos de reparo/Inmetro', () => {
        const techSeals = source('pages/tech/TechSealsPage.tsx')
        expect(techSeals).not.toContain('/inventory/seals')
        expect(techSeals).toContain('/repair-seals/my-inventory')
        expect(techSeals).toContain('/repair-seals/use')

        const sealReport = source('pages/inmetro/InmetroSealReportPage.tsx')
        expect(sealReport).not.toContain('/inventory/seals')
        expect(sealReport).toContain('/repair-seals/dashboard')
        expect(sealReport).toContain('/repair-seals')
        expect(sealReport).toContain('/repair-seals/export')

        const sealManagement = source('pages/inmetro/InmetroSealManagement.tsx')
        expect(sealManagement).not.toContain('/inventory/seals')
        expect(sealManagement).toContain('/repair-seals')
        expect(sealManagement).toContain('/repair-seal-batches')
        expect(sealManagement).toContain('/repair-seals/assign')
    })

    it('usa rota real para solicitacoes de ajuste de ponto', () => {
        const clockCorrection = source('pages/tech/TechClockCorrectionPage.tsx')
        expect(clockCorrection).not.toContain('/hr/clock-adjustments')
        expect(clockCorrection).toContain('/hr/adjustments')
    })
})
