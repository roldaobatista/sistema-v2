import { test, expect, type Page } from '@playwright/test'
import { loginAsAdmin, BASE, waitForAppReady } from './helpers'
import { navigateToModule } from './fixtures'

async function ensureLoggedIn(page: Page) {
    const ok = await loginAsAdmin(page, { navigateToApp: false })
    expect(ok, 'Login admin E2E deve estar disponivel para usuarios, responsividade e permissoes').toBe(true)
}

test.describe('E2E - User Management', () => {
    test('should load users list page', async ({ page }) => {
        await ensureLoggedIn(page)
        await navigateToModule(page, '/iam/usuarios')

        const content = page.locator('table, [data-testid="empty-state"], h1, h2')
        await expect(content.first()).toBeVisible({ timeout: 15000 })
    })

    test('should load roles page', async ({ page }) => {
        await ensureLoggedIn(page)
        await navigateToModule(page, '/iam/roles')

        const content = page.locator('table, [data-testid="empty-state"], h1, h2, :text("Role"), :text("Papel")')
        await expect(content.first()).toBeVisible({ timeout: 15000 })
    })

    test('should navigate to user creation', async ({ page }) => {
        await ensureLoggedIn(page)
        await navigateToModule(page, '/iam/usuarios')

        const createBtn = page.locator('a:has-text("Nov"), button:has-text("Nov"), a:has-text("Criar"), button:has-text("Criar")').first()
        if (await createBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
            await createBtn.click()
            await waitForAppReady(page)

            const nameField = page.locator('input[name="name"], input[placeholder*="Nome"]').first()
            await expect(nameField).toBeVisible({ timeout: 10000 })
        }
    })
})

test.describe('E2E - Responsive Layout', () => {
    test('sidebar collapses on mobile viewport', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 812 })
        await ensureLoggedIn(page)
        await navigateToModule(page, '/')

        const sidebar = page.locator('[data-sidebar]').first()
        const menuBtn = page.getByRole('button', { name: /Abrir menu lateral/i })

        await expect(menuBtn).toBeVisible({ timeout: 10000 })
        const initialBox = await sidebar.boundingBox()
        expect(initialBox?.x ?? 0).toBeLessThan(0)

        await menuBtn.click()
        await expect(page.getByRole('button', { name: /Fechar menu lateral/i })).toBeVisible({ timeout: 5000 })
        await expect
            .poll(async () => (await sidebar.boundingBox())?.x ?? -1, { timeout: 5000 })
            .toBeGreaterThanOrEqual(0)
    })

    test('content is readable on desktop viewport', async ({ page }) => {
        await page.setViewportSize({ width: 1920, height: 1080 })
        await ensureLoggedIn(page)
        await navigateToModule(page, '/')
        await waitForAppReady(page)

        await expect
            .poll(
                async () => (await page.textContent('body'))?.length ?? 0,
                { timeout: 15_000, message: 'O dashboard desktop deve renderizar conteudo textual suficiente' }
            )
            .toBeGreaterThan(100)
    })
})

test.describe('E2E - Permission Gates', () => {
    test('unauthenticated user is redirected to login', async ({ page }) => {
        await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded' })
        await page.evaluate(() => {
            localStorage.clear()
            sessionStorage.clear()
        })

        await page.goto(BASE + '/os', { waitUntil: 'domcontentloaded' })
        await waitForAppReady(page)

        const url = page.url()
        expect(url).toContain('login')
    })

    test('logged in admin can see sidebar navigation items', async ({ page }) => {
        await ensureLoggedIn(page)
        await navigateToModule(page, '/')
        await waitForAppReady(page)

        await expect
            .poll(
                async () => await page.locator('nav').first().textContent(),
                { timeout: 15_000, message: 'A navegacao principal deve renderizar itens para admin autenticado' }
            )
            .toMatch(/Dashboard|Centro O\.S\.|Financeiro|Gestão CRM/i)
    })
})
