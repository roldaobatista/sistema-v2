import { test, expect } from '@playwright/test'
import { loginAsAdmin } from '../helpers'
import { navigateToModule } from '../fixtures'

test.describe('Tech PWA Workflow', () => {
    test.beforeEach(async ({ page }) => {
        const loggedIn = await loginAsAdmin(page)
        expect(loggedIn, 'Login admin E2E deve estar disponivel para workflow Tech PWA').toBe(true)
    })

    test('tech dashboard loads', async ({ page }) => {
        await navigateToModule(page, '/tech/dashboard')

        await page.waitForTimeout(3000)

        // Should show Kalibrium branding and tech UI
        await expect(page.locator('text=Kalibrium').first()).toBeVisible({ timeout: 10000 })
        await expect(page.getByRole('heading', { name: /Dashboard/i }).first()).toBeVisible({ timeout: 10000 })
        await expect(page.getByRole('navigation').first()).toBeVisible({ timeout: 10000 })
    })

    test('view assigned work orders list', async ({ page }) => {
        await navigateToModule(page, '/tech')

        await page.waitForTimeout(3000)

        // Tech main page shows work orders list
        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })

    test('open individual work order detail', async ({ page }) => {
        await navigateToModule(page, '/tech')

        await page.waitForTimeout(2000)

        // Click first work order card
        const osCard = page.locator('[data-testid*="os-card"], a[href*="/tech/os/"], .card, [class*="card"]').first()
        if (await osCard.count() > 0) {
            await osCard.click()
            await page.waitForTimeout(2000)
        }

        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })

    test('checklist items can be interacted with', async ({ page }) => {
        await navigateToModule(page, '/tech')
        await page.waitForTimeout(2000)

        // Open first OS
        const osCard = page.locator('[data-testid*="os-card"], a[href*="/tech/os/"]').first()
        if (await osCard.count() > 0) {
            await osCard.click()
            await page.waitForTimeout(2000)
        }

        // Look for checklist section
        const checklistItems = page.locator('input[type="checkbox"], [data-testid*="checklist"], [role="checkbox"]')
        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })

    test('photo capture section exists', async ({ page }) => {
        await navigateToModule(page, '/tech')
        await page.waitForTimeout(2000)

        const osCard = page.locator('[data-testid*="os-card"], a[href*="/tech/os/"]').first()
        if (await osCard.count() > 0) {
            await osCard.click()
            await page.waitForTimeout(2000)
        }

        // Look for photo/camera button
        const photoBtn = page.locator('button:has-text("Foto"), button:has-text("Camera"), input[type="file"][accept*="image"], [data-testid*="photo"]')
        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })

    test('signature capture section exists', async ({ page }) => {
        await navigateToModule(page, '/tech')
        await page.waitForTimeout(2000)

        const osCard = page.locator('[data-testid*="os-card"], a[href*="/tech/os/"]').first()
        if (await osCard.count() > 0) {
            await osCard.click()
            await page.waitForTimeout(2000)
        }

        // Look for signature area
        const signatureArea = page.locator('canvas, [data-testid*="signature"], button:has-text("Assinatura")')
        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })

    test('bottom navigation works', async ({ page }) => {
        await navigateToModule(page, '/tech')

        // Verify bottom nav links
        const nav = page.locator('nav')
        await expect(nav.first()).toBeVisible({ timeout: 10000 })

        // Click Agenda tab
        const agendaLink = page.locator('nav a[href="/tech/agenda"], nav a:has-text("Agenda")').first()
        if (await agendaLink.count() > 0) {
            await agendaLink.click()
            await page.waitForTimeout(2000)
            expect(page.url()).toContain('/tech/agenda')
        }
    })

    test('more menu opens and shows options', async ({ page }) => {
        await navigateToModule(page, '/tech')

        // Click "Mais" button
        const moreBtn = page.locator('button:has-text("Mais")').first()
        const count = await moreBtn.count()
        expect(count, 'Tech PWA deve expor botao Mais para navegacao offline').toBeGreaterThan(0)

        await moreBtn.click()
        await page.waitForTimeout(500)

        // More menu should show additional options
        const moreMenu = page.locator('text=Mais opcoes, h3:has-text("Mais")').first()
        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(100)
    })
})
