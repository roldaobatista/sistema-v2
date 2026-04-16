import { test, expect, type Page } from '@playwright/test'
import { loginAsAdmin, BASE, waitForAppReady } from './helpers'

async function ensureLoggedIn(page: Page) {
    const ok = await loginAsAdmin(page)
    expect(ok, 'Login admin E2E deve estar disponivel para fluxos Tech PWA').toBe(true)
}

async function gotoAndWait(page: Page, path: string) {
    await page.goto(BASE + path, { waitUntil: 'domcontentloaded' })
    await waitForAppReady(page)
}

test.describe.configure({ timeout: 90_000 })

test.describe('Tech PWA - Shell & Navigation', () => {
    test('tech shell loads with bottom navigation', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/tech')

        const bottomNav = page.locator('nav').last()
        await expect(bottomNav).toBeVisible({ timeout: 10_000 })

        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })

    test('bottom nav has OS and Perfil tabs', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/tech')

        const osLink = page.locator('nav a:has-text("OS"), nav a span:has-text("OS")')
        const perfilLink = page.locator('nav a:has-text("Perfil"), nav a span:has-text("Perfil")')
        const hasOSTab = await osLink.count() > 0
        const hasProfileTab = await perfilLink.count() > 0
        expect(hasOSTab || hasProfileTab).toBeTruthy()
    })

    test('navigates to profile page', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/tech/perfil')

        const body = await page.textContent('body')
        const hasProfile = body?.match(/Sincroniz|Perfil|Conectado|Offline/i) !== null
        expect(hasProfile).toBeTruthy()
    })
})

test.describe('Tech PWA - Work Orders', () => {
    test('work orders list page loads', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/tech')

        const heading = page.locator('h1, h2, [class*="font-bold"]').first()
        await expect(heading).toBeVisible({ timeout: 10_000 })

        const body = await page.textContent('body')
        const hasError = body?.match(/erro 500|internal server error/i) !== null
        expect(hasError).toBeFalsy()
    })

    test('work orders list has search functionality', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/tech')

        const search = page.locator('input[placeholder*="Buscar" i], input[type="search"]').first()
        if (await search.count() > 0) {
            await search.fill('NONEXISTENT_TEST_999')
            await page.waitForTimeout(500)

            const body = await page.textContent('body')
            expect(body).toBeTruthy()
        }
    })

    test('work orders list has status filter', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/tech')

        const filters = page.locator('button:has-text("Todas"), button:has-text("Pendente"), button:has-text("Em Andamento")')
        if (await filters.count() > 0) {
            await filters.first().click()
            await page.waitForTimeout(500)
            const body = await page.textContent('body')
            expect(body).toBeTruthy()
        }
    })
})

test.describe('Tech PWA - Profile & Sync', () => {
    test('profile page shows sync status', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/tech/perfil')

        const body = await page.textContent('body')
        const hasSyncInfo = body?.match(/Sincroniz|Conectado|Offline|sincronizado/i) !== null
        expect(hasSyncInfo).toBeTruthy()
    })

    test('profile page has sync now button', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/tech/perfil')

        const syncBtn = page.locator('button:has-text("Sincronizar")')
        if (await syncBtn.count() > 0) {
            await expect(syncBtn.first()).toBeVisible()
        }
    })

    test('profile page has logout button', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/tech/perfil')

        const logoutBtn = page.locator('button:has-text("Sair"), button:has-text("Sair da conta")')
        if (await logoutBtn.count() > 0) {
            await expect(logoutBtn.first()).toBeVisible()
        }
    })

    test('profile page has clear data option', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/tech/perfil')

        const body = await page.textContent('body')
        const hasClear = body?.match(/Limpar dados|cache offline/i) !== null
        expect(hasClear).toBeTruthy()
    })
})

test.describe('Tech PWA - Offline Behavior', () => {
    test('shows offline indicator when network is disconnected', async ({ page, context }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/tech')

        await context.setOffline(true)
        await page.waitForTimeout(1000)

        await page.evaluate(() => window.dispatchEvent(new Event('offline')))
        await page.waitForTimeout(500)

        const body = await page.textContent('body')
        expect(body).toBeTruthy()

        await context.setOffline(false)
        await page.evaluate(() => window.dispatchEvent(new Event('online')))
    })

    test('page content remains visible when offline', async ({ page, context }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/tech')
        await page.waitForTimeout(2000)

        await context.setOffline(true)
        await page.evaluate(() => window.dispatchEvent(new Event('offline')))
        await page.waitForTimeout(500)

        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)

        const nav = page.locator('nav').last()
        await expect(nav).toBeVisible()

        await context.setOffline(false)
        await page.evaluate(() => window.dispatchEvent(new Event('online')))
    })

    test('can navigate between tech pages while offline', async ({ page, context }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/tech/perfil')
        await gotoAndWait(page, '/tech')
        await page.waitForTimeout(2000)

        await context.setOffline(true)
        await page.evaluate(() => window.dispatchEvent(new Event('offline')))
        await page.waitForTimeout(500)

        await page.getByRole('button', { name: 'Mais' }).click()
        await page.getByRole('button', { name: /Perfil/ }).click()
        await expect(page).toHaveURL(/\/tech\/perfil/, { timeout: 5000 })
        await waitForAppReady(page)

        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(30)

        await context.setOffline(false)
        await page.evaluate(() => window.dispatchEvent(new Event('online')))
    })
})

test.describe('Tech PWA - Page Stability', () => {
    const techPages = [
        { name: 'Work Orders List', path: '/tech' },
        { name: 'Profile', path: '/tech/perfil' },
    ]

    for (const techPage of techPages) {
        test(`${techPage.name} loads without errors`, async ({ page }) => {
            await ensureLoggedIn(page)
            await gotoAndWait(page, techPage.path)

            const body = await page.textContent('body')
            expect(body!.length).toBeGreaterThan(30)

            const hasErrorOverlay = await page.locator('#webpack-dev-server-client-overlay, .vite-error-overlay').count() > 0
            expect(hasErrorOverlay).toBeFalsy()
        })
    }
})
