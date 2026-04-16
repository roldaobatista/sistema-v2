import { test, expect, type Page } from '@playwright/test'
import { loginAsAdmin, BASE, waitForAppReady } from './helpers'

async function ensureLoggedIn(page: Page) {
    const ok = await loginAsAdmin(page)
    expect(ok, 'Login admin E2E deve estar disponivel para smoke de modulos').toBe(true)
}

async function gotoAndWait(page: Page, path: string) {
    await page.goto(BASE + path, { waitUntil: 'domcontentloaded' })
    await waitForAppReady(page)
    await expect(page).toHaveURL(new RegExp(`${path.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`), { timeout: 15000 })
}

async function expectHeading(page: Page, pattern: RegExp) {
    const heading = page.locator('h1, h2, [data-testid="page-title"]').first()
    await expect(heading).toBeVisible({ timeout: 15000 })
    await expect(heading).toContainText(pattern)
}

test.describe('Equipamentos Module', () => {
    test('equipment list page loads', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/equipamentos')
        await expectHeading(page, /equipamentos/i)
    })

    test('equipment list shows table or empty state', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/equipamentos')

        const hasTable = await page.locator('table, [role="table"]').count() > 0
        const hasEmpty = await page.locator('text=/nenhum equipamento encontrado|nenhum|vazio/i').count() > 0
        expect(hasTable || hasEmpty).toBeTruthy()
    })

    test('equipment create page navigates correctly', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/equipamentos/novo')

        const formOrHeader = page.locator('form, h1, h2').first()
        await expect(formOrHeader).toBeVisible({ timeout: 15000 })
    })

    test('equipment calendar page loads', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/agenda-calibracoes')
        await expectHeading(page, /agenda de calibracoes|agenda de calibrações/i)
    })

    test('standard weights page loads', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/equipamentos/pesos-padrao')
        await expect(page.locator('main')).toBeVisible({ timeout: 15000 })
    })
})

test.describe('Estoque Module', () => {
    test('stock dashboard loads', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/estoque')
        await expectHeading(page, /estoque/i)
    })

    test('stock movements page loads', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/estoque/movimentacoes')
        await expect(page.locator('main')).toBeVisible({ timeout: 15000 })
    })
})

test.describe('CRM Module', () => {
    test('CRM dashboard loads', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/crm')
        await expectHeading(page, /crm/i)
    })

    test('CRM pipeline page loads', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/crm/pipeline')
        await expect(page.locator('main')).toBeVisible({ timeout: 15000 })
    })
})

test.describe('Chamados Module', () => {
    test('service calls list page loads', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/chamados')
        await expectHeading(page, /chamados/i)
    })

    test('service call create page loads', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/chamados/novo')

        const hasForm = await page.locator('form, input, select, textarea').count() > 0
        expect(hasForm).toBeTruthy()
    })
})

test.describe('INMETRO Module', () => {
    test('INMETRO main page loads', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/inmetro')
        await expectHeading(page, /inmetro/i)
    })

    test('INMETRO concorrentes page loads', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/inmetro/concorrentes')
        await expect(page.locator('main')).toBeVisible({ timeout: 15000 })
    })
})
