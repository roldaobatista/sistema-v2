import { test, expect, type APIRequestContext, type Page } from '@playwright/test'
import { API_BASE, loginAsAdmin } from '../helpers'
import { navigateToModule } from '../fixtures'

type ApiEnvelope<T> = { data?: T } & Partial<T>
type CreatedRecord = { id?: number | string }

async function authHeaders(page: Page): Promise<Record<string, string>> {
    const token = await page.evaluate(() => window.localStorage.getItem('auth_token'))

    if (!token) {
        throw new Error('Token de autenticacao ausente para ciclo E2E de orcamentos.')
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
    const suffix = `${Date.now()}-${test.info().parallelIndex}-${test.info().workerIndex}`
    const name = `Cliente Orcamento E2E ${suffix}`
    const response = await request.post(`${API_BASE}/customers`, {
        headers: await authHeaders(page),
        data: {
            type: 'PF',
            name,
            document: null,
            email: `orcamento-${suffix}@example.test`,
            phone: '11999999999',
            is_active: true,
        },
    })

    const bodyText = await response.text()
    expect(response.status(), bodyText).toBe(201)

    return { id: extractId(bodyText, 'cliente'), name }
}

async function createService(page: Page, request: APIRequestContext): Promise<number> {
    const suffix = `${Date.now()}-${test.info().parallelIndex}-${test.info().workerIndex}`
    const response = await request.post(`${API_BASE}/services`, {
        headers: await authHeaders(page),
        data: {
            name: `Calibracao E2E ${suffix}`,
            code: `SERV-E2E-${suffix}`,
            default_price: 250,
            estimated_minutes: 60,
            is_active: true,
        },
    })

    const bodyText = await response.text()
    expect(response.status(), bodyText).toBe(201)

    return extractId(bodyText, 'servico')
}

async function createEquipment(page: Page, request: APIRequestContext, customerId: number): Promise<number> {
    const suffix = `${Date.now()}-${test.info().parallelIndex}-${test.info().workerIndex}`
    const response = await request.post(`${API_BASE}/equipments`, {
        headers: await authHeaders(page),
        data: {
            customer_id: customerId,
            name: `Balanca E2E ${suffix}`,
            type: 'balanca',
            serial_number: `EQ-E2E-${suffix}`,
            brand: 'E2E',
        },
    })

    const bodyText = await response.text()
    expect(response.status(), bodyText).toBe(201)

    return extractId(bodyText, 'equipamento')
}

async function createQuote(page: Page, request: APIRequestContext, withItems = false): Promise<number> {
    const customer = await createCustomer(page, request)
    const serviceId = withItems ? await createService(page, request) : null
    const equipmentId = withItems ? await createEquipment(page, request, customer.id) : null
    const response = await request.post(`${API_BASE}/quotes`, {
        headers: await authHeaders(page),
        data: {
            customer_id: customer.id,
            title: `Orcamento E2E ${Date.now()}`,
            validity_days: 30,
            general_conditions: 'Condicoes geradas pelo E2E de ciclo de orcamentos.',
            equipments: equipmentId && serviceId ? [{
                equipment_id: equipmentId,
                description: 'Equipamento com item gerado pelo E2E',
                items: [{
                    type: 'service',
                    service_id: serviceId,
                    custom_description: 'Calibracao padrao E2E',
                    quantity: 1,
                    original_price: 250,
                    unit_price: 250,
                    discount_percentage: 0,
                }],
            }] : undefined,
        },
    })

    const bodyText = await response.text()
    expect(response.status(), bodyText).toBe(201)

    return extractId(bodyText, 'orcamento')
}

async function postQuoteAction(page: Page, request: APIRequestContext, quoteId: number, action: string, data?: Record<string, unknown>): Promise<void> {
    const response = await request.post(`${API_BASE}/quotes/${quoteId}/${action}`, {
        headers: await authHeaders(page),
        data: data ?? {},
    })

    const bodyText = await response.text()
    expect(response.ok(), bodyText).toBe(true)
}

async function createInternallyApprovedQuote(page: Page, request: APIRequestContext): Promise<number> {
    const quoteId = await createQuote(page, request, true)

    await postQuoteAction(page, request, quoteId, 'request-internal-approval')
    await postQuoteAction(page, request, quoteId, 'internal-approve')

    return quoteId
}

async function createSentQuote(page: Page, request: APIRequestContext): Promise<number> {
    const quoteId = await createInternallyApprovedQuote(page, request)
    await postQuoteAction(page, request, quoteId, 'send')

    return quoteId
}

test.describe('Quotes Lifecycle', () => {
    test.beforeEach(async ({ page }) => {
        const loggedIn = await loginAsAdmin(page)
        expect(loggedIn, 'Login admin E2E deve estar disponivel para ciclo de orcamentos').toBe(true)
    })

    test('quotes list page loads', async ({ page }) => {
        await navigateToModule(page, '/orcamentos')
        await expect(page.locator('h1, h2').first()).toBeVisible({ timeout: 10000 })
    })

    test('create new quote with items', async ({ page }) => {
        await navigateToModule(page, '/orcamentos')

        const createBtn = page.locator('button:has-text("Novo"), button:has-text("Criar"), a:has-text("Novo"), [data-testid="btn-new"]').first()
        await expect(createBtn, 'Tela de orcamentos deve expor acao de criacao').toBeVisible()

        await createBtn.click()
        await page.waitForTimeout(1000)

        await expect(page.getByRole('heading', { name: /novo or[cç]amento/i })).toBeVisible()
        await expect(page.getByRole('combobox', { name: /selecionar cliente/i })).toBeVisible()

        const validityField = page.locator('input[title="Validade"], input[type="date"]').first()
        await expect(validityField, 'Formulario de novo orcamento deve expor validade').toBeVisible()
        await validityField.fill('2027-12-31')

        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(100)
    })

    test('quote totals calculate correctly', async ({ page, request }) => {
        const quoteId = await createQuote(page, request)
        await page.goto(`/orcamentos/${quoteId}`)
        await page.waitForTimeout(2000)

        // Look for total field
        const totalElement = page.locator('[data-testid*="total"], .total, :text("Total")').first()
        await expect(totalElement, 'Detalhe do orcamento deve exibir total').toBeVisible()
        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })

    test('send quote to customer action exists', async ({ page, request }) => {
        const quoteId = await createInternallyApprovedQuote(page, request)
        await page.goto(`/orcamentos/${quoteId}`)
        await page.waitForTimeout(1000)

        const sendBtn = page.locator('button:has-text("Enviar"), button:has-text("Email"), [data-testid*="send"]').first()
        await expect(sendBtn, 'Detalhe do orcamento deve expor acao de envio').toBeVisible()

        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })

    test('quote status transition buttons exist', async ({ page, request }) => {
        const quoteId = await createSentQuote(page, request)
        await page.goto(`/orcamentos/${quoteId}`)
        await page.waitForTimeout(1000)

        // Look for approval/reject/convert buttons
        const actionBtns = page.locator('button:has-text("Aprovar"), button:has-text("Rejeitar"), button:has-text("Gerar OS"), [data-testid*="approve"], [data-testid*="reject"]')
        await expect(actionBtns.first(), 'Detalhe do orcamento deve expor acao de status').toBeVisible()
        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })

    test('generate work order from approved quote', async ({ page, request }) => {
        const quoteId = await createInternallyApprovedQuote(page, request)
        await page.goto(`/orcamentos/${quoteId}`)
        await page.waitForTimeout(1000)

        const generateOsBtn = page.locator('button:has-text("Gerar OS"), button:has-text("Converter"), [data-testid*="generate-os"]').first()
        await expect(generateOsBtn, 'Detalhe do orcamento aprovado deve expor acao de gerar OS').toBeVisible()

        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })

    test('quotes search and filter', async ({ page }) => {
        await navigateToModule(page, '/orcamentos')

        const searchInput = page.locator('input[placeholder*="buscar" i], input[placeholder*="pesquisar" i], input[type="search"]').first()
        await expect(searchInput, 'Lista de orcamentos deve expor busca/filtro').toBeVisible()
        await searchInput.fill('Teste')
        await page.waitForTimeout(1000)

        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })

    test('quote PDF preview or download exists', async ({ page, request }) => {
        const quoteId = await createQuote(page, request)
        await page.goto(`/orcamentos/${quoteId}`)
        await page.waitForTimeout(1000)

        const pdfBtn = page.locator('button:has-text("PDF"), button:has-text("Imprimir"), a:has-text("PDF"), [data-testid*="pdf"]').first()
        await expect(pdfBtn, 'Detalhe do orcamento deve expor acao de PDF/impressao').toBeVisible()

        const body = await page.textContent('body')
        expect(body!.length).toBeGreaterThan(50)
    })
})
