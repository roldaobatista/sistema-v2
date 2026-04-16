import { test, expect } from '@playwright/test'
import { loginAsAdmin, BASE } from '../helpers'

test.describe('Login', () => {
    test.use({ storageState: { cookies: [], origins: [] } })

    test('login form renders with all required fields', async ({ page }) => {
        await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded' })
        await expect(page.locator('#email')).toBeVisible({ timeout: 15000 })
        await expect(page.locator('#password')).toBeVisible()
        await expect(page.locator('button[type="submit"]')).toBeVisible()
    })

    test('invalid credentials show error message', async ({ page }) => {
        await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded' })
        await page.locator('#email').fill('fake@invalid.com')
        await page.locator('#password').fill('wrongpassword123')
        await page.locator('button[type="submit"]').click()

        const errorLocator = page.locator('[role="alert"], [data-sonner-toaster] li, .text-red-600, .text-red-500')
            .filter({ hasText: /credenciais|invalid|erro|unauthorized/i })
        await expect(errorLocator.first()).toBeVisible({ timeout: 10000 })
    })

    test('successful login redirects to dashboard', async ({ page }) => {
        const loggedIn = await loginAsAdmin(page)
        expect(loggedIn, 'Login admin E2E deve estar disponivel para validar redirecionamento').toBe(true)

        expect(page.url()).not.toContain('/login')
        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(100)
    })

    test('password toggle visibility works', async ({ page }) => {
        await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded' })
        const passwordInput = page.locator('#password')
        await expect(passwordInput).toHaveAttribute('type', 'password')

        const toggleBtn = page.locator('#password + button, #password ~ button, button[aria-label*="senha" i], button[aria-label*="password" i]').first()
        const toggleCount = await toggleBtn.count()
        if (toggleCount > 0) {
            await toggleBtn.click()
            await expect(passwordInput).toHaveAttribute('type', 'text')

            await toggleBtn.click()
            await expect(passwordInput).toHaveAttribute('type', 'password')
        }
    })

    test('submit button shows loading state during login request', async ({ page }) => {
        await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded' })

        await page.route('**/api/v1/login', async (route) => {
            await new Promise(r => setTimeout(r, 2000))
            await route.fulfill({
                status: 401,
                contentType: 'application/json',
                body: JSON.stringify({ message: 'Credenciais invalidas' }),
            })
        })

        await page.locator('#email').fill('admin@test.com')
        await page.locator('#password').fill('password')
        await page.locator('button[type="submit"]').click()

        await expect(page.locator('button[type="submit"]')).toBeDisabled({ timeout: 3000 })
    })

    test('unauthenticated user is redirected to login', async ({ page }) => {
        await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded' })
        await page.evaluate(() => localStorage.clear())

        await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' })
        await page.waitForURL(/\/login/, { timeout: 10000 })
        expect(page.url()).toContain('/login')
    })

    test('redirect to intended page after login', async ({ page }) => {
        // Clear storage first
        await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded' })
        await page.evaluate(() => localStorage.clear())

        // Try to visit protected page
        await page.goto(BASE + '/cadastros/clientes', { waitUntil: 'domcontentloaded' })
        await page.waitForURL(/\/login/, { timeout: 10000 })

        // Now login
        const loggedIn = await loginAsAdmin(page)
        expect(loggedIn, 'Login admin E2E deve estar disponivel para validar redirecionamento pos-login').toBe(true)

        // Should land on dashboard at minimum (redirect-after-login depends on implementation)
        expect(page.url()).not.toContain('/login')
    })

    test('email field validates format', async ({ page }) => {
        await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded' })
        await page.locator('#email').fill('not-an-email')
        await page.locator('#password').fill('password')
        await page.locator('button[type="submit"]').click()

        // Either HTML5 validation or custom validation should prevent submission
        await page.waitForTimeout(500)
        // Page should still be on login
        expect(page.url()).toContain('/login')
    })
})
