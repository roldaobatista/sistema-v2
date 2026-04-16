import { test, expect, type Page } from '@playwright/test'
import { loginAsAdmin, BASE, waitForAppReady } from './helpers'

async function ensureLoggedIn(page: Page) {
    await loginAsAdmin(page)
}

async function clearBrowserSession(page: Page): Promise<void> {
    await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded' })
    await page.evaluate(() => localStorage.clear())
}

test.describe('Security - Authentication Redirect', () => {
    test('unauthenticated visit to dashboard redirects to login', async ({ page }) => {
        await clearBrowserSession(page)

        await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' })

        await expect(page).toHaveURL(/\/login/, { timeout: 10000 })
    })

    test('unauthenticated visit to clientes redirects to login', async ({ page }) => {
        await clearBrowserSession(page)

        await page.goto(BASE + '/cadastros/clientes', { waitUntil: 'domcontentloaded' })

        await expect(page).toHaveURL(/\/login/, { timeout: 10000 })
    })
})

test.describe('Security - XSS Prevention', () => {
    test('script tags in search input are not executed', async ({ page }) => {
        await ensureLoggedIn(page)
        await page.goto(BASE + '/cadastros/clientes', { waitUntil: 'domcontentloaded' })
        await waitForAppReady(page)

        const search = page.locator('input[placeholder*="buscar" i], input[placeholder*="pesquisar" i], input[type="search"]').first()
        await expect(search).toBeVisible({ timeout: 15000 })

        await search.fill('<script>document.title="XSS"</script>')

        await expect(page).not.toHaveTitle('XSS')
        await expect(page.locator('body')).not.toContainText(/<script>document\.title="XSS"<\/script>/)
    })
})

test.describe('Security - SQL Injection Prevention', () => {
    test('SQL injection in search does not break the page', async ({ page }) => {
        await ensureLoggedIn(page)
        await page.goto(BASE + '/cadastros/clientes', { waitUntil: 'domcontentloaded' })
        await waitForAppReady(page)

        const search = page.locator('input[placeholder*="buscar" i], input[placeholder*="pesquisar" i], input[type="search"]').first()
        await expect(search).toBeVisible({ timeout: 15000 })

        await search.fill("'; DROP TABLE customers; --")
        await waitForAppReady(page)

        await expect(page.locator('body')).not.toContainText(/SQLSTATE|syntax error|pgsql/i)
    })
})

test.describe('Security - Portal Route Isolation', () => {
    test('portal routes redirect to portal login', async ({ page }) => {
        await clearBrowserSession(page)

        await page.goto(BASE + '/portal', { waitUntil: 'domcontentloaded' })

        await expect(page).toHaveURL(/\/portal\/login|\/login/, { timeout: 10000 })
    })
})

test.describe('Security - Session Management', () => {
    test('authenticated user can access dashboard', async ({ page }) => {
        await ensureLoggedIn(page)
        await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' })
        await waitForAppReady(page)

        await expect(page).not.toHaveURL(/\/login/)
        await expect(page.locator('body')).toContainText(/Dashboard|Kalibrium|Hoje/i)
    })
})
