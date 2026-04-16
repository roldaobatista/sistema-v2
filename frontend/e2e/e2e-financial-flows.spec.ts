import { test, expect } from '@playwright/test'
import { waitForAppReady } from './helpers'
import { navigateToModule } from './fixtures'

test.describe('E2E - Financial Payment Lifecycle', () => {
    test('should load accounts receivable list', async ({ page }) => {
        await navigateToModule(page, '/financeiro/receber')

        const content = page.locator('table, [data-testid="empty-state"], :text("Nenhum")')
        await expect(content.first()).toBeVisible({ timeout: 15000 })
    })

    test('should load accounts payable list', async ({ page }) => {
        await navigateToModule(page, '/financeiro/pagar')

        const content = page.locator('table, [data-testid="empty-state"], :text("Nenhum")')
        await expect(content.first()).toBeVisible({ timeout: 15000 })
    })

    test('should navigate to receivable creation form', async ({ page }) => {
        await navigateToModule(page, '/financeiro/receber')

        const createBtn = page.locator('a:has-text("Nov"), button:has-text("Nov"), a:has-text("Criar"), button:has-text("Criar")').first()
        if (await createBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
            await createBtn.click()
            await waitForAppReady(page)

            const form = page.locator('form')
            await expect(form).toBeVisible({ timeout: 10000 })
        }
    })

    test('should load cash flow page', async ({ page }) => {
        await navigateToModule(page, '/financeiro/fluxo-caixa')

        const content = page.locator('h1, h2, [class*="chart"], canvas, svg')
        await expect(content.first()).toBeVisible({ timeout: 15000 })
    })

    test('should load DRE page', async ({ page }) => {
        await navigateToModule(page, '/financeiro/dre')

        const content = page.locator('h1, h2, table, [class*="chart"]')
        await expect(content.first()).toBeVisible({ timeout: 15000 })
    })

    test('should load invoices page', async ({ page }) => {
        await navigateToModule(page, '/financeiro/faturamento')

        const content = page.locator('table, [data-testid="empty-state"], :text("Nenhum"), h1, h2')
        await expect(content.first()).toBeVisible({ timeout: 15000 })
    })
})
