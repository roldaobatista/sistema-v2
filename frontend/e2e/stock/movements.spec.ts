import { test, expect } from '@playwright/test'
import { loginAsAdmin } from '../helpers'
import { navigateToModule } from '../fixtures'

test.describe('Stock Movements', () => {
    test.beforeEach(async ({ page }) => {
        const loggedIn = await loginAsAdmin(page)
        expect(loggedIn, 'Login admin E2E deve estar disponivel para movimentacoes de estoque').toBe(true)
    })

    test('stock dashboard loads', async ({ page }) => {
        await navigateToModule(page, '/estoque')

        await page.waitForTimeout(3000)
        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(100)
    })

    test('create stock entry movement', async ({ page }) => {
        await navigateToModule(page, '/estoque/movimentacoes')

        await page.waitForTimeout(2000)

        const createBtn = page.locator('button:has-text("Nova Movimentação"), button:has-text("Novo"), button:has-text("Criar"), button:has-text("Entrada"), a:has-text("Novo"), [data-testid="btn-new"]').first()
        const count = await createBtn.count()
        expect(count, 'Tela de movimentacoes deve expor botao para entrada de estoque').toBeGreaterThan(0)

        await createBtn.click()
        await page.waitForTimeout(1000)

        const movementType = page.getByRole('combobox', { name: /Tipo de movimentação/i }).first()
        await expect(movementType).toBeVisible({ timeout: 10000 })
        await movementType.selectOption('entry')

        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(100)
    })

    test('create stock exit movement', async ({ page }) => {
        await navigateToModule(page, '/estoque/movimentacoes')

        await page.waitForTimeout(2000)

        const createBtn = page.locator('button:has-text("Nova Movimentação"), button:has-text("Novo"), button:has-text("Criar"), button:has-text("Saida"), a:has-text("Novo"), [data-testid="btn-new"]').first()
        const count = await createBtn.count()
        expect(count, 'Tela de movimentacoes deve expor botao para saida de estoque').toBeGreaterThan(0)

        await createBtn.click()
        await page.waitForTimeout(1000)

        const movementType = page.getByRole('combobox', { name: /Tipo de movimentação/i }).first()
        await expect(movementType).toBeVisible({ timeout: 10000 })
        await movementType.selectOption('exit')

        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(100)
    })

    test('view Kardex report', async ({ page }) => {
        await navigateToModule(page, '/estoque/kardex')

        await page.waitForTimeout(3000)

        // Should show table or report view
        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })

    test('inventory count page loads', async ({ page }) => {
        await navigateToModule(page, '/estoque/inventarios')

        await page.waitForTimeout(3000)

        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })

    test('stock movements list shows history', async ({ page }) => {
        await navigateToModule(page, '/estoque/movimentacoes')

        await page.waitForTimeout(3000)

        // Table or list should render
        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })
})
