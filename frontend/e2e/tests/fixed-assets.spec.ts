import { test, expect } from '@playwright/test'

test.describe('Fixed Assets', () => {
  test('renderiza módulo patrimonial com mocks de API', async ({ page }) => {
    await page.route('**/api/v1/me', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            user: {
              id: 1,
              name: 'Admin',
              email: 'admin@example.test',
              phone: null,
              tenant_id: 1,
              permissions: [
                'fixed_assets.asset.view',
                'fixed_assets.dashboard.view',
                'fixed_assets.depreciation.view',
                'fixed_assets.depreciation.run',
                'fixed_assets.inventory.manage',
              ],
              roles: ['admin'],
              tenant: { id: 1, name: 'Kalibrium', document: null, email: null, phone: null, status: 'active' },
            },
          },
        }),
      })
    })

    await page.route('**/api/v1/fixed-assets/dashboard', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            total_assets: 2,
            total_acquisition_value: 150000,
            total_current_book_value: 120000,
            total_accumulated_depreciation: 30000,
            by_category: {
              equipment: { count: 1, book_value: 70000 },
              vehicle: { count: 1, book_value: 50000 },
            },
            disposals_this_year: 0,
            ciap_credits_pending: 8,
          },
        }),
      })
    })

    await page.route('**/api/v1/fixed-assets**', async (route) => {
      const url = route.request().url()
      if (url.includes('/dashboard')) return

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: 10,
              code: 'AT-00010',
              name: 'Balança XR-500',
              category: 'equipment',
              acquisition_date: '2026-01-15',
              acquisition_value: '45000.00',
              residual_value: '5000.00',
              useful_life_months: 120,
              depreciation_method: 'linear',
              depreciation_rate: '10.0000',
              accumulated_depreciation: '1500.00',
              current_book_value: '43500.00',
              status: 'active',
              location: 'Laboratório',
            },
          ],
          meta: { current_page: 1, per_page: 25, total: 1, last_page: 1 },
        }),
      })
    })

    await page.goto('/login')
    await page.evaluate(() => {
      localStorage.setItem('auth_token', 'playwright-token')
      localStorage.setItem('auth-store', JSON.stringify({
        state: {
          user: {
            id: 1,
            name: 'Admin',
            email: 'admin@example.test',
            phone: null,
            tenant_id: 1,
            permissions: [
              'fixed_assets.asset.view',
              'fixed_assets.dashboard.view',
              'fixed_assets.depreciation.view',
              'fixed_assets.depreciation.run',
              'fixed_assets.inventory.manage',
            ],
            roles: ['admin'],
            tenant: { id: 1, name: 'Kalibrium', document: null, email: null, phone: null, status: 'active' },
          },
          token: 'playwright-token',
          isAuthenticated: true,
          isLoading: false,
          tenant: { id: 1, name: 'Kalibrium', document: null, email: null, phone: null, status: 'active' },
        },
        version: 0,
      }))
    })

    await page.goto('/financeiro/ativos')
    await expect(page.getByText('Ativo Imobilizado')).toBeVisible()
    await expect(page.getByText('AT-00010')).toBeVisible()
    await expect(page.getByText('Parcelas CIAP pendentes')).toBeVisible()
  })
})
