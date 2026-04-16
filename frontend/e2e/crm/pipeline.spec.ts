import { test, expect } from '@playwright/test'
import { loginAsAdmin, waitForAppReady } from '../helpers'
import { navigateToModule } from '../fixtures'

test.describe('CRM Pipeline', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page)
    })

    test('pipeline kanban expõe contrato estável de estágios e ações', async ({ page }) => {
        await navigateToModule(page, '/crm/pipeline')
        await waitForAppReady(page)

        await expect(page.getByTestId('crm-pipeline-page')).toBeVisible({ timeout: 15000 })
        await expect(page.getByTestId('crm-view-kanban')).toHaveAttribute('aria-pressed', 'true')
        await expect(page.getByTestId('crm-status-filter')).toBeVisible()

        const stages = page.getByTestId('crm-pipeline-stage')
        await expect(stages.first()).toBeVisible({ timeout: 15000 })
        expect(await stages.count()).toBeGreaterThan(0)

        await expect(page.getByTestId('crm-add-deal-button').first()).toBeVisible()
    })

    test('pipeline alterna para tabela mantendo filtros explícitos', async ({ page }) => {
        await navigateToModule(page, '/crm/pipeline')
        await waitForAppReady(page)

        await page.getByTestId('crm-view-table').click()
        await expect(page.getByTestId('crm-view-table')).toHaveAttribute('aria-pressed', 'true')
        await expect(page.getByTestId('crm-pipeline-table')).toBeVisible({ timeout: 15000 })

        await page.getByTestId('crm-status-filter').selectOption('won')
        await expect(page.getByTestId('crm-status-filter')).toHaveValue('won')
        await expect(page.getByTestId('crm-pipeline-page')).toBeVisible()
    })

    test('dashboard CRM e agenda carregam sem boundary de erro', async ({ page }) => {
        for (const route of ['/crm', '/crm/calendar']) {
            await navigateToModule(page, route)
            await waitForAppReady(page)

            await expect(page).not.toHaveURL(/.*login/)
            await expect(page.getByText(/erro inesperado|falha ao renderizar|chunk failed/i)).toHaveCount(0)
        }
    })
})
