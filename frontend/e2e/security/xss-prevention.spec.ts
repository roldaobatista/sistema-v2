import { expect, test } from '@playwright/test'
import { loginAsAdmin, waitForAppReady } from '../helpers'
import { navigateToModule } from '../fixtures'

test.describe('Security - XSS Prevention', () => {
    test.beforeEach(async ({ page }) => {
        const loggedIn = await loginAsAdmin(page)
        expect(loggedIn, 'Login admin E2E deve estar disponivel para validar XSS').toBe(true)
    })

    test('script tag in name field is not executed', async ({ page }) => {
        await navigateToModule(page, '/cadastros/clientes')
        await waitForAppReady(page)

        const createBtn = page.locator('button:has-text("Novo"), button:has-text("Criar"), a:has-text("Novo"), [data-testid="btn-new"]').first()
        await expect(createBtn, 'Tela de clientes deve expor acao de criacao para validar XSS no nome').toBeVisible()

        await createBtn.click()
        await page.waitForTimeout(1000)

        const nameField = page.locator('input[name="name"], input[name="nome"]').first()
        await expect(nameField, 'Formulario de cliente deve expor campo de nome para validar XSS').toBeVisible()

        const xssPayload = '<script>document.title="XSS"</script>'
        await nameField.fill(xssPayload)
        await page.waitForTimeout(1000)

        // Title should NOT be changed by the XSS payload
        expect(await page.title()).not.toBe('XSS')
    })

    test('script tag in search input is not executed', async ({ page }) => {
        await navigateToModule(page, '/cadastros/clientes')
        await waitForAppReady(page)

        const searchInput = page.locator('input[placeholder*="buscar" i], input[placeholder*="pesquisar" i], input[type="search"]').first()
        await expect(searchInput, 'Tela de clientes deve expor busca para validar XSS').toBeVisible()

        const xssPayload = '<script>document.title="XSS"</script>'
        await searchInput.fill(xssPayload)
        await page.waitForTimeout(1000)

        expect(await page.title()).not.toBe('XSS')

        // Page should not crash
        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })

    test('HTML in text fields is escaped in display', async ({ page }) => {
        await navigateToModule(page, '/cadastros/clientes')
        await waitForAppReady(page)

        const searchInput = page.locator('input[placeholder*="buscar" i], input[placeholder*="pesquisar" i], input[type="search"]').first()
        await expect(searchInput, 'Tela de clientes deve expor busca para validar escape de HTML').toBeVisible()

        const htmlPayload = '<b>bold</b><img src=x onerror=alert(1)>'
        await searchInput.fill(htmlPayload)
        await page.waitForTimeout(1000)

        // No alert should have been triggered
        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)

        // Input value should contain the raw HTML text
        const inputValue = await searchInput.inputValue()
        expect(inputValue).toContain('<b>bold</b>')
    })

    test('HTML in description field is not rendered as markup', async ({ page }) => {
        await navigateToModule(page, '/os/nova')
        await waitForAppReady(page)

        const descField = page.locator('textarea[name="description"], textarea[name="descricao"], textarea').first()
        await expect(descField, 'Tela de OS deve expor descricao para validar escape de HTML').toBeVisible()

        const htmlPayload = '<h1>XSS</h1><script>window.xssTriggered=true</script>'
        await descField.fill(htmlPayload)
        await page.waitForTimeout(500)

        // Verify no script execution
        const xssTriggered = await page.evaluate(() => (window as Window & { xssTriggered?: boolean }).xssTriggered)
        expect(xssTriggered).toBeFalsy()

        // The textarea should contain the raw text
        const value = await descField.inputValue()
        expect(value).toContain('<h1>XSS</h1>')
    })
})
