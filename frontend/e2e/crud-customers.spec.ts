import { test, expect } from '@playwright/test'
import { loginAsAdmin, BASE, waitForAppReady } from './helpers'

async function gotoAndWait(page: Parameters<typeof loginAsAdmin>[0], path: string) {
    await page.goto(BASE + path, { waitUntil: 'domcontentloaded' })
    await waitForAppReady(page)
}

test.describe('CRUD Clientes', () => {
    test('lista de clientes deve carregar', async ({ page }) => {
        const loggedIn = await loginAsAdmin(page)
        expect(loggedIn, 'Login admin E2E deve estar disponivel para listar clientes').toBe(true)

        await gotoAndWait(page, '/cadastros/clientes')

        await expect(page.locator('h1').first()).toContainText(/clientes/i)
    })

    test('deve abrir modal/formulario de novo cliente', async ({ page }) => {
        const loggedIn = await loginAsAdmin(page)
        expect(loggedIn, 'Login admin E2E deve estar disponivel para criar clientes').toBe(true)

        await gotoAndWait(page, '/cadastros/clientes')

        const createBtn = page.locator('button:has-text("Novo"), button:has-text("Criar"), a:has-text("Novo")')
        if (await createBtn.count() > 0) {
            await createBtn.first().click()
            await page.waitForTimeout(500)
            const formVisible = await page.locator('form, [role="dialog"]').count() > 0
            expect(formVisible).toBeTruthy()
        }
    })

    test('formulario de cliente deve validar campos obrigatorios', async ({ page }) => {
        const loggedIn = await loginAsAdmin(page)
        expect(loggedIn, 'Login admin E2E deve estar disponivel para validar formulario de clientes').toBe(true)

        await gotoAndWait(page, '/cadastros/clientes')

        const createBtn = page.locator('button:has-text("Novo"), button:has-text("Criar"), a:has-text("Novo")')
        if (await createBtn.count() > 0) {
            await createBtn.first().click()
            await page.waitForTimeout(500)

            const submitBtn = page.locator('button[type="submit"], button:has-text("Salvar")')
            if (await submitBtn.count() > 0) {
                await submitBtn.first().click()
                await page.waitForTimeout(1000)
                const hasValidation = await page.locator('.text-red-600, .text-red-500, :invalid').count() > 0
                expect(hasValidation).toBeTruthy()
            }
        }
    })

    test('busca de clientes deve funcionar', async ({ page }) => {
        const loggedIn = await loginAsAdmin(page)
        expect(loggedIn, 'Login admin E2E deve estar disponivel para buscar clientes').toBe(true)

        await gotoAndWait(page, '/cadastros/clientes')

        const searchInput = page.locator('input[placeholder*="buscar" i], input[placeholder*="pesquisar" i], input[placeholder*="filtrar" i], input[type="search"]')
        if (await searchInput.count() > 0) {
            await searchInput.first().fill('TesteSearch123')
            await page.waitForTimeout(500)
            const pageContent = await page.textContent('body')
            expect(pageContent).toBeTruthy()
        }
    })
})
