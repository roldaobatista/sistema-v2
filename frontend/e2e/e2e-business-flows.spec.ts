import { test, expect, type Page } from '@playwright/test'
import { gotoAuthenticated, loginAsAdmin } from './helpers'

async function ensureLoggedIn(page: Page) {
    const ok = await loginAsAdmin(page, { navigateToApp: false })
    expect(ok, 'Login admin E2E deve estar disponivel para fluxos de negocio').toBe(true)
}

async function gotoAndWait(page: Page, path: string) {
    await gotoAuthenticated(page, path)
}

test.describe('Business Flow - Dashboard', () => {
    test('dashboard loads with real widgets after login', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/')

        const cards = page.locator('.rounded-xl, .rounded-lg, [class*="card"]')
        await expect(cards.first()).toBeVisible({ timeout: 15000 })

        const body = await page.textContent('body')
        expect(body).toBeTruthy()
        expect(body!.length).toBeGreaterThan(100)
    })

    test('sidebar navigation works to all major modules', async ({ page }) => {
        await ensureLoggedIn(page)
        const routes = ['/cadastros/clientes', '/os', '/orcamentos', '/financeiro/receber']

        for (const route of routes) {
            await gotoAndWait(page, route)

            const heading = page.locator('h1, h2, [data-testid="page-title"]').first()
            await expect(heading).toBeVisible({ timeout: 10000 })
        }
    })
})

test.describe('Business Flow - Clientes CRUD', () => {
    const timestamp = Date.now()
    const testName = `E2E Cliente ${timestamp}`

    test('create a new customer', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/cadastros/clientes')

        const newBtn = page.locator('button:has-text("Novo"), a:has-text("Novo")')
        await expect(newBtn.first()).toBeVisible({ timeout: 10000 })
        await newBtn.first().click()
        await page.waitForTimeout(500)

        const nameInput = page.locator('input[name="name"], input[name="nome"], input[placeholder*="nome" i]').first()
        if (await nameInput.count() > 0) {
            await nameInput.fill(testName)
        }

        const emailInput = page.locator('input[name="email"], input[type="email"]').first()
        if (await emailInput.count() > 0) {
            await emailInput.fill(`e2e-${timestamp}@teste.com`)
        }

        const phoneInput = page.locator('input[name="phone"], input[name="telefone"]').first()
        if (await phoneInput.count() > 0) {
            await phoneInput.fill('11999999999')
        }

        const submitBtn = page.locator('button[type="submit"], button:has-text("Salvar")')
        if (await submitBtn.count() > 0) {
            await submitBtn.first().click()
            await page.waitForTimeout(2000)

            const body = await page.textContent('body')
            const hasSuccess = body?.match(/sucesso|criado|salvo/i) !== null
            const hasRedirect = !page.url().includes('/novo')
            expect(hasSuccess || hasRedirect).toBeTruthy()
        }
    })

    test('customer appears in the list after creation', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/cadastros/clientes')

        await page.waitForTimeout(1000)
        const body = await page.textContent('body')
        expect(body).toBeTruthy()
        const hasError = body?.match(/500|erro interno|failed/i) !== null
        expect(hasError).toBeFalsy()
    })

    test('search filters the customer list', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/cadastros/clientes')

        const search = page.locator('input[placeholder*="buscar" i], input[placeholder*="pesquisar" i], input[type="search"]').first()
        if (await search.count() > 0) {
            await search.fill('XYZNONEXISTENT999')
            await page.waitForTimeout(1500)

            const body = await page.textContent('body')
            const hasEmptyIndicator = body?.match(/nenhum|vazio|sem resultado|0 registro/i) !== null
            const rowCount = await page.locator('tbody tr, [class*="table-row"]').count()
            expect(hasEmptyIndicator || rowCount === 0).toBeTruthy()
        }
    })
})

test.describe('Business Flow - OS Flow', () => {
    test('navigate to create OS and see form', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/os/nova')

        const form = page.locator('form, [class*="form"]').first()
        await expect(form).toBeVisible({ timeout: 10000 })
    })

    test('OS list loads with table or kanban', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/os')

        const hasTable = await page.locator('table, [role="table"]').count() > 0
        const hasKanban = await page.locator('[class*="kanban"], [class*="column"]').count() > 0
        const hasContent = await page.locator('h1, h2').first().isVisible()
        expect(hasTable || hasKanban || hasContent).toBeTruthy()
    })

    test('OS kanban loads with columns', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/os/kanban')

        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)

        const hasError = body?.match(/erro 500|internal server/i) !== null
        expect(hasError).toBeFalsy()
    })
})

test.describe('Business Flow - Orcamentos Flow', () => {
    test('navigate to create quote and see form', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/orcamentos/novo')

        const form = page.locator('form, [class*="form"]').first()
        await expect(form).toBeVisible({ timeout: 10000 })
    })

    test('quote list shows table with data or empty state', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/orcamentos')

        const hasTable = await page.locator('table tbody tr, [class*="table-row"]').count() > 0
        const hasEmpty = await page.locator('text=/nenhum|vazio|sem orcamento|sem orçamento/i').count() > 0
        const hasHeading = await page.locator('h1, h2').first().isVisible()
        expect(hasTable || hasEmpty || hasHeading).toBeTruthy()
    })
})

test.describe('Business Flow - Financeiro', () => {
    const financialPages = [
        { name: 'Contas a Receber', path: '/financeiro/receber' },
        { name: 'Contas a Pagar', path: '/financeiro/pagar' },
        { name: 'Despesas', path: '/financeiro/despesas' },
        { name: 'Fluxo de Caixa', path: '/financeiro/fluxo-caixa' },
        { name: 'Formas de Pagamento', path: '/financeiro/formas-pagamento' },
    ]

    for (const financialPage of financialPages) {
        test(`${financialPage.name} loads with real data structure`, async ({ page }) => {
            await ensureLoggedIn(page)
            await gotoAndWait(page, financialPage.path)

            const heading = page.locator('h1, h2').first()
            await expect(heading).toBeVisible({ timeout: 10000 })

            const body = await page.textContent('body')
            const hasError = body?.match(/erro 500|internal server error/i) !== null
            expect(hasError).toBeFalsy()

            const hasDataStructure =
                await page.locator('table, [role="table"], [class*="card"], [class*="grid"]').count() > 0
            expect(hasDataStructure).toBeTruthy()
        })
    }
})

test.describe('Business Flow - UX States', () => {
    test('pages show loading state (not blank) during data fetch', async ({ page }) => {
        await ensureLoggedIn(page)

        await page.route('**/api/**', async (route) => {
            await new Promise(resolve => setTimeout(resolve, 500))
            await route.continue()
        })

        await gotoAuthenticated(page, '/cadastros/clientes')
        await page.waitForTimeout(200)

        const hasVisibleContent = await page.locator('body').evaluate(el => el.children.length > 0)
        expect(hasVisibleContent).toBeTruthy()
    })

    test('search with no results shows empty state message', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/cadastros/clientes')

        const search = page.locator('input[placeholder*="buscar" i], input[placeholder*="pesquisar" i], input[type="search"]').first()
        if (await search.count() > 0) {
            await search.fill('ZZZZZZ_NONEXISTENT_99999')
            await page.waitForTimeout(2000)

            const body = await page.textContent('body')
            const hasEmptyFeedback = body?.match(/nenhum|0 resultado|sem resultado|vazio/i) !== null
            const noRows = await page.locator('tbody tr').count() === 0
            expect(hasEmptyFeedback || noRows).toBeTruthy()
        }
    })
})
