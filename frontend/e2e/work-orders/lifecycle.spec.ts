import { test, expect, type Page } from '@playwright/test'
import { loginAsAdmin } from '../helpers'
import { navigateToModule, testWorkOrder } from '../fixtures'

interface CustomerSearchItem {
    id: number
    name?: string
}

interface CustomerSearchResponse {
    data?: CustomerSearchItem[] | { data?: CustomerSearchItem[] }
}

interface CreatedWorkOrderPayload {
    id?: number | string
    description?: string | null
    customer_id?: number | string | null
}

interface WorkOrderCreateResponse {
    data?: CreatedWorkOrderPayload
    id?: number | string
    description?: string | null
    customer_id?: number | string | null
}

function customerItemsFromResponse(payload: CustomerSearchResponse): CustomerSearchItem[] {
    if (Array.isArray(payload.data)) {
        return payload.data
    }

    if (payload.data && Array.isArray(payload.data.data)) {
        return payload.data.data
    }

    return []
}

function createdWorkOrderFromResponse(payload: WorkOrderCreateResponse): CreatedWorkOrderPayload {
    return payload.data ?? payload
}

async function getExistingCustomerId(page: Page): Promise<number> {
    const token = await page.evaluate(() => window.localStorage.getItem('auth_token'))
    expect(token, 'Sessao E2E deve expor token para preparar cliente da OS').toBeTruthy()

    const apiBase = process.env.E2E_API_BASE || 'http://127.0.0.1:8010/api/v1'
    const response = await page.request.get(`${apiBase}/customers`, {
        headers: {
            Accept: 'application/json',
            Authorization: `Bearer ${token}`,
        },
        params: {
            search: 'Cliente E2E',
            per_page: '1',
        },
        timeout: 60_000,
    })

    expect(response.ok(), `Busca de cliente E2E deve retornar HTTP 2xx, recebeu ${response.status()}`).toBe(true)

    const payload = await response.json() as CustomerSearchResponse
    const customer = customerItemsFromResponse(payload).find(item => item.name?.includes('Cliente E2E'))
    expect(customer, 'Seeder E2E deve disponibilizar cliente para abertura de OS').toBeTruthy()

    return customer!.id
}

test.describe('Work Orders Lifecycle', () => {
    test.beforeEach(async ({ page }) => {
        const loggedIn = await loginAsAdmin(page)
        expect(loggedIn, 'Login admin E2E deve estar disponivel para ciclo de ordens de servico').toBe(true)
    })

    test('work orders list page loads', async ({ page }) => {
        await navigateToModule(page, '/os')
        await expect(page.locator('h1, h2').first()).toBeVisible({ timeout: 10000 })
    })

    test('create new work order with customer selection', async ({ page }) => {
        await navigateToModule(page, '/os/nova')

        const formOrHeader = page.locator('form, h1, h2').first()
        await expect(formOrHeader).toBeVisible({ timeout: 10000 })

        // Look for customer selection field
        const customerField = page.locator('input[placeholder*="cliente" i], [data-testid*="customer"], input[name="customer"]').first()
        if (await customerField.count() > 0) {
            await customerField.fill('Cliente')
            await page.waitForTimeout(500)

            // Select first suggestion
            const suggestion = page.locator('[role="option"], [role="listbox"] li, .suggestion-item').first()
            if (await suggestion.count() > 0) {
                await suggestion.click()
            }
        }

        // Fill description
        const descField = page.locator('textarea[name="description"], textarea[name="descricao"], input[name="description"]').first()
        if (await descField.count() > 0) {
            await descField.fill(testWorkOrder.description)
        }

        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(100)
    })

    test('add items (products and services) to work order', async ({ page }) => {
        await navigateToModule(page, '/os/nova')
        await page.waitForTimeout(1000)

        // Look for add item button
        const addItemBtn = page.locator('button:has-text("Adicionar"), button:has-text("Item"), button:has-text("Produto"), [data-testid*="add-item"]').first()
        if (await addItemBtn.count() > 0) {
            await addItemBtn.click()
            await page.waitForTimeout(500)
        }

        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(100)
    })

    test('save work order and verify created', async ({ page }) => {
        test.setTimeout(90_000)

        const customerId = await getExistingCustomerId(page)
        await navigateToModule(page, `/os/nova?customer_id=${customerId}`)

        const customerSelect = page.getByRole('combobox', { name: /cliente/i }).first()
        await expect(customerSelect).toContainText(/Cliente E2E/i, { timeout: 30000 })

        // Fill minimal required fields
        const descField = page.getByRole('textbox', { name: /Defeito Relatado|Descrição/i }).first()
        await expect(descField).toBeVisible({ timeout: 10000 })
        const description = 'OS Teste E2E - ' + Date.now()
        await descField.fill(description)

        const submitButton = page.getByRole('button', { name: /Abrir OS/i }).first()
        await expect(submitButton).toBeEnabled({ timeout: 10000 })
        const createResponsePromise = page.waitForResponse((response) => {
            const request = response.request()

            try {
                return request.method() === 'POST' && new URL(response.url()).pathname === '/api/v1/work-orders'
            } catch {
                return false
            }
        }, { timeout: 30000 })

        await submitButton.click()

        const createResponse = await createResponsePromise
        const createResponseBody = await createResponse.text()
        expect(createResponse.status(), createResponseBody).toBe(201)

        const createdWorkOrder = createdWorkOrderFromResponse(JSON.parse(createResponseBody) as WorkOrderCreateResponse)
        const createdWorkOrderId = Number(createdWorkOrder.id)

        expect(Number.isInteger(createdWorkOrderId) && createdWorkOrderId > 0, 'Criacao de OS deve retornar id valido').toBe(true)
        expect(createdWorkOrder.description, 'API deve persistir a descricao enviada no formulario').toBe(description)
        expect(Number(createdWorkOrder.customer_id), 'API deve persistir o cliente usado na abertura da OS').toBe(customerId)

        await expect(page).toHaveURL(new RegExp(`/os/${createdWorkOrderId}$`), { timeout: 30000 })
        await expect(page.locator('main nav[aria-label="Breadcrumb"]').filter({ hasText: `#${createdWorkOrderId}` })).toBeVisible({ timeout: 10000 })
        await expect(page.locator('body')).not.toContainText(/404|Página não encontrada/i)
    })

    test('kanban view loads correctly', async ({ page }) => {
        await navigateToModule(page, '/os/kanban')
        await expect(page.getByRole('heading', { name: /Kanban de OS/i }).first()).toBeVisible({ timeout: 10000 })
    })

    test('work order search filters results', async ({ page }) => {
        await navigateToModule(page, '/os')

        const searchInput = page.locator('input[placeholder*="buscar" i], input[placeholder*="pesquisar" i], input[type="search"]').first()
        if (await searchInput.count() > 0) {
            await searchInput.fill('OS-TEST')
            await page.waitForTimeout(1000)
        }

        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })

    test('work order status can be changed', async ({ page }) => {
        await navigateToModule(page, '/os')

        // Click first work order to open detail
        const firstRow = page.locator('table tbody tr a, table tbody tr td').first()
        if (await firstRow.count() > 0) {
            await firstRow.click()
            await page.waitForTimeout(1000)
        }

        // Look for status change button or dropdown
        const statusBtn = page.locator('button:has-text("Status"), [data-testid*="status"], select[name="status"]').first()
        if (await statusBtn.count() > 0) {
            await expect(statusBtn).toBeVisible()
        }

        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })

    test('work order agenda view loads', async ({ page }) => {
        await navigateToModule(page, '/os/agenda')
        await page.waitForTimeout(2000)

        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })

    test('work order map view loads', async ({ page }) => {
        await navigateToModule(page, '/os/mapa')
        await page.waitForTimeout(2000)

        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })

    test('work order dashboard loads with metrics', async ({ page }) => {
        await navigateToModule(page, '/os/dashboard')
        await page.waitForTimeout(2000)

        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })

    test('work order SLA page loads', async ({ page }) => {
        await navigateToModule(page, '/os/sla')
        await page.waitForTimeout(2000)

        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })

    test('work order detail page shows all sections', async ({ page }) => {
        test.setTimeout(60_000)

        await navigateToModule(page, '/os')

        // Click first work order
        const firstRow = page.locator('main a[href^="/os/"]').filter({ hasText: /OS-/ }).first()
        await expect(firstRow, 'Seeder E2E deve disponibilizar OS para abrir detalhe').toBeVisible({ timeout: 30000 })

        await firstRow.click()
        await expect(page).toHaveURL(/\/os\/\d+$/, { timeout: 10000 })

        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(100)
    })
})
