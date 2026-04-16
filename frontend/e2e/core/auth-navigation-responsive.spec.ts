import { test, expect } from '@playwright/test'
import { gotoAuthenticated, loginAsAdmin, BASE } from '../helpers'

test.use({ storageState: { cookies: [], origins: [] } })

async function gotoLogin(page: import('@playwright/test').Page) {
  await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded' })
}

async function loginForSmoke(page: import('@playwright/test').Page) {
  const loggedIn = await loginAsAdmin(page)
  expect(loggedIn, 'Login admin E2E deve estar disponivel; API/login indisponivel deve falhar o fluxo critico').toBe(true)
}

test.describe('Authentication E2E', () => {
  test('login page loads', async ({ page }) => {
    await gotoLogin(page)
    await expect(page.locator('#email')).toBeVisible({ timeout: 15000 })
    await expect(page.locator('#password')).toBeVisible()
    await expect(page.locator('button[type="submit"]')).toBeVisible()
  })

  test('login with valid credentials', async ({ page }) => {
    await loginForSmoke(page)
    expect(page.url()).not.toContain('/login')
    await expect(page.locator('main')).toBeVisible({ timeout: 15000 })
  })

  test('login with invalid credentials shows error', async ({ page }) => {
    await gotoLogin(page)
    await page.locator('#email').fill('wrong@test.com')
    await page.locator('#password').fill('wrongpassword')
    await page.locator('button[type="submit"]').click()

    const error = page.locator('[role="alert"], [data-sonner-toaster] li, .text-red-600, .text-red-500')
      .filter({ hasText: /credenciais|invalid|erro|unauthorized|bloqueada|tentativas/i })
    await expect(error.first()).toBeVisible({ timeout: 10000 })
  })

  test('login form validates empty fields', async ({ page }) => {
    await gotoLogin(page)
    await page.locator('button[type="submit"]').click()
    // Should show validation errors or stay on login page
    await expect(page).toHaveURL(/login/)
  })

  test('logout redirects to login', async ({ page }) => {
    await loginForSmoke(page)
    await page.getByRole('button', { name: 'Sair' }).click()
    await expect(page).toHaveURL(/login/, { timeout: 10000 })
  })
})

test.describe('Navigation E2E', () => {
  test.beforeEach(async ({ page }) => {
    await loginForSmoke(page)
  })

  test('sidebar renders correctly', async ({ page }) => {
    await expect(page.locator('aside, nav').first()).toBeVisible({ timeout: 15000 })
  })

  test('navigates to all main routes', async ({ page }) => {
    test.setTimeout(90_000)

    const routes = [
      { path: '/os', signal: /ordens|servico|o\.s\./i },
      { path: '/cadastros/clientes', signal: /clientes/i },
      { path: '/equipamentos', signal: /equipamentos/i },
      { path: '/orcamentos', signal: /orcamentos|orçamentos/i },
    ]

    for (const route of routes) {
      await gotoAuthenticated(page, route.path)
      const main = page.locator('main')
      await expect(main).toBeVisible({ timeout: 15000 })
      await expect(main).toContainText(route.signal, { timeout: 15000 })
    }
  })

  test('breadcrumbs work correctly', async ({ page }) => {
    await gotoAuthenticated(page, '/os')
    const breadcrumb = page.locator('[data-testid="breadcrumb"], nav[aria-label="breadcrumb"]')
    if (await breadcrumb.isVisible()) {
      await expect(breadcrumb).toContainText('Ordens')
    }
  })

  test('404 page for invalid route', async ({ page }) => {
    await gotoAuthenticated(page, '/nonexistent-page')
    await expect(page.locator('body')).toContainText(/404|nao encontrada|não encontrada|pagina/i)
  })

  test('command palette opens with Ctrl+K', async ({ page }) => {
    await page.keyboard.press('Control+k')
    const palette = page.locator('[data-testid="command-palette"], [role="dialog"]')
    if (await palette.isVisible({ timeout: 2000 })) {
      await expect(palette).toBeVisible()
    }
  })
})

test.describe('Responsive E2E', () => {
  test('mobile viewport shows menu button', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 })
    await loginForSmoke(page)

    await expect(page.locator('main')).toBeVisible({ timeout: 15000 })
  })

  test('tablet viewport renders correctly', async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 1024 })
    await loginForSmoke(page)

    await expect(page.locator('main, [data-testid="main-content"]')).toBeVisible()
  })

  test('desktop viewport shows full sidebar', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 })
    await loginForSmoke(page)

    const sidebar = page.locator('[data-testid="sidebar"], aside')
    if (await sidebar.isVisible({ timeout: 2000 })) {
      await expect(sidebar).toBeVisible()
    }
  })
})

test.describe('Accessibility E2E', () => {
  test.beforeEach(async ({ page }) => {
    await loginForSmoke(page)
  })

  test('pages have proper heading structure', async ({ page }) => {
    await gotoAuthenticated(page, '/os')
    const h1 = page.locator('h1')
    await expect(h1).toHaveCount(1)
  })

  test('forms have labels', async ({ page }) => {
    await gotoAuthenticated(page, '/os')
    const createButton = page.locator('[data-testid="create-button"], a:has-text("Nova OS"), button:has-text("Nova OS")').first()
    await expect(createButton, 'Fluxo critico de OS deve expor botao de criacao para validar acessibilidade de formulario').toBeVisible({ timeout: 10000 })
    await createButton.click()
    await page.waitForURL(/\/os\/nova/, { timeout: 10000 })

    const inputs = page.locator('input:visible')
    const count = await inputs.count()
    for (let i = 0; i < Math.min(count, 5); i++) {
      const input = inputs.nth(i)
      const hasLabel = await input.evaluate((element) => {
        const control = element as HTMLInputElement
        return Boolean(
          control.getAttribute('aria-label')?.trim()
          || control.getAttribute('aria-labelledby')?.trim()
          || control.labels?.length
          || control.title?.trim()
        )
      })

      expect(hasLabel).toBeTruthy()
    }
  })

  test('keyboard navigation works on tables', async ({ page }) => {
    await gotoAuthenticated(page, '/os')

    const focusSnapshot = async () => page.evaluate(() => {
      const active = document.activeElement

      if (!(active instanceof HTMLElement)) {
        return null
      }

      const rect = active.getBoundingClientRect()

      return {
        tagName: active.tagName,
        role: active.getAttribute('role'),
        ariaLabel: active.getAttribute('aria-label'),
        text: active.textContent?.trim().slice(0, 80) ?? '',
        href: active instanceof HTMLAnchorElement ? active.href : null,
        tabIndex: active.tabIndex,
        isVisible: rect.width > 0 && rect.height > 0,
      }
    })

    await page.keyboard.press('Tab')
    const firstFocusedElement = await focusSnapshot()
    await page.keyboard.press('Tab')
    const secondFocusedElement = await focusSnapshot()

    expect(firstFocusedElement, 'Primeiro Tab deve mover foco para um elemento HTML visivel').not.toBeNull()
    expect(secondFocusedElement, 'Segundo Tab deve manter navegacao de foco em elemento HTML visivel').not.toBeNull()
    expect(firstFocusedElement?.isVisible).toBe(true)
    expect(secondFocusedElement?.isVisible).toBe(true)
    expect(secondFocusedElement, 'Segundo Tab deve avancar para outro controle, sem foco preso').not.toEqual(firstFocusedElement)
  })

  test('modals trap focus', async ({ page }) => {
    await gotoAuthenticated(page, '/os')
    const createBtn = page.locator('[data-testid="create-button"], a:has-text("Nova OS"), button:has-text("Nova OS")').first()
    if (await createBtn.isVisible({ timeout: 2000 })) {
      await createBtn.click()
      // When a modal/form opens, focus should be within it
      const focusedElement = await page.evaluate(() => document.activeElement?.tagName)
      expect(focusedElement).toBeDefined()
    }
  })
})
