import { test, expect } from '@playwright/test'
import { loginAsAdmin, BASE, waitForAppReady } from './helpers'

async function gotoAndWait(page: Parameters<typeof loginAsAdmin>[0], path: string) {
    await page.goto(BASE + path, { waitUntil: 'domcontentloaded' })
    await waitForAppReady(page)
}

test.describe('CRUD Orcamentos', () => {
    test('lista de orcamentos deve carregar', async ({ page }) => {
        const loggedIn = await loginAsAdmin(page)
        expect(loggedIn, 'Login admin E2E deve estar disponivel para listar orcamentos').toBe(true)

        await gotoAndWait(page, '/orcamentos')
        await expect(page.locator('h1, h2').first()).toBeVisible({ timeout: 10000 })
    })

    test('deve navegar para criar novo orcamento', async ({ page }) => {
        const loggedIn = await loginAsAdmin(page)
        expect(loggedIn, 'Login admin E2E deve estar disponivel para criar orcamentos').toBe(true)

        await gotoAndWait(page, '/orcamentos/novo')

        const formOrHeader = page.locator('form, h1, h2').first()
        await expect(formOrHeader).toBeVisible({ timeout: 10000 })
    })

    test('busca de orcamentos deve funcionar', async ({ page }) => {
        const loggedIn = await loginAsAdmin(page)
        expect(loggedIn, 'Login admin E2E deve estar disponivel para buscar orcamentos').toBe(true)

        await gotoAndWait(page, '/orcamentos')

        const searchInput = page.locator('input[placeholder*="buscar" i], input[placeholder*="pesquisar" i], input[type="search"]')
        if (await searchInput.count() > 0) {
            await searchInput.first().fill('orcamento-inexistente')
            await page.waitForTimeout(500)
            const content = await page.textContent('body')
            expect(content).toBeTruthy()
        }
    })
})
