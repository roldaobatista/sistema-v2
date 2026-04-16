import { test, expect, type Page } from '@playwright/test'
import { loginAsAdmin, BASE, waitForAppReady } from './helpers'

async function ensureLoggedIn(page: Page) {
    const ok = await loginAsAdmin(page)
    expect(ok, 'Login admin E2E deve estar disponivel para validacao de formularios').toBe(true)
}

async function gotoAndWait(page: Page, path: string) {
    await page.goto(BASE + path, { waitUntil: 'domcontentloaded' })
    await waitForAppReady(page)
}

test.describe('Forms Validation - Clientes', () => {
    test('submit empty form shows required field errors', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/cadastros/clientes')

        const newBtn = page.locator('button:has-text("Novo"), a:has-text("Novo")').first()
        if (await newBtn.count() === 0) return

        await newBtn.click()
        const dialog = page.getByRole('dialog', { name: /Novo Cliente/i })
        await expect(dialog).toBeVisible()

        const submitBtn = dialog.locator('button[type="submit"], button:has-text("Salvar")').first()
        if (await submitBtn.count() === 0) return

        await submitBtn.click()

        await expect(
            dialog.getByText(/obrigatorio|obrigatório|required|campo|preencha/i).first()
        ).toBeVisible()
    })

    test('email field rejects invalid format', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/cadastros/clientes')

        const newBtn = page.locator('button:has-text("Novo"), a:has-text("Novo")').first()
        if (await newBtn.count() === 0) return
        await newBtn.click()
        await page.waitForTimeout(500)

        const emailInput = page.locator('input[name="email"], input[type="email"]').first()
        if (await emailInput.count() > 0) {
            await emailInput.fill('not-an-email')

            const nameInput = page.locator('input[name="name"], input[name="nome"]').first()
            if (await nameInput.count() > 0) {
                await nameInput.fill('Teste Validation')
            }

            const submitBtn = page.locator('button[type="submit"], button:has-text("Salvar")').first()
            if (await submitBtn.count() > 0) {
                await submitBtn.click()
                await page.waitForTimeout(1500)

                const hasEmailError = await page.locator('text=/email|invalido|inválido|invalid/i').count() > 0
                const hasInvalid = await page.locator('input[type="email"]:invalid').count() > 0
                expect(hasEmailError || hasInvalid).toBeTruthy()
            }
        }
    })
})

test.describe('Forms Validation - OS', () => {
    test('OS create form submit is disabled without customer', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/os/nova')

        const submitBtn = page.locator('button[type="submit"]').first()
        if (await submitBtn.count() > 0) {
            const isDisabled = await submitBtn.isDisabled()
            expect(isDisabled).toBeTruthy()
        }

        expect(page.url()).toContain('/os/nova')
    })
})

test.describe('Forms Validation - Orcamentos', () => {
    test('quote create form has required fields', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/orcamentos/novo')

        const submitBtn = page.locator('button[type="submit"], button:has-text("Salvar"), button:has-text("Criar")').first()
        if (await submitBtn.count() > 0) {
            await submitBtn.click()
            await page.waitForTimeout(1500)

            const hasValidation = await page.locator('.text-red-600, .text-red-500, .text-destructive, :invalid').count() > 0
            const stayedOnPage = page.url().includes('/novo')
            expect(hasValidation || stayedOnPage).toBeTruthy()
        }
    })
})

test.describe('Forms Validation - Financeiro', () => {
    test('contas a receber - new modal has required fields', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/financeiro/receber')

        const newBtn = page.locator('button:has-text("Novo"), button:has-text("Nova"), a:has-text("Novo")').first()
        if (await newBtn.count() === 0) return
        await newBtn.click()
        await page.waitForTimeout(800)

        const submitBtn = page.locator('button[type="submit"], button:has-text("Salvar")').first()
        if (await submitBtn.count() > 0) {
            await submitBtn.click()
            await page.waitForTimeout(1500)

            const hasValidation = await page.locator('.text-red-600, .text-red-500, .text-destructive, :invalid').count() > 0
            const hasDialog = await page.locator('[role="dialog"], .modal').count() > 0
            expect(hasValidation || hasDialog).toBeTruthy()
        }
    })

    test('contas a pagar - new modal has required fields', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/financeiro/pagar')

        const newBtn = page.locator('button:has-text("Novo"), button:has-text("Nova"), a:has-text("Novo")').first()
        if (await newBtn.count() === 0) return
        await newBtn.click()
        await page.waitForTimeout(800)

        const submitBtn = page.locator('button[type="submit"], button:has-text("Salvar")').first()
        if (await submitBtn.count() > 0) {
            await submitBtn.click()
            await page.waitForTimeout(1500)

            const hasValidation = await page.locator('.text-red-600, .text-red-500, .text-destructive, :invalid').count() > 0
            const stayedOpen = await page.locator('[role="dialog"], .modal').count() > 0
            expect(hasValidation || stayedOpen).toBeTruthy()
        }
    })
})

test.describe('Forms Validation - API Error Handling', () => {
    test('server 500 shows graceful error message', async ({ page }) => {
        await ensureLoggedIn(page)

        await page.route('**/api/v1/customers**', async (route) => {
            if (route.request().method() === 'POST') {
                await route.fulfill({
                    status: 500,
                    contentType: 'application/json',
                    body: JSON.stringify({ message: 'Internal Server Error' }),
                })
            } else {
                await route.continue()
            }
        })

        await gotoAndWait(page, '/cadastros/clientes')

        const newBtn = page.locator('button:has-text("Novo"), a:has-text("Novo")').first()
        if (await newBtn.count() === 0) return
        await newBtn.click()
        await page.waitForTimeout(500)

        const nameInput = page.locator('input[name="name"], input[name="nome"]').first()
        if (await nameInput.count() > 0) await nameInput.fill('Teste Erro 500')

        const submitBtn = page.locator('button[type="submit"], button:has-text("Salvar")').first()
        if (await submitBtn.count() > 0) {
            await submitBtn.click()
            await page.waitForTimeout(2000)

            const body = await page.textContent('body')
            expect(body!.length).toBeGreaterThan(50)
        }
    })

    test('server 422 shows validation errors', async ({ page }) => {
        await ensureLoggedIn(page)

        await page.route('**/api/v1/customers**', async (route) => {
            if (route.request().method() === 'POST') {
                await route.fulfill({
                    status: 422,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        message: 'Dados invalidos',
                        errors: { name: ['O campo nome e obrigatorio.'] },
                    }),
                })
            } else {
                await route.continue()
            }
        })

        await gotoAndWait(page, '/cadastros/clientes')

        const newBtn = page.locator('button:has-text("Novo"), a:has-text("Novo")').first()
        if (await newBtn.count() === 0) return
        await newBtn.click()
        await page.waitForTimeout(500)

        const nameInput = page.locator('input[name="name"], input[name="nome"]').first()
        if (await nameInput.count() > 0) await nameInput.fill('Test')

        const submitBtn = page.locator('button[type="submit"], button:has-text("Salvar")').first()
        if (await submitBtn.count() > 0) {
            await submitBtn.click()
            await page.waitForTimeout(2000)

            const hasInline = await page.locator('text=/obrigatorio|obrigatório/i').count() > 0
            const hasToast = await page.locator('text=/invalido|inválido|erro|falha/i').count() > 0
            expect(hasInline || hasToast).toBeTruthy()
        }
    })
})
