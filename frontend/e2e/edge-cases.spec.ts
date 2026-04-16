import { test, expect } from '@playwright/test'

const BASE = 'http://localhost:3000'

test.describe('Edge Cases', () => {
    test.use({ storageState: { cookies: [], origins: [] } })

    test('formulario de login vazio nao deve submeter', async ({ page }) => {
        await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded' })
        await expect(page.locator('input#email')).toBeVisible({ timeout: 10_000 })

        await page.click('button[type="submit"]')

        expect(page.url()).toContain('/login')

        const emailInput = page.locator('input#email')
        await expect(emailInput).toHaveAttribute('required', '')
    })

    test('duplo submit deve ser prevenido (botao desabilitado durante loading)', async ({ page }) => {
        await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded' })
        await expect(page.locator('input#email')).toBeVisible({ timeout: 10_000 })
        await page.fill('input#email', 'test@test.com')
        await page.fill('input#password', 'password')

        await page.route('**/api/v1/login', async (route) => {
            await new Promise(resolve => setTimeout(resolve, 2000))
            await route.fulfill({ status: 200, body: JSON.stringify({ token: 'test' }) })
        })

        await page.click('button[type="submit"]')
        await expect(page.locator('button[type="submit"]')).toBeDisabled()
    })

    test('token invalido deve limpar estado e redirecionar', async ({ page }) => {
        await page.goto(BASE + '/login')

        await page.evaluate(() => {
            localStorage.setItem('auth_token', 'invalid-garbage-token')
            localStorage.setItem('auth-store', JSON.stringify({
                state: { token: 'invalid-garbage-token', isAuthenticated: true },
                version: 0,
            }))
        })

        await page.route('**/api/v1/me', async route => {
            await route.fulfill({ status: 401, json: { message: 'Unauthenticated.' } })
        })

        await page.goto(BASE + '/')
        await page.waitForTimeout(5000)

        const url = page.url()
        const isOnLogin = url.includes('/login')
        const hasError = await page.locator('text=/erro|expirad|sessao|sessão|unauthorized/i').count() > 0
        expect(isOnLogin || hasError).toBeTruthy()
    })

    test('rota inexistente deve redirecionar para /', async ({ page }) => {
        await page.goto(BASE + '/login')
        await page.evaluate(() => localStorage.clear())

        await page.goto(BASE + '/rota-que-nao-existe-abc123')
        await page.waitForTimeout(2000)

        expect(page.url()).toContain('/login')
    })

    test('API offline deve mostrar feedback gracioso (sem crash)', async ({ page }) => {
        await page.goto(BASE + '/login')

        await page.route('**/api/**', route => route.abort('connectionrefused'))

        await page.fill('input#email', 'test@test.com')
        await page.fill('input#password', 'password')
        await page.click('button[type="submit"]')

        await page.waitForTimeout(2000)

        const body = await page.textContent('body')
        expect(body?.length).toBeGreaterThan(10)

        const hasError = await page.locator('text=/erro|server|conexao|conexão|conectar/i').count() > 0
        expect(hasError).toBeTruthy()
    })

    test('localStorage limpo deve redirecionar para login', async ({ page }) => {
        await page.goto(BASE + '/login')
        await page.evaluate(() => localStorage.clear())

        await page.goto(BASE + '/')
        await page.waitForURL(/\/login/, { timeout: 5000 })
        expect(page.url()).toContain('/login')
    })
})
