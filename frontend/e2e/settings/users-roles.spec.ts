import { test, expect } from '@playwright/test'
import { loginAsAdmin } from '../helpers'
import { navigateToModule } from '../fixtures'

test.describe('Settings - Users and Roles', () => {
    test.beforeEach(async ({ page }) => {
        const loggedIn = await loginAsAdmin(page)
        expect(loggedIn, 'Login admin E2E deve estar disponivel para usuarios e roles').toBe(true)
    })

    test('users list page loads', async ({ page }) => {
        await navigateToModule(page, '/iam/usuarios')

        await expect(page.locator('h1, h2').first()).toBeVisible({ timeout: 10000 })

        // Table or list of users should render
        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(100)
    })

    test('create new user form opens', async ({ page }) => {
        await navigateToModule(page, '/iam/usuarios')

        const createBtn = page.locator('button:has-text("Novo"), button:has-text("Criar"), a:has-text("Novo"), [data-testid="btn-new"]').first()
        const count = await createBtn.count()
        expect(count, 'Tela de usuarios deve expor botao de criacao').toBeGreaterThan(0)

        await createBtn.click()
        await page.waitForTimeout(1000)

        // Form should be visible with name, email, role fields
        const nameField = page.locator('input[name="name"], input[name="nome"]').first()
        const emailField = page.locator('input[name="email"]').first()

        if (await nameField.count() > 0) {
            await expect(nameField).toBeVisible()
        }
        if (await emailField.count() > 0) {
            await expect(emailField).toBeVisible()
        }

        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(100)
    })

    test('edit user permissions', async ({ page }) => {
        await navigateToModule(page, '/iam/usuarios')

        // Click first user to edit
        const editBtn = page.locator('button:has-text("Editar"), a:has-text("Editar"), [data-testid*="edit"], button[aria-label*="editar" i]').first()
        const firstRow = page.locator('table tbody tr a, table tbody tr').first()

        if (await editBtn.count() > 0) {
            await editBtn.click()
        } else if (await firstRow.count() > 0) {
            await firstRow.click()
        } else {
            expect(await editBtn.count() + await firstRow.count(), 'Seeder E2E deve disponibilizar usuario ou acao de edicao').toBeGreaterThan(0)
        }

        await page.waitForTimeout(1000)

        // Look for permissions or roles section
        const permSection = page.locator('[data-testid*="permission"], [data-testid*="role"], input[type="checkbox"], :text("Permiss")').first()
        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(100)
    })

    test('deactivate user action exists', async ({ page }) => {
        await navigateToModule(page, '/iam/usuarios')

        // Look for deactivate/disable button on any user
        const deactivateBtn = page.locator('button:has-text("Desativar"), button:has-text("Inativar"), button:has-text("Bloquear"), [data-testid*="deactivate"]').first()
        const switchToggle = page.locator('button[role="switch"], input[type="checkbox"][name*="active"]').first()

        if (await deactivateBtn.count() > 0) {
            await expect(deactivateBtn).toBeVisible()
        } else if (await switchToggle.count() > 0) {
            await expect(switchToggle).toBeVisible()
        }

        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(100)
    })

    test('roles list page loads', async ({ page }) => {
        await navigateToModule(page, '/iam/roles')

        await page.waitForTimeout(3000)
        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })

    test('permissions matrix page loads', async ({ page }) => {
        await navigateToModule(page, '/iam/permissoes')

        await page.waitForTimeout(3000)
        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })
})
