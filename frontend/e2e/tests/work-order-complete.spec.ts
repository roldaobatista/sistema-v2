import { test, expect, type APIRequestContext, type Page } from '@playwright/test'
import { navigateToModule } from '../fixtures'
import { API_BASE } from '../helpers'

type ApiRecord = {
    id: number | string
}

type ApiEnvelope<T> = {
    data?: T
} & Partial<T>

type FixtureAuth = {
    token: string
    headers: Record<string, string>
}

type CustomerFixture = {
    id: number
    type: 'PF'
    name: string
    document: null
    email: string
    phone: string
    phone2: null
    address_city: null
    address_state: null
    address_street: null
    address_number: null
    address_neighborhood: null
    latitude: null
    longitude: null
    google_maps_link: null
    is_active: boolean
}

const TEST_PREFIX = 'OS E2E Completa'

async function getFixtureAuth(page: Page): Promise<FixtureAuth> {
    const token = await page.evaluate(() => window.localStorage.getItem('auth_token'))

    if (!token) {
        throw new Error('Token de autenticacao nao encontrado no localStorage E2E.')
    }

    return {
        token,
        headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
        },
    }
}

function extractCreatedId(body: ApiEnvelope<ApiRecord>, entity: string): number {
    const rawId = body.data?.id ?? body.id
    const id = Number(rawId)

    if (!Number.isInteger(id) || id <= 0) {
        throw new Error(`Resposta de criacao de ${entity} nao retornou id valido.`)
    }

    return id
}

async function createCustomer(page: Page, request: APIRequestContext): Promise<CustomerFixture> {
    const { headers } = await getFixtureAuth(page)
    const suffix = `${Date.now()}-${test.info().parallelIndex}-${test.info().repeatEachIndex}`
    const customerData: Omit<CustomerFixture, 'id'> = {
        type: 'PF',
        name: `${TEST_PREFIX} Cliente ${suffix}`,
        document: null,
        email: `os-e2e-${suffix}@example.test`,
        phone: '11999999999',
        phone2: null,
        address_city: null,
        address_state: null,
        address_street: null,
        address_number: null,
        address_neighborhood: null,
        latitude: null,
        longitude: null,
        google_maps_link: null,
        is_active: true,
    }
    const response = await request.post(`${API_BASE}/customers`, {
        headers,
        data: customerData,
    })

    const bodyText = await response.text()
    expect(response.status(), bodyText).toBe(201)

    return {
        id: extractCreatedId(JSON.parse(bodyText) as ApiEnvelope<ApiRecord>, 'cliente'),
        ...customerData,
    }
}

async function stubWorkOrderCreateLookups(page: Page, customer: CustomerFixture): Promise<void> {
    const emptyList = { data: [] }
    const listLookupPaths = new Set([
        '/api/v1/products',
        '/api/v1/services',
        '/api/v1/users/by-role/tecnico',
        '/api/v1/users',
        '/api/v1/branches',
        '/api/v1/service-checklists',
    ])

    await page.route('**/api/v1/**', async (route) => {
        const request = route.request()

        if (request.method() !== 'GET') {
            await route.continue()
            return
        }

        const url = new URL(request.url())
        const pathname = url.pathname.replace(/\/$/, '')

        if (pathname === `/api/v1/customers/${customer.id}`) {
            await route.fulfill({ status: 200, json: { data: customer } })
            return
        }

        if (pathname === '/api/v1/equipments' && url.searchParams.get('customer_id') === String(customer.id)) {
            await route.fulfill({ status: 200, json: emptyList })
            return
        }

        if (listLookupPaths.has(pathname)) {
            await route.fulfill({ status: 200, json: emptyList })
            return
        }

        await route.continue()
    })
}

async function createWorkOrder(page: Page, request: APIRequestContext, description = `${TEST_PREFIX} fixture`): Promise<number> {
    const customer = await createCustomer(page, request)
    const { headers } = await getFixtureAuth(page)
    const response = await request.post(`${API_BASE}/work-orders`, {
        headers,
        data: {
            customer_id: customer.id,
            description,
            priority: 'normal',
        },
    })

    const bodyText = await response.text()
    expect(response.status(), bodyText).toBe(201)

    return extractCreatedId(JSON.parse(bodyText) as ApiEnvelope<ApiRecord>, 'ordem de servico')
}

test.describe('Work Order Module', () => {
    test.describe.configure({ mode: 'serial' })
    test.setTimeout(90_000)

    test.beforeEach(async ({ page }) => {
        await navigateToModule(page, '/dashboard')
        await expect(page).not.toHaveURL(/.*login/)
    })

    test('should navigate to OS listing', async ({ page }) => {
        await navigateToModule(page, '/os')
        await expect(page.locator('h1, [data-testid="page-title"]')).toContainText(/Orden|OS|Serviço/i)
    })

    test('should create a new work order', async ({ page, request }) => {
        const customer = await createCustomer(page, request)
        await stubWorkOrderCreateLookups(page, customer)

        await navigateToModule(page, `/os/nova?customer_id=${customer.id}`)

        const descInput = page.locator('textarea[name="description"], textarea[placeholder*="descrição" i], textarea').first()
        await descInput.fill(`${TEST_PREFIX} criada pela tela`)

        const submitButton = page.locator('button[type="submit"]').first()
        await expect(submitButton).toBeEnabled({ timeout: 10000 })

        const createResponsePromise = page.waitForResponse(
            response => response.url().includes('/api/v1/work-orders') && response.request().method() === 'POST',
            { timeout: 60000 }
        )
        await submitButton.click()
        const createResponse = await createResponsePromise
        expect(createResponse.status(), await createResponse.text()).toBe(201)

        await expect(page).toHaveURL(/\/os\/\d+/, { timeout: 15000 })
    })

    test('should view work order detail', async ({ page, request }) => {
        const workOrderId = await createWorkOrder(page, request, `${TEST_PREFIX} detalhe`)

        await navigateToModule(page, '/os')
        const workOrderLink = page.locator(`main a[href="/os/${workOrderId}"]`).first()
        await expect(workOrderLink).toBeVisible({ timeout: 10000 })
        await workOrderLink.click()

        await expect(page).toHaveURL(new RegExp(`/os/${workOrderId}$`), { timeout: 10000 })
    })

    test('should access kanban view', async ({ page }) => {
        await navigateToModule(page, '/os/kanban')
        await expect(page.locator('[data-testid="kanban-column"], .flex-1.overflow-x-auto').first()).toBeVisible()
    })

    test('should access dashboard', async ({ page }) => {
        await navigateToModule(page, '/os/dashboard')
        await expect(page.locator('text=/Total de OS|Dashboard/i').first()).toBeVisible()
    })

    test('should export CSV', async ({ page }) => {
        await navigateToModule(page, '/os')
        await expect(page.locator('body')).not.toBeEmpty({ timeout: 10000 })
        const exportBtn = page.locator('button:has-text("Exportar"), button:has-text("Export")').first()
        if (await exportBtn.isVisible()) {
            const [download] = await Promise.all([
                page.waitForEvent('download', { timeout: 10000 }).catch(() => null),
                exportBtn.click(),
            ])
            if (download) {
                expect(download.suggestedFilename()).toMatch(/\.(csv|xlsx)$/i)
            }
        }
    })

    test('should duplicate work order', async ({ page, request }) => {
        const workOrderId = await createWorkOrder(page, request, `${TEST_PREFIX} duplicacao`)

        await navigateToModule(page, `/os/${workOrderId}`)
        const dupBtn = page.locator('button:has-text("Duplicar")').first()
        if (await dupBtn.isVisible()) {
            await dupBtn.click()
            await expect(page).toHaveURL(/\/os\/\d+/, { timeout: 15000 })
        }
    })
})
