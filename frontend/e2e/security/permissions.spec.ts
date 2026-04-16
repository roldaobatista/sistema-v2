import { test, expect } from '@playwright/test'
import { loginAsAdmin, BASE, waitForAppReady } from '../helpers'
import { navigateToModule } from '../fixtures'

test.describe('Security - Permissions', () => {
    test('unauthenticated user is redirected to login from dashboard', async ({ page }) => {
        await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded' })
        await page.evaluate(() => localStorage.clear())

        await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' })
        await page.waitForTimeout(3000)
        expect(page.url()).toContain('/login')
    })

    test('unauthenticated user is redirected from protected route', async ({ page }) => {
        await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded' })
        await page.evaluate(() => localStorage.clear())

        await page.goto(BASE + '/cadastros/clientes', { waitUntil: 'domcontentloaded' })
        await page.waitForTimeout(3000)
        expect(page.url()).toContain('/login')
    })

    test('unauthenticated user is redirected from financial route', async ({ page }) => {
        await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded' })
        await page.evaluate(() => localStorage.clear())

        await page.goto(BASE + '/financeiro/receber', { waitUntil: 'domcontentloaded' })
        await page.waitForTimeout(3000)
        expect(page.url()).toContain('/login')
    })

    test('authenticated admin can access dashboard', async ({ page }) => {
        const loggedIn = await loginAsAdmin(page, { navigateToApp: false })
        expect(loggedIn, 'Login admin E2E deve estar disponivel para validar permissoes').toBe(true)

        await navigateToModule(page, '/')
        await waitForAppReady(page)
        expect(page.url()).not.toContain('/login')

        await expect
            .poll(
                async () => (await page.textContent('body'))?.length ?? 0,
                { timeout: 15_000, message: 'Dashboard autenticado deve renderizar conteudo' }
            )
            .toBeGreaterThan(100)
    })

    test('admin sidebar shows IAM section for super_admin', async ({ page }) => {
        const loggedIn = await loginAsAdmin(page, { navigateToApp: false })
        expect(loggedIn, 'Login admin E2E deve estar disponivel para validar menu IAM').toBe(true)

        await navigateToModule(page, '/')
        await waitForAppReady(page)

        await expect
            .poll(
                async () => await page.locator('nav').first().textContent(),
                { timeout: 15_000, message: 'Menu administrativo deve renderizar secao IAM/configuracoes' }
            )
            .toMatch(/Gestão IAM|Configurações de Acesso|Usuários|Roles/i)
    })

    test('portal routes redirect to portal login when unauthenticated', async ({ page }) => {
        await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded' })
        await page.evaluate(() => localStorage.clear())

        await page.goto(BASE + '/portal', { waitUntil: 'domcontentloaded' })
        await page.waitForTimeout(3000)

        const url = page.url()
        expect(url.includes('/portal/login') || url.includes('/login')).toBeTruthy()
    })
})
