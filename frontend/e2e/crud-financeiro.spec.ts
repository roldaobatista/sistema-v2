import { test, expect } from '@playwright/test'
import { loginAsAdmin, BASE, waitForAppReady } from './helpers'

async function gotoAndWait(page: Parameters<typeof loginAsAdmin>[0], path: string) {
    await page.goto(BASE + path, { waitUntil: 'domcontentloaded' })
    await waitForAppReady(page)
}

test.describe('CRUD Financeiro', () => {
    test('contas a receber deve carregar', async ({ page }) => {
        const loggedIn = await loginAsAdmin(page)
        expect(loggedIn, 'Login admin E2E deve estar disponivel para contas a receber').toBe(true)

        await gotoAndWait(page, '/financeiro/receber')
        await expect(page.locator('h1, h2').first()).toBeVisible({ timeout: 10000 })
    })

    test('contas a pagar deve carregar', async ({ page }) => {
        const loggedIn = await loginAsAdmin(page)
        expect(loggedIn, 'Login admin E2E deve estar disponivel para contas a pagar').toBe(true)

        await gotoAndWait(page, '/financeiro/pagar')
        await expect(page.locator('h1, h2').first()).toBeVisible({ timeout: 10000 })
    })

    test('despesas deve carregar', async ({ page }) => {
        const loggedIn = await loginAsAdmin(page)
        expect(loggedIn, 'Login admin E2E deve estar disponivel para despesas').toBe(true)

        await gotoAndWait(page, '/financeiro/despesas')
        await expect(page.locator('h1, h2').first()).toBeVisible({ timeout: 10000 })
    })

    test('fluxo de caixa deve carregar', async ({ page }) => {
        const loggedIn = await loginAsAdmin(page)
        expect(loggedIn, 'Login admin E2E deve estar disponivel para fluxo de caixa').toBe(true)

        await gotoAndWait(page, '/financeiro/fluxo-caixa')
        await expect(page.locator('h1, h2').first()).toBeVisible({ timeout: 10000 })
    })

    test('formas de pagamento deve carregar', async ({ page }) => {
        const loggedIn = await loginAsAdmin(page)
        expect(loggedIn, 'Login admin E2E deve estar disponivel para formas de pagamento').toBe(true)

        await gotoAndWait(page, '/financeiro/formas-pagamento')
        await expect(page.locator('h1, h2').first()).toBeVisible({ timeout: 10000 })
    })

    test('comissoes deve carregar', async ({ page }) => {
        const loggedIn = await loginAsAdmin(page)
        expect(loggedIn, 'Login admin E2E deve estar disponivel para comissoes').toBe(true)

        await gotoAndWait(page, '/financeiro/comissoes')
        await expect(page.locator('h1, h2').first()).toBeVisible({ timeout: 10000 })
    })
})
