import { test, expect, type Locator, type Page } from '@playwright/test'
import { API_BASE, BASE } from '../helpers'

type CustomerType = 'PF' | 'PJ'

interface SeededCustomer {
    id: number
    name: string
}

interface CustomerApiPayload {
    id?: unknown
    name?: unknown
    data?: unknown
}

interface CustomerListEnvelope {
    data?: unknown
}

interface AuthSession {
    token: string
    user: unknown
    tenant: unknown
}

let cachedAdminSession: AuthSession | null = null

test.setTimeout(300000)

function uniqueLabel(label: string): string {
    return `${label} ${Date.now()} ${Math.floor(Math.random() * 100000)}`
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null
}

function readSeededCustomer(payload: unknown, fallbackName: string): SeededCustomer {
    const envelope = isRecord(payload) ? payload as CustomerApiPayload : {}
    const rawCustomer = isRecord(envelope.data) ? envelope.data as CustomerApiPayload : envelope

    const id = Number(rawCustomer.id)
    expect(Number.isFinite(id), 'API deve retornar id do cliente criado').toBe(true)

    return {
        id,
        name: typeof rawCustomer.name === 'string' ? rawCustomer.name : fallbackName,
    }
}

function readCustomerCollection(payload: unknown): SeededCustomer[] {
    const envelope = isRecord(payload) ? payload as CustomerListEnvelope : {}
    const rawData = envelope.data
    const collection = Array.isArray(rawData)
        ? rawData
        : isRecord(rawData) && Array.isArray(rawData.data)
            ? rawData.data
            : []

    return collection
        .filter(isRecord)
        .map((customer) => ({
            id: Number(customer.id),
            name: typeof customer.name === 'string' ? customer.name : '',
        }))
        .filter((customer) => Number.isFinite(customer.id) && customer.name.length > 0)
}

async function authHeaders(page: Page): Promise<Record<string, string>> {
    const token = await page.evaluate(() => window.localStorage.getItem('auth_token'))
    expect(token, 'Sessao E2E deve expor token para criar massa por API').toBeTruthy()

    return {
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
    }
}

async function waitForCustomersEndpoint(page: Page, timeoutMs = 120000): Promise<void> {
    const headers = await authHeaders(page)
    const startedAt = Date.now()
    let lastError = ''

    while (Date.now() - startedAt <= timeoutMs) {
        try {
            const response = await page.request.get(`${API_BASE}/customers`, {
                headers,
                params: { per_page: 1 },
                timeout: 15000,
            })

            if (response.ok()) {
                return
            }

            lastError = `HTTP ${response.status()} ${await response.text().catch(() => '')}`
        } catch (error) {
            lastError = error instanceof Error ? error.message : String(error)
        }

        await page.waitForTimeout(1000)
    }

    throw new Error(`Endpoint customers E2E nao ficou pronto em ${API_BASE}/customers. Ultimo erro: ${lastError}`)
}

async function waitForCustomerIndexed(page: Page, name: string, timeoutMs = 120000): Promise<void> {
    const headers = await authHeaders(page)
    const startedAt = Date.now()
    let lastError = ''

    while (Date.now() - startedAt <= timeoutMs) {
        try {
            const response = await page.request.get(`${API_BASE}/customers`, {
                headers,
                params: { search: name, per_page: 20 },
                timeout: 15000,
            })
            const text = await response.text()

            if (response.ok()) {
                const customers = readCustomerCollection(JSON.parse(text) as unknown)
                if (customers.some((customer) => customer.name === name)) {
                    return
                }
                lastError = `HTTP ${response.status()} sem cliente ${name}; total retornado ${customers.length}`
            } else {
                lastError = `HTTP ${response.status()} ${text.slice(0, 240)}`
            }
        } catch (error) {
            lastError = error instanceof Error ? error.message : String(error)
        }

        await page.waitForTimeout(1000)
    }

    throw new Error(`Cliente nao ficou pesquisavel via API: ${name}. Ultimo erro: ${lastError}`)
}

async function createCustomer(page: Page, name: string, type: CustomerType = 'PJ'): Promise<SeededCustomer> {
    const response = await page.request.post(`${API_BASE}/customers`, {
        headers: await authHeaders(page),
        data: {
            type,
            name,
            email: `${name.toLowerCase().replace(/[^a-z0-9]+/g, '.')}@example.com`,
            phone: '11999998888',
            is_active: true,
        },
    })
    const text = await response.text()

    expect(response.status(), text).toBe(201)
    return readSeededCustomer(JSON.parse(text) as unknown, name)
}

async function seedCustomerGroup(page: Page, prefix: string, count: number): Promise<SeededCustomer[]> {
    const customers: SeededCustomer[] = []

    for (let index = 1; index <= count; index++) {
        customers.push(await createCustomer(page, `${prefix} ${String(index).padStart(2, '0')}`, index % 2 === 0 ? 'PF' : 'PJ'))
    }

    const lastCustomer = customers.at(-1)
    if (lastCustomer) {
        await waitForCustomerIndexed(page, lastCustomer.name)
    }

    return customers
}

function searchInput(page: Page) {
    return page.locator('input[placeholder*="buscar" i], input[type="search"]').first()
}

async function goToCustomersPage(page: Page): Promise<void> {
    await waitForCustomersEndpoint(page)

    const customersLoaded = page.waitForResponse((response) => {
        try {
            const url = new URL(response.url())
            return url.pathname.endsWith('/api/v1/customers') && response.ok()
        } catch {
            return false
        }
    }, { timeout: 60000 }).catch(() => null)

    await page.goto(`${BASE}/cadastros/clientes`, { waitUntil: 'domcontentloaded', timeout: 60000 })
    await expect(page.locator('h1, h2').filter({ hasText: /clientes/i }).first()).toBeVisible({ timeout: 30000 })
    await expect(searchInput(page), 'Tela de clientes deve estar carregada, nao apenas um main generico').toBeVisible({ timeout: 30000 })
    await customersLoaded
}

function readAuthPayload(payload: unknown): { token: string | null; user: unknown; tenant: unknown } {
    const envelope = isRecord(payload) ? payload : {}
    const data = isRecord(envelope.data) ? envelope.data : envelope
    const token = typeof data.token === 'string' ? data.token : null
    const user = isRecord(data.user) ? data.user : null
    const tenant = isRecord(user) && 'tenant' in user ? user.tenant : null

    return { token, user, tenant }
}

async function fetchAuthenticatedUser(page: Page, token: string): Promise<{ user: unknown; tenant: unknown }> {
    const response = await page.request.get(`${API_BASE}/me`, {
        headers: {
            Accept: 'application/json',
            Authorization: `Bearer ${token}`,
        },
    })
    const text = await response.text()

    expect(response.ok(), text).toBe(true)

    const envelope = isRecord(JSON.parse(text) as unknown) ? JSON.parse(text) as Record<string, unknown> : {}
    const data = isRecord(envelope.data) ? envelope.data : envelope
    const user = isRecord(data.user) ? data.user : data
    const tenant = isRecord(user) && 'tenant' in user ? user.tenant : null

    return { user, tenant }
}

async function loginAdminByApi(page: Page): Promise<AuthSession> {
    if (cachedAdminSession) {
        return cachedAdminSession
    }

    const credentials = [
        { email: 'admin@example.test', password: process.env.E2E_PASSWORD ?? 'CHANGE_ME_E2E_PASSWORD' },
        { email: 'admin@sistema.com', password: process.env.E2E_PASSWORD ?? 'CHANGE_ME_E2E_PASSWORD' },
    ]
    const failures: string[] = []

    for (const credential of credentials) {
        const response = await page.request.post(`${API_BASE}/login`, { data: credential })
        const text = await response.text()

        if (!response.ok()) {
            failures.push(`${credential.email}: HTTP ${response.status()} ${text.slice(0, 180)}`)
            continue
        }

        const authPayload = readAuthPayload(JSON.parse(text) as unknown)
        if (!authPayload.token) {
            failures.push(`${credential.email}: resposta sem token`)
            continue
        }

        const me = authPayload.user ? { user: authPayload.user, tenant: authPayload.tenant } : await fetchAuthenticatedUser(page, authPayload.token)

        cachedAdminSession = {
            token: authPayload.token,
            user: me.user,
            tenant: me.tenant,
        }

        return cachedAdminSession
    }

    throw new Error(`Login admin por API falhou em ${API_BASE}. ${failures.join(' | ')}`)
}

async function installAdminSession(page: Page, session: AuthSession): Promise<void> {
    await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded' })
    await page.evaluate((authSession) => {
        localStorage.clear()
        localStorage.setItem('auth_token', authSession.token)
        localStorage.setItem('auth-store', JSON.stringify({
            state: {
                token: authSession.token,
                isAuthenticated: true,
                isLoading: false,
                user: authSession.user,
                tenant: authSession.tenant,
            },
            version: 0,
        }))
        localStorage.setItem('kalibrium-mode-selected', 'remembered')
        localStorage.setItem('kalibrium-mode', 'gestao')
        localStorage.setItem('kalibrium-onboarding-done', JSON.stringify({ gestao: true, tecnico: true, vendedor: true }))
    }, session)
}

async function findCustomerCard(page: Page, name: string) {
    await waitForCustomerIndexed(page, name)
    await fillCustomerSearch(page, name)

    const card = page.getByTestId('customer-card').filter({ hasText: name }).first()
    await expect(card, `Cliente seeded deve aparecer na lista: ${name}`).toBeVisible({ timeout: 30000 })

    return card
}

async function fillCustomerSearch(page: Page, term: string): Promise<void> {
    const input = searchInput(page)
    await expect(input, 'Tela de clientes deve expor busca por nome').toBeVisible({ timeout: 30000 })

    const searchResponse = page.waitForResponse((response) => {
        try {
            const url = new URL(response.url())
            return url.pathname.endsWith('/api/v1/customers')
                && url.searchParams.get('search') === term
                && response.ok()
        } catch {
            return false
        }
    }, { timeout: 60000 }).catch(() => null)

    await input.fill(term)
    await searchResponse
}

async function openCustomerForm(page: Page) {
    await page.getByRole('button', { name: /novo cliente/i }).click()
    const dialog = page.getByRole('dialog', { name: /novo cliente/i })
    await expect(dialog).toBeVisible()

    return dialog
}

async function saveCustomerForm(page: Page, dialog: Locator, method: 'POST' | 'PUT') {
    const saveResponse = page.waitForResponse((response) => {
        const request = response.request()
        const url = response.url()

        return request.method() === method
            && /\/api\/v1\/customers(?:\/\d+)?$/.test(url)
            && response.ok()
    })

    await dialog.getByRole('button', { name: /^Salvar$/ }).click()
    await saveResponse
    await expect(dialog).toBeHidden()
}

test.describe('Customers CRUD', () => {
    test.beforeEach(async ({ page }) => {
        await installAdminSession(page, await loginAdminByApi(page))
    })

    test('navigate to customers page and see list', async ({ page }) => {
        await goToCustomersPage(page)
    })

    test('create new customer PF', async ({ page }) => {
        await goToCustomersPage(page)

        const customerName = uniqueLabel('Cliente PF E2E')
        const dialog = await openCustomerForm(page)
        await dialog.getByRole('button', { name: /pessoa física/i }).click()

        await dialog.locator('input[name="name"]').fill(customerName)
        await dialog.locator('input[name="email"]').fill(`${customerName.toLowerCase().replace(/[^a-z0-9]+/g, '.')}@example.com`)
        await dialog.locator('input[name="phone"]').fill('11999998888')

        await saveCustomerForm(page, dialog, 'POST')

        await expect(await findCustomerCard(page, customerName)).toBeVisible()
    })

    test('create new customer PJ', async ({ page }) => {
        await goToCustomersPage(page)

        const customerName = uniqueLabel('Empresa PJ E2E')
        const dialog = await openCustomerForm(page)
        await dialog.getByRole('button', { name: /pessoa jurídica/i }).click()

        await dialog.locator('input[name="name"]').fill(customerName)
        await dialog.locator('input[name="email"]').fill(`${customerName.toLowerCase().replace(/[^a-z0-9]+/g, '.')}@example.com`)

        await saveCustomerForm(page, dialog, 'POST')

        await expect(await findCustomerCard(page, customerName)).toBeVisible()
    })

    test('edit existing customer', async ({ page }) => {
        const customer = await createCustomer(page, uniqueLabel('Cliente Editar E2E'))
        const editedName = `${customer.name} Atualizado`

        await goToCustomersPage(page)
        const card = await findCustomerCard(page, customer.name)

        await card.hover()
        await card.getByRole('button', { name: /editar/i }).click()

        const dialog = page.getByRole('dialog', { name: /editar cliente/i })
        await expect(dialog).toBeVisible()
        await dialog.locator('input[name="name"]').fill(editedName)
        await saveCustomerForm(page, dialog, 'PUT')

        await expect(await findCustomerCard(page, editedName)).toBeVisible()
    })

    test('search customer by name', async ({ page }) => {
        const customer = await createCustomer(page, uniqueLabel('Cliente Busca E2E'))

        await goToCustomersPage(page)

        await expect(await findCustomerCard(page, customer.name)).toBeVisible()
    })

    test('filter customers by type PF/PJ', async ({ page }) => {
        const customer = await createCustomer(page, uniqueLabel('Cliente Filtro PF E2E'), 'PF')

        await goToCustomersPage(page)
        await searchInput(page).fill(customer.name)

        const typeFilter = page.getByLabel(/filtrar por tipo/i)
        await expect(typeFilter, 'Tela de clientes deve expor filtro de tipo PF/PJ').toBeVisible()
        await typeFilter.selectOption('PF')

        await expect(await findCustomerCard(page, customer.name)).toBeVisible()
    })

    test('delete customer shows confirmation dialog and can be cancelled', async ({ page }) => {
        const customer = await createCustomer(page, uniqueLabel('Cliente Cancelar Exclusao E2E'))

        await goToCustomersPage(page)
        const card = await findCustomerCard(page, customer.name)

        await card.hover()
        await card.getByRole('button', { name: /excluir/i }).click()

        const dialog = page.getByRole('dialog', { name: /excluir cliente/i })
        await expect(dialog).toBeVisible()

        await dialog.getByRole('button', { name: /cancelar/i }).click()

        await expect(dialog).toBeHidden()
        await expect(await findCustomerCard(page, customer.name)).toBeVisible()
    })

    test('delete customer with confirmation removes from list', async ({ page }) => {
        const customer = await createCustomer(page, uniqueLabel('Cliente Excluir E2E'))

        await goToCustomersPage(page)
        const card = await findCustomerCard(page, customer.name)

        await card.hover()
        await card.getByRole('button', { name: /excluir/i }).click()

        const dialog = page.getByRole('dialog', { name: /excluir cliente/i })
        await expect(dialog).toBeVisible()
        await dialog.getByRole('button', { name: /^Excluir$/ }).click()

        await expect(dialog).toBeHidden({ timeout: 10000 })
        await expect(page.getByTestId('customer-card').filter({ hasText: customer.name }).first()).toBeHidden({ timeout: 10000 })
    })

    test('pagination navigation works', async ({ page }) => {
        const prefix = uniqueLabel('Cliente Paginacao E2E')
        await seedCustomerGroup(page, prefix, 21)

        await goToCustomersPage(page)
        await fillCustomerSearch(page, prefix)
        await expect(page.getByText(/1 \/ 2/)).toBeVisible({ timeout: 30000 })

        const nextPage = page.getByRole('button', { name: /pr[oó]xima|next/i })
        await expect(nextPage, 'Tela de clientes deve expor paginacao para navegar resultados').toBeEnabled()

        await nextPage.click()

        await expect(page.getByText(/2 \/ 2/)).toBeVisible({ timeout: 30000 })
        await expect(page.getByTestId('customer-card').filter({ hasText: `${prefix} 21` }).first()).toBeVisible()
    })

    test('export customers button exists', async ({ page }) => {
        const customer = await createCustomer(page, uniqueLabel('Cliente Exportar E2E'))

        await goToCustomersPage(page)
        const card = await findCustomerCard(page, customer.name)
        await card.hover()

        const exportBtn = card.getByRole('button', { name: /exportar/i })
        await expect(exportBtn).toBeVisible()
    })
})
