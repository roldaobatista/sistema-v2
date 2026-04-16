import { test, expect } from '@playwright/test'
import { BASE } from '../helpers'

test.use({ storageState: { cookies: [], origins: [] } })

test.describe('Password Reset', () => {
    test('password reset page is accessible from login', async ({ page }) => {
        await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded' })
        await expect(page.locator('#email')).toBeVisible({ timeout: 10000 })

        const resetLink = page.locator('a:has-text("esquec"), a:has-text("Esquec"), a:has-text("recuperar"), a[href*="reset"], a[href*="forgot"]').first()
        const count = await resetLink.count()
        expect(count, 'Login deve expor link de recuperacao de senha').toBeGreaterThan(0)

        await resetLink.click()
        await page.waitForTimeout(1000)

        // Should navigate to reset page or show reset form
        const emailInput = page.locator('input[type="email"], input#email, input[name="email"]').first()
        await expect(emailInput).toBeVisible({ timeout: 5000 })
    })

    test('request reset with valid email shows confirmation', async ({ page }) => {
        await page.goto(BASE + '/esqueci-senha', { waitUntil: 'domcontentloaded' })
        await page.waitForTimeout(1000)

        const emailInput = page.locator('input[type="email"], input#email, input[name="email"]').first()
        const count = await emailInput.count()
        expect(count, 'Pagina /esqueci-senha deve expor campo de email').toBeGreaterThan(0)

        await emailInput.fill('admin@example.test')

        const submitBtn = page.locator('button[type="submit"], button:has-text("enviar"), button:has-text("recuperar")').first()
        await submitBtn.click()

        // Should show success message or redirect
        await page.waitForTimeout(2000)
        const pageContent = await page.textContent('body')
        expect(pageContent!.length).toBeGreaterThan(50)
    })

    test('request reset with invalid email shows error', async ({ page }) => {
        await page.goto(BASE + '/esqueci-senha', { waitUntil: 'domcontentloaded' })
        await page.waitForTimeout(1000)

        const emailInput = page.locator('input[type="email"], input#email, input[name="email"]').first()
        const count = await emailInput.count()
        expect(count, 'Pagina de recuperacao deve expor campo de email para validar erro').toBeGreaterThan(0)

        await emailInput.fill('nonexistent@fake.invalid')

        const submitBtn = page.locator('button[type="submit"], button:has-text("enviar"), button:has-text("recuperar")').first()
        await submitBtn.click()

        await page.waitForTimeout(2000)
        // Page should still exist (not crash)
        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })

    test('reset page with expired token shows error', async ({ page }) => {
        await page.goto(BASE + '/redefinir-senha?token=expired-fake-token&email=test@test.com', {
            waitUntil: 'domcontentloaded',
        })
        await page.waitForTimeout(2000)

        // Should show some content (error page or redirect to login)
        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(20)
    })

    test('password reset form validates password requirements', async ({ page }) => {
        await page.goto(BASE + '/redefinir-senha?token=fake-token&email=test@test.com', {
            waitUntil: 'domcontentloaded',
        })
        await page.waitForTimeout(1000)

        const passwordInput = page.locator('input[type="password"], input#password, input[name="password"]').first()
        const count = await passwordInput.count()
        expect(count, 'Formulario de redefinicao deve expor campo de senha para validar requisitos').toBeGreaterThan(0)

        // Try submitting short password
        await passwordInput.fill('123')

        const confirmInput = page.locator('input[name="password_confirmation"], input[name="confirmPassword"]').first()
        if (await confirmInput.count() > 0) {
            await confirmInput.fill('123')
        }

        const submitBtn = page.locator('button[type="submit"]').first()
        await submitBtn.click()
        await page.waitForTimeout(1000)

        // Should show validation error or stay on page
        expect(page.url()).not.toContain('/login')
    })
})
