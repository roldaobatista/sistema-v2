import { test, expect, type APIRequestContext, type Page } from '@playwright/test'
import { API_BASE, loginAsAdmin, waitForAppReady } from '../helpers'

type ApiEnvelope<T> = { data?: T } & Partial<T>
type CreatedRecord = { id: number | string }

type CycleState = {
    customerId: number
    customerName: string
    workOrderId: number
    receivableId: number
}

async function authHeaders(page: Page): Promise<Record<string, string>> {
    const storageState = await page.context().storageState()
    const localStorageEntries = storageState.origins.flatMap(o => o.localStorage)
    const directToken = localStorageEntries.find(e => e.name === 'auth_token')?.value
    const persistedStore = localStorageEntries.find(e => e.name === 'auth-store')?.value
    let token = directToken || ''

    if (!token && persistedStore) {
        try {
            const parsed = JSON.parse(persistedStore) as { state?: { token?: unknown } }
            if (typeof parsed.state?.token === 'string') token = parsed.state.token
        } catch {
            // ignore
        }
    }

    if (!token) {
        throw new Error('Token de autenticacao ausente para o ciclo E2E.')
    }

    return {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
    }
}

function extractId(bodyText: string, entity: string): number {
    const body = JSON.parse(bodyText) as ApiEnvelope<CreatedRecord>
    const rawId = body.data?.id ?? body.id
    const id = Number(rawId)

    if (!Number.isInteger(id) || id <= 0) {
        throw new Error(`Criacao de ${entity} nao retornou id valido.`)
    }

    return id
}

async function createCustomer(page: Page, request: APIRequestContext): Promise<{ id: number; name: string }> {
    const suffix = `${Date.now()}-${test.info().parallelIndex}`
    const customerName = `Ciclo Completo E2E ${suffix}`
    const response = await request.post(`${API_BASE}/customers`, {
        headers: await authHeaders(page),
        data: {
            type: 'PF',
            name: customerName,
            document: null,
            email: `ciclo-${suffix}@example.test`,
            phone: '11999999999',
            is_active: true,
        },
    })

    const bodyText = await response.text()
    expect(response.status(), bodyText).toBe(201)

    return { id: extractId(bodyText, 'cliente'), name: customerName }
}

async function createWorkOrder(page: Page, request: APIRequestContext, customerId: number): Promise<number> {
    const response = await request.post(`${API_BASE}/work-orders`, {
        headers: await authHeaders(page),
        data: {
            customer_id: customerId,
            description: 'Ciclo completo E2E: orçamento, OS e financeiro',
            priority: 'normal',
            total: 1250.5,
        },
    })

    const bodyText = await response.text()
    expect(response.status(), bodyText).toBe(201)

    return extractId(bodyText, 'ordem de servico')
}

async function generateReceivable(page: Page, request: APIRequestContext, workOrderId: number): Promise<number> {
    const dueDate = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10)
    const response = await request.post(`${API_BASE}/accounts-receivable/generate-from-os`, {
        headers: await authHeaders(page),
        data: {
            work_order_id: workOrderId,
            due_date: dueDate,
            payment_method: 'pix',
        },
    })

    const bodyText = await response.text()
    expect(response.status(), bodyText).toBe(201)

    return extractId(bodyText, 'titulo a receber')
}

test.describe('Full Business Cycle - Cross Module', () => {
    test.describe.configure({ mode: 'serial' })
    test.setTimeout(120_000)

    let cycle: CycleState

    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page, { navigateToApp: false })
    })

    test('propaga cliente para OS e financeiro por API real', async ({ page, request }) => {
        const customer = await createCustomer(page, request)
        const workOrderId = await createWorkOrder(page, request, customer.id)
        const receivableId = await generateReceivable(page, request, workOrderId)

        const headers = await authHeaders(page)
        const workOrderResponse = await request.get(`${API_BASE}/work-orders/${workOrderId}`, { headers })
        const workOrderBody = await workOrderResponse.text()
        expect(workOrderResponse.status(), workOrderBody).toBe(200)
        expect(workOrderBody).toContain(customer.name)

        const receivableResponse = await request.get(`${API_BASE}/accounts-receivable/${receivableId}`, { headers })
        const receivableBody = await receivableResponse.text()
        expect(receivableResponse.status(), receivableBody).toBe(200)
        expect(receivableBody).toContain(String(workOrderId))

        cycle = {
            customerId: customer.id,
            customerName: customer.name,
            workOrderId,
            receivableId,
        }
    })

    test('interfaces dos módulos exibem o estado criado no ciclo', async ({ page }) => {
        expect(cycle).toBeDefined()

        await page.goto(`/os/${cycle.workOrderId}`, { waitUntil: 'domcontentloaded' })
        await waitForAppReady(page)
        await expect(page).toHaveURL(new RegExp(`/os/${cycle.workOrderId}$`))
        await expect(page.locator('body')).toContainText(cycle.customerName)

        await page.goto('/financeiro/receber', { waitUntil: 'domcontentloaded' })
        await waitForAppReady(page)
        await expect(page).not.toHaveURL(/.*login/)
        await expect(page.locator('body')).toContainText(/Contas a Receber|Receber|Financeiro/i)

        await page.goto('/crm/pipeline', { waitUntil: 'domcontentloaded' })
        await waitForAppReady(page)
        await expect(page.getByTestId('crm-pipeline-page')).toBeVisible({ timeout: 15000 })
    })
})
