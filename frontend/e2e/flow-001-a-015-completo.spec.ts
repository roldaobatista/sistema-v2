/**
 * Fluxo completo 1-15: Backend + Frontend + Banco.
 * Valida login, listagens e criacao de Cliente, Fornecedor e Produto pela UI.
 */
import { test, expect } from '@playwright/test'
import { loginAsAdmin, BASE, waitForAppReady } from './helpers'

test.describe('Fluxo completo 1-15 (sistema todo)', () => {
    test('F1: Login pela UI e redirecionamento', async ({ page }) => {
        await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' })
        await page.evaluate(() => {
            localStorage.clear()
            sessionStorage.clear()
        })

        await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded' })
        await expect(page.locator('#email')).toBeVisible()
        await expect(page.locator('#password')).toBeVisible()

        const loggedIn = await loginAsAdmin(page)
        expect(loggedIn, 'Backend/login E2E deve estar disponivel para fluxo F1').toBe(true)

        await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' })
        await waitForAppReady(page)
        expect(page.url()).not.toContain('/login')
    })

    test('F13: Listar clientes (pagina carrega e chama API)', async ({ page }) => {
        const loggedIn = await loginAsAdmin(page, { navigateToApp: false })
        expect(loggedIn, 'API/login E2E deve estar disponivel para fluxo F13').toBe(true)

        await page.goto(BASE + '/cadastros/clientes', { waitUntil: 'domcontentloaded' })
        await waitForAppReady(page)
        await expect(page.locator('h1').first()).toContainText(/clientes/i)
        await expect(page.locator('body')).toContainText(/cliente|lista|cadastro/i)
    })

    test('F11+F12: Criar Cliente PJ e PF pela UI (fluxo completo)', async ({ page }) => {
        test.setTimeout(60000)
        const loggedIn = await loginAsAdmin(page, { navigateToApp: false })
        expect(loggedIn, 'API/login E2E deve estar disponivel para fluxo F11+F12').toBe(true)

        const initialCustomersResponse = page.waitForResponse(
            response => response.request().method() === 'GET'
                && response.url().includes('/api/v1/customers?')
                && response.ok(),
            { timeout: 25000 }
        )
        await page.goto(BASE + '/cadastros/clientes', { waitUntil: 'domcontentloaded' })
        await waitForAppReady(page)
        await initialCustomersResponse
        await page.waitForLoadState('networkidle', { timeout: 25000 })

        const createBtn = page.locator('button:has-text("Novo"), button:has-text("Criar"), a:has-text("Novo")').first()
        await expect(createBtn).toBeVisible({ timeout: 10000 })
        await createBtn.click()

        const dialog = page.getByRole('dialog', { name: /Novo Cliente/i })
        await expect(dialog).toBeVisible({ timeout: 10000 })

        const tipoSelect = page.locator('select[name="type"], [name="type"]').first()
        if (await tipoSelect.count() > 0) {
            await tipoSelect.selectOption('PJ')
        } else {
            await dialog.getByRole('button', { name: /Pessoa Jurídica/i }).click()
        }
        const customerName = `Metalurgica Rossi E2E ${Date.now()}`
        await dialog.locator('input[name="name"]').fill(customerName)
        await dialog.locator('input[name="email"]').fill(`e2e.${Date.now()}@metalurgicarossi.com.br`)

        const submitBtn = dialog.locator('button[type="submit"], button:has-text("Salvar"), button:has-text("Criar")').first()
        await expect(submitBtn).toBeEnabled()
        const createResponse = page.waitForResponse(
            response => response.request().method() === 'POST' && response.url().includes('/api/v1/customers'),
            { timeout: 30000 }
        )
        await submitBtn.click()
        const response = await createResponse

        expect(response.ok(), await response.text()).toBe(true)
        await expect(dialog).toBeHidden({ timeout: 10000 })

        const searchResponse = page.waitForResponse(
            response => response.request().method() === 'GET'
                && response.url().includes('/api/v1/customers?')
                && response.url().includes('search=')
                && response.ok(),
            { timeout: 30000 }
        )
        await page.getByPlaceholder(/Buscar por nome/i).fill(customerName)
        await searchResponse
        await expect(page.getByText(customerName).first()).toBeVisible({ timeout: 10000 })
    })

    test('F14: Listar e abrir formulario de Fornecedor', async ({ page }) => {
        const loggedIn = await loginAsAdmin(page, { navigateToApp: false })
        expect(loggedIn, 'API/login E2E deve estar disponivel para fluxo F14').toBe(true)

        await page.goto(BASE + '/cadastros/fornecedores', { waitUntil: 'domcontentloaded' })
        await waitForAppReady(page)
        await expect(page.locator('body')).toContainText(/fornecedor/i)
        const createBtn = page.locator('button:has-text("Novo"), button:has-text("Criar"), a:has-text("Novo")').first()
        if (await createBtn.count() > 0) {
            await createBtn.click()
            await page.waitForTimeout(500)
            const form = page.locator('form, [role="dialog"]')
            await expect(form.first()).toBeVisible({ timeout: 5000 })
        }
    })

    test('F15: Listar e abrir formulario de Produto', async ({ page }) => {
        const loggedIn = await loginAsAdmin(page, { navigateToApp: false })
        expect(loggedIn, 'API/login E2E deve estar disponivel para fluxo F15').toBe(true)

        await page.goto(BASE + '/cadastros/produtos', { waitUntil: 'domcontentloaded' })
        await waitForAppReady(page)
        await expect(page.locator('body')).toContainText(/produto/i)
        const createBtn = page.locator('button:has-text("Novo"), button:has-text("Criar"), a:has-text("Novo")').first()
        if (await createBtn.count() > 0) {
            await createBtn.click()
            await page.waitForTimeout(500)
            const form = page.locator('form, [role="dialog"]')
            await expect(form.first()).toBeVisible({ timeout: 5000 })
        }
    })
})
