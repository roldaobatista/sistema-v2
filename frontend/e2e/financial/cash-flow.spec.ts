import { test, expect } from '@playwright/test'
import { loginAsAdmin } from '../helpers'
import { navigateToModule } from '../fixtures'

test.describe('Financial - Cash Flow', () => {
    test.beforeEach(async ({ page }) => {
        const loggedIn = await loginAsAdmin(page)
        expect(loggedIn, 'Login admin E2E deve estar disponivel para fluxo de caixa').toBe(true)
    })

    test('cash flow dashboard loads with chart', async ({ page }) => {
        await navigateToModule(page, '/financeiro/fluxo-caixa')

        await expect(page.locator('h1, h2').first()).toBeVisible({ timeout: 10000 })

        // Wait for chart to load
        await page.waitForTimeout(3000)

        // Chart container should be visible (canvas, svg, or recharts wrapper)
        const chartElement = page.locator('canvas, svg.recharts-surface, [data-testid*="chart"], .recharts-wrapper').first()
        await expect(chartElement, 'Fluxo de caixa deve renderizar grafico').toBeVisible()
        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(100)
    })

    test('filter cash flow by period', async ({ page }) => {
        await navigateToModule(page, '/financeiro/fluxo-caixa')

        // Look for period filter (date range, select, or buttons)
        const periodFilter = page.locator('select, button:has-text("Periodo"), button:has-text("Mes"), input[type="date"], [data-testid*="period"]').first()
        await expect(periodFilter, 'Fluxo de caixa deve expor filtro de periodo').toBeVisible()

        await periodFilter.click()
        await page.waitForTimeout(500)

        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })

    test('cash flow exposes income and expense summaries', async ({ page }) => {
        await navigateToModule(page, '/financeiro/fluxo-caixa')
        await page.waitForTimeout(2000)

        await expect(page.getByText('Receitas').first(), 'Fluxo de caixa deve exibir resumo de receitas').toBeVisible()
        await expect(page.getByText('Despesas').first(), 'Fluxo de caixa deve exibir resumo de despesas').toBeVisible()
        await expect(page.getByText('Saldo caixa').first(), 'Fluxo de caixa deve exibir saldo de caixa').toBeVisible()

        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })

    test('export cash flow data', async ({ page }) => {
        await navigateToModule(page, '/financeiro/fluxo-caixa')

        const exportBtn = page.locator('button:has-text("Exportar"), button:has-text("Export"), [data-testid*="export"]').first()
        await expect(exportBtn).toBeVisible()
    })

    test('weekly cash flow view loads', async ({ page }) => {
        await navigateToModule(page, '/financeiro/fluxo-caixa-semanal')

        await page.waitForTimeout(3000)
        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })
})
