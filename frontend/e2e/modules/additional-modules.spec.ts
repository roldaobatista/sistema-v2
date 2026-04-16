import { test, expect, type Page } from '@playwright/test'
import { loginAsAdmin, waitForAppReady } from '../helpers'

async function ensureLoggedIn(page: Page) {
  const ok = await loginAsAdmin(page, { navigateToApp: false })
  expect(ok, 'Login admin E2E deve estar disponivel para modulos adicionais').toBe(true)
}

async function expectModulePage(page: Page, path: string) {
  await page.goto(path, { waitUntil: 'domcontentloaded' })
  await waitForAppReady(page)
  await expect(page).not.toHaveURL(/\/login$/, { timeout: 10_000 })
  await expect(page.locator('main, h1, h2, [data-testid="page-title"]').first()).toBeVisible({ timeout: 15_000 })
  await expect(page.locator('body')).not.toContainText(/404|Página não encontrada/i)
}

test.beforeEach(async ({ page }) => {
  await ensureLoggedIn(page)
})

test.describe('Financial Module E2E', () => {
  test('payables page loads', async ({ page }) => {
    await expectModulePage(page, '/financeiro/pagar')
  })

  test('creates payable', async ({ page }) => {
    await expectModulePage(page, '/financeiro/pagar')

    const createButton = page.locator('[data-testid="create-button"], button:has-text("Novo"), button:has-text("Nova")').first()
    await expect(createButton).toBeVisible({ timeout: 10_000 })
    await createButton.click()
    await expect(page.locator('form, [role="dialog"]').first()).toBeVisible({ timeout: 10_000 })
  })

  test('receivables page loads', async ({ page }) => {
    await expectModulePage(page, '/financeiro/receber')
  })

  test('bank accounts page loads', async ({ page }) => {
    await expectModulePage(page, '/financeiro/contas-bancarias')
  })

  test('fund transfer page loads', async ({ page }) => {
    await expectModulePage(page, '/financeiro/transferencias-tecnicos')
  })

  test('financial reports page loads', async ({ page }) => {
    await expectModulePage(page, '/relatorios')
  })
})

test.describe('HR Module E2E', () => {
  test('hr overview page loads', async ({ page }) => {
    await expectModulePage(page, '/rh')
  })

  test('time clock page loads', async ({ page }) => {
    await expectModulePage(page, '/rh/ponto')
  })

  test('leave requests page loads', async ({ page }) => {
    await expectModulePage(page, '/rh/ferias')
  })
})

test.describe('Stock Module E2E', () => {
  test('products page loads', async ({ page }) => {
    await expectModulePage(page, '/cadastros/produtos')
  })

  test('warehouses page loads', async ({ page }) => {
    await expectModulePage(page, '/estoque/armazens')
  })

  test('stock movements page loads', async ({ page }) => {
    await expectModulePage(page, '/estoque/movimentacoes')
  })
})

test.describe('Commission Module E2E', () => {
  test('commission rules page loads', async ({ page }) => {
    await expectModulePage(page, '/financeiro/comissoes')
  })

  test('commission dashboard page loads', async ({ page }) => {
    await expectModulePage(page, '/financeiro/comissoes/dashboard')
  })
})

test.describe('Inmetro Module E2E', () => {
  test('inmetro owners page loads', async ({ page }) => {
    await expectModulePage(page, '/inmetro')
  })

  test('inmetro instruments page loads', async ({ page }) => {
    await expectModulePage(page, '/inmetro/instrumentos')
  })

  test('inmetro competitors page loads', async ({ page }) => {
    await expectModulePage(page, '/inmetro/concorrentes')
  })
})

test.describe('Settings & Profile E2E', () => {
  test('settings page loads', async ({ page }) => {
    await expectModulePage(page, '/configuracoes')
  })

  test('profile page loads', async ({ page }) => {
    await expectModulePage(page, '/perfil')
  })

  test('users management page loads', async ({ page }) => {
    await expectModulePage(page, '/iam/usuarios')
  })
})

test.describe('Fleet Module E2E', () => {
  test('fleet vehicles page loads', async ({ page }) => {
    await expectModulePage(page, '/frota')
  })

  test('travel requests page loads', async ({ page }) => {
    await expectModulePage(page, '/rh/viagens')
  })
})

test.describe('Quality Module E2E', () => {
  test('quality audits page loads', async ({ page }) => {
    await expectModulePage(page, '/qualidade/auditorias')
  })
})
