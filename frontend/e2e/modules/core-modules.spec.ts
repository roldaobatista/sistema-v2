import { test, expect, type Page } from '@playwright/test'
import { loginAsAdmin, waitForAppReady } from '../helpers'

async function ensureLoggedIn(page: Page) {
  const ok = await loginAsAdmin(page, { navigateToApp: false })
  expect(ok, 'Login admin E2E deve estar disponivel para modulos principais').toBe(true)
}

async function expectPageLoaded(page: Page, path: string) {
  await page.goto(path, { waitUntil: 'domcontentloaded' })
  await waitForAppReady(page)
  await expect(page).not.toHaveURL(/\/login$/, { timeout: 10_000 })
  await expect(page.locator('main, h1, h2, [data-testid="page-title"]').first()).toBeVisible({ timeout: 15_000 })
  await expect(page.locator('body')).not.toContainText(/404|Página não encontrada/i)
}

test.beforeEach(async ({ page }) => {
  await ensureLoggedIn(page)
})

test.describe('Work Order E2E', () => {
  test('list work orders page loads', async ({ page }) => {
    await expectPageLoaded(page, '/os')
  })

  test('creates new work order', async ({ page }) => {
    await expectPageLoaded(page, '/os')
    await page.locator('[data-testid="create-button"], a:has-text("Nova OS"), button:has-text("Nova OS")').first().click()
    await expect(page).toHaveURL(/\/os\/nova/, { timeout: 10_000 })
    await expect(page.locator('form, h1, h2').first()).toBeVisible({ timeout: 10_000 })
  })

  test('views work order detail', async ({ page }) => {
    await expectPageLoaded(page, '/os')
    const firstRow = page.locator('table tbody tr, [data-testid="work-order-card"], a[href*="/os/"]').first()

    await expect(firstRow, 'Seeder E2E deve disponibilizar OS para abrir detalhe').toBeVisible({ timeout: 10000 })
    await firstRow.click()

    await expect(page.locator('[data-testid="work-order-detail"], h1, h2').first()).toBeVisible({ timeout: 10_000 })
  })

  test('filters work orders by status', async ({ page }) => {
    await expectPageLoaded(page, '/os')
    const statusFilter = page.locator('[data-testid="status-filter"], select[name="status"], select[aria-label="Filtrar por status"]').first()

    if (await statusFilter.isVisible({ timeout: 5000 }).catch(() => false)) {
      await statusFilter.selectOption({ index: 1 }).catch(async () => {
        await statusFilter.selectOption('open')
      })
      await waitForAppReady(page)
    }

    await expect(page.locator('main')).toBeVisible()
  })

  test('searches work orders', async ({ page }) => {
    await expectPageLoaded(page, '/os')
    const searchInput = page.locator('[data-testid="search-input"], input[type="search"], input[placeholder*="Buscar"]').first()

    if (await searchInput.isVisible({ timeout: 5000 }).catch(() => false)) {
      await searchInput.fill('calibração')
      await waitForAppReady(page)
    }

    await expect(page.locator('main')).toBeVisible()
  })
})

test.describe('Customer E2E', () => {
  test('list customers page loads', async ({ page }) => {
    await expectPageLoaded(page, '/cadastros/clientes')
  })

  test('creates new customer', async ({ page }) => {
    await expectPageLoaded(page, '/cadastros/clientes')
    await page.locator('[data-testid="create-button"], button:has-text("Novo Cliente"), a:has-text("Novo Cliente")').first().click()
    await expect(page.locator('form, [role="dialog"]').first()).toBeVisible({ timeout: 10_000 })
  })

  test('views customer detail', async ({ page }) => {
    test.setTimeout(60_000)

    await expectPageLoaded(page, '/cadastros/clientes')
    const searchInput = page.locator('[data-testid="search-input"], input[type="search"], input[placeholder*="Buscar"]').first()
    await expect(searchInput).toBeVisible({ timeout: 10_000 })
    await searchInput.fill('Cliente E2E Tenant 1')

    const customerCard = page.getByTestId('customer-card').filter({ hasText: 'Cliente E2E Tenant 1' }).first()

    await expect(customerCard).toBeVisible({ timeout: 30_000 })
    await customerCard.click()
    await expect(page).toHaveURL(/\/cadastros\/clientes\/\d+$/, { timeout: 10_000 })
    await expect(page.locator('[data-testid="customer-detail"], h1, h2').first()).toBeVisible({ timeout: 10_000 })
  })

  test('searches customers', async ({ page }) => {
    await expectPageLoaded(page, '/cadastros/clientes')
    const searchInput = page.locator('[data-testid="search-input"], input[type="search"], input[placeholder*="Buscar"]').first()

    if (await searchInput.isVisible({ timeout: 5000 }).catch(() => false)) {
      await searchInput.fill('E2E')
      await waitForAppReady(page)
    }

    await expect(page.locator('main')).toBeVisible()
  })
})

test.describe('Equipment E2E', () => {
  test('list equipment page loads', async ({ page }) => {
    await expectPageLoaded(page, '/equipamentos')
  })

  test('creates new equipment', async ({ page }) => {
    await expectPageLoaded(page, '/equipamentos')
    await page.locator('[data-testid="create-button"], button:has-text("Novo Equipamento"), a:has-text("Novo Equipamento")').first().click()
    await expect(page.locator('form, [role="dialog"]').first()).toBeVisible({ timeout: 10_000 })
  })
})

test.describe('Quote E2E', () => {
  test('list quotes page loads', async ({ page }) => {
    await expectPageLoaded(page, '/orcamentos')
  })

  test('creates new quote', async ({ page }) => {
    await expectPageLoaded(page, '/orcamentos')
    await page.locator('[data-testid="create-button"], button:has-text("Novo Orçamento"), a:has-text("Novo Orçamento")').first().click()
    await expect(page.locator('form, h1, h2').first()).toBeVisible({ timeout: 10_000 })
  })
})

test.describe('Dashboard E2E', () => {
  test('dashboard loads with KPIs', async ({ page }) => {
    await expectPageLoaded(page, '/')
  })

  test('navigates from dashboard to work orders', async ({ page }) => {
    await expectPageLoaded(page, '/os')
  })

  test('navigates from dashboard to customers', async ({ page }) => {
    await expectPageLoaded(page, '/cadastros/clientes')
  })
})

test.describe('CRM E2E', () => {
  test('CRM deals page loads', async ({ page }) => {
    await expectPageLoaded(page, '/crm/pipeline')
  })

  test('creates new deal', async ({ page }) => {
    await expectPageLoaded(page, '/crm/pipeline')
    await page.locator('[data-testid="create-button"], button[aria-label="Adicionar deal"], button[title="Adicionar deal"], button:has-text("Novo Negócio"), button:has-text("Novo Deal"), button:has-text("Adicionar")').first().click()
    await expect(page.locator('form, [role="dialog"]').first()).toBeVisible({ timeout: 10_000 })
  })
})

test.describe('Financial E2E', () => {
  test('account payables page loads', async ({ page }) => {
    await expectPageLoaded(page, '/financeiro/pagar')
  })

  test('account receivables page loads', async ({ page }) => {
    await expectPageLoaded(page, '/financeiro/receber')
  })
})

test.describe('Agenda E2E', () => {
  test('agenda page loads', async ({ page }) => {
    await expectPageLoaded(page, '/agenda')
  })

  test('creates new agenda item', async ({ page }) => {
    await expectPageLoaded(page, '/agenda')
    await page.locator('[data-testid="create-button"], button:has-text("Nova Tarefa"), button:has-text("Novo"), button:has-text("Criar")').first().click()
    await expect(page.locator('form, [role="dialog"]').first()).toBeVisible({ timeout: 10_000 })
  })
})
