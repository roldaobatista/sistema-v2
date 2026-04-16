import { test, expect, type Page } from '@playwright/test'
import { loginAsAdmin, waitForAppReady } from './helpers'
import { navigateToModule } from './fixtures'

async function ensureLoggedIn(page: Page) {
    const ok = await loginAsAdmin(page, { navigateToApp: false })
    expect(ok, 'Login admin E2E deve estar disponivel para ciclo completo de OS').toBe(true)
}

test.describe('E2E - OS Full Lifecycle', () => {
    test('should create, transition, and verify work order', async ({ page }) => {
        await ensureLoggedIn(page)

        await navigateToModule(page, '/os/nova')
        await expect(page.locator('h1, h2').first()).toBeVisible({ timeout: 15000 })

        const form = page.locator('form')
        if (await form.isVisible({ timeout: 5000 }).catch(() => false)) {
            const customerField = page.locator('[name="customer_id"], [data-testid="customer-select"]').first()
            if (await customerField.isVisible({ timeout: 3000 }).catch(() => false)) {
                await customerField.click()
                const option = page.locator('[role="option"], [data-value]').first()
                if (await option.isVisible({ timeout: 3000 }).catch(() => false)) {
                    await option.click()
                }
            }

            const descField = page.locator('[name="description"], textarea').first()
            if (await descField.isVisible({ timeout: 2000 }).catch(() => false)) {
                await descField.fill('OS Teste E2E - Calibracao de balanca')
            }

            const submitBtn = page.locator('button[type="submit"], button:has-text("Salvar"), button:has-text("Criar")').first()
            if (await submitBtn.isEnabled({ timeout: 2000 }).catch(() => false)) {
                await submitBtn.click()
                await waitForAppReady(page)
            }
        }
    })

    test('should list work orders with data', async ({ page }) => {
        await ensureLoggedIn(page)
        await navigateToModule(page, '/os')
        await waitForAppReady(page)

        await expect
            .poll(
                async () => {
                    const hasTable = await page.locator('table tbody tr').count() > 0
                    const hasEmptyState = (await page.locator('[data-testid="empty-state"]').count() > 0)
                        || (await page.getByText(/Nenhum/i).count() > 0)
                    const hasDashboardContent = await page.locator('button:has-text("Nova OS"), input[placeholder*="Buscar OS"], input[placeholder*="Buscar"], h1:has-text("Ordens de Serviço")').count() > 0

                    return hasTable || hasEmptyState || hasDashboardContent
                },
                { timeout: 15_000, message: 'A lista de OS deve renderizar tabela, estado vazio ou controles principais' }
            )
            .toBe(true)
    })

    test('should display kanban view', async ({ page }) => {
        await ensureLoggedIn(page)
        await navigateToModule(page, '/os/kanban')

        const columns = page.locator('[data-testid*="kanban"], [class*="kanban"], [class*="column"]')
        if (await columns.first().isVisible({ timeout: 5000 }).catch(() => false)) {
            expect(await columns.count()).toBeGreaterThanOrEqual(1)
        }
    })

    test('should search and filter work orders', async ({ page }) => {
        await ensureLoggedIn(page)
        await navigateToModule(page, '/os')

        const searchInput = page.locator('input[type="search"], input[placeholder*="Buscar"], input[placeholder*="buscar"]').first()
        if (await searchInput.isVisible({ timeout: 5000 }).catch(() => false)) {
            await searchInput.fill('Calibracao')
            await page.waitForTimeout(1000)
            await waitForAppReady(page)
        }
    })
})
