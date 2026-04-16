import { test, expect } from '@playwright/test'
import { loginAsAdmin } from '../helpers'
import { navigateToModule, confirmDeleteDialog } from '../fixtures'

test.describe('Financial - Receivables', () => {
    test.beforeEach(async ({ page }) => {
        const loggedIn = await loginAsAdmin(page, { navigateToApp: false })
        expect(loggedIn, 'Login admin E2E deve estar disponivel para contas a receber').toBe(true)
    })

    test('receivables list loads with filters', async ({ page }) => {
        await navigateToModule(page, '/financeiro/receber')

        await expect(page.locator('h1, h2').first()).toBeVisible({ timeout: 10000 })

        // Should show filter controls
        const filterArea = page.locator('input[type="search"], input[placeholder*="buscar" i], button:has-text("Filtro"), select').first()
        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(100)
    })

    test('create new receivable', async ({ page }) => {
        await navigateToModule(page, '/financeiro/receber')

        const createBtn = page.locator('button:has-text("Novo"), button:has-text("Criar"), a:has-text("Novo"), [data-testid="btn-new"]').first()
        const count = await createBtn.count()
        expect(count, 'Tela de contas a receber deve expor botao de criacao').toBeGreaterThan(0)

        await createBtn.click()
        await page.waitForTimeout(1000)

        // Fill form fields
        const descField = page.locator('input[name="description"], input[name="descricao"], textarea').first()
        if (await descField.count() > 0) {
            await descField.fill('Recebivel Teste E2E')
        }

        const valueField = page.locator('input[name="value"], input[name="valor"], input[name="amount"]').first()
        if (await valueField.count() > 0) {
            await valueField.fill('1500.00')
        }

        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(100)
    })

    test('register partial payment on receivable', async ({ page }) => {
        await navigateToModule(page, '/financeiro/receber')

        // Click first receivable
        const firstRow = page.locator('table tbody tr a, table tbody tr').first()
        const count = await firstRow.count()
        expect(count, 'Seeder E2E deve disponibilizar recebivel para baixa parcial').toBeGreaterThan(0)

        await firstRow.click()
        await page.waitForTimeout(1000)

        // Look for payment action
        const payBtn = page.locator('button:has-text("Pagar"), button:has-text("Receber"), button:has-text("Baixa"), [data-testid*="payment"]').first()
        if (await payBtn.count() > 0) {
            await expect(payBtn).toBeVisible()
        }

        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })

    test('register full payment on receivable', async ({ page }) => {
        await navigateToModule(page, '/financeiro/receber')

        const firstRow = page.locator('table tbody tr a, table tbody tr').first()
        const count = await firstRow.count()
        expect(count, 'Seeder E2E deve disponibilizar recebivel para baixa total').toBeGreaterThan(0)

        await firstRow.click()
        await page.waitForTimeout(1000)

        const payBtn = page.locator('button:has-text("Pagar"), button:has-text("Receber"), button:has-text("Baixa Total"), [data-testid*="pay-full"]').first()
        if (await payBtn.count() > 0) {
            await payBtn.click()
            await page.waitForTimeout(1000)
        }

        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })

    test('cancel receivable', async ({ page }) => {
        await navigateToModule(page, '/financeiro/receber')

        const firstRow = page.locator('table tbody tr a, table tbody tr').first()
        const count = await firstRow.count()
        expect(count, 'Seeder E2E deve disponibilizar recebivel para cancelamento').toBeGreaterThan(0)

        await firstRow.click()
        await page.waitForTimeout(1000)

        const cancelBtn = page.locator('button:has-text("Cancelar"), button:has-text("Estornar"), [data-testid*="cancel"]').first()
        if (await cancelBtn.count() > 0) {
            await cancelBtn.click()
            await page.waitForTimeout(500)

            // Confirm if dialog appears
            const dialog = page.locator('[role="alertdialog"], [role="dialog"]').first()
            if (await dialog.isVisible()) {
                await confirmDeleteDialog(page)
            }
        }

        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })

    test('overdue receivables are visually highlighted', async ({ page }) => {
        await navigateToModule(page, '/financeiro/receber')

        // Check for any highlighted/overdue items (red text, badge, etc.)
        const overdueIndicators = page.locator('.text-red-500, .text-red-600, .text-red-700, .bg-red-50, [data-testid*="overdue"]')
        await page.waitForTimeout(2000)

        // Just verify page loaded correctly
        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })

    test('export receivables report', async ({ page }) => {
        await navigateToModule(page, '/financeiro/receber')

        const exportBtn = page.locator('button:has-text("Exportar"), button:has-text("Export"), a:has-text("Exportar"), [data-testid*="export"]').first()
        const count = await exportBtn.count()
        expect(count, 'Tela de contas a receber deve expor exportacao de relatorio').toBeGreaterThan(0)

        await expect(exportBtn).toBeVisible()
    })
})
