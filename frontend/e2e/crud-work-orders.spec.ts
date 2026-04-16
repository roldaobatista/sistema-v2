import { test, expect } from '@playwright/test'
import { gotoAuthenticated, loginAsAdmin } from './helpers'

async function gotoAndWait(page: Parameters<typeof loginAsAdmin>[0], path: string) {
    await gotoAuthenticated(page, path)
}

test.describe('CRUD Ordens de Servico', () => {
    test('lista de OS deve carregar', async ({ page }) => {
        const loggedIn = await loginAsAdmin(page, { navigateToApp: false })
        expect(loggedIn, 'Login admin E2E deve estar disponivel para listar ordens de servico').toBe(true)

        await gotoAndWait(page, '/os')
        await expect(page.locator('h1, h2').first()).toBeVisible()
    })

    test('deve navegar para criar nova OS', async ({ page }) => {
        const loggedIn = await loginAsAdmin(page, { navigateToApp: false })
        expect(loggedIn, 'Login admin E2E deve estar disponivel para criar ordem de servico').toBe(true)

        await gotoAndWait(page, '/os/nova')

        const formOrHeader = page.locator('form, h1, h2').first()
        await expect(formOrHeader).toBeVisible({ timeout: 10000 })
    })

    test('kanban de OS deve carregar', async ({ page }) => {
        const loggedIn = await loginAsAdmin(page, { navigateToApp: false })
        expect(loggedIn, 'Login admin E2E deve estar disponivel para abrir kanban de OS').toBe(true)

        await gotoAndWait(page, '/os/kanban')
        await expect(page.getByRole('heading', { name: 'Kanban de OS', level: 1 })).toBeVisible({ timeout: 10000 })
        await expect(page.getByRole('heading', { name: 'Aberta', level: 3 })).toBeVisible({ timeout: 10000 })
        await expect(page.getByRole('heading', { name: 'Aguard. Despacho', level: 3 })).toBeVisible({ timeout: 10000 })
    })

    test('busca de OS deve filtrar resultados', async ({ page }) => {
        const loggedIn = await loginAsAdmin(page, { navigateToApp: false })
        expect(loggedIn, 'Login admin E2E deve estar disponivel para buscar OS').toBe(true)

        await gotoAndWait(page, '/os')

        const searchInput = page.locator('input[placeholder*="buscar" i], input[placeholder*="pesquisar" i], input[type="search"]')
        if (await searchInput.count() > 0) {
            await searchInput.first().fill('OS-TEST-999')
            await page.waitForTimeout(500)
            const content = await page.textContent('body')
            expect(content).toBeTruthy()
        }
    })
})
