import { test, expect, type Page } from '@playwright/test'
import { loginAsAdmin, BASE, waitForAppReady } from './helpers'

async function ensureLoggedIn(page: Page) {
    const ok = await loginAsAdmin(page)
    expect(ok, 'Login admin E2E deve estar disponivel para fluxos de exclusao').toBe(true)
}

async function gotoAndWait(page: Page, path: string) {
    await page.goto(BASE + path, { waitUntil: 'domcontentloaded' })
    await waitForAppReady(page)
}

type DeleteFixtureOptions = {
    deleteStatus?: 204 | 403 | 409
    deleteMessage?: string
}

const today = new Date().toISOString().slice(0, 10)

const quoteFixture = {
    id: 9101,
    tenant_id: 1,
    quote_number: 'ORC-E2E-DELETE',
    revision: 1,
    customer_id: 9201,
    seller_id: 1,
    created_by: 1,
    status: 'draft',
    source: 'e2e',
    valid_until: '2026-12-31',
    discount_percentage: 0,
    discount_amount: 0,
    displacement_value: 0,
    subtotal: 100,
    total: 100,
    observations: null,
    internal_notes: null,
    general_conditions: null,
    payment_terms: null,
    payment_terms_detail: null,
    template_id: null,
    opportunity_id: null,
    currency: 'BRL',
    validity_days: 30,
    custom_fields: null,
    is_template: false,
    internal_approved_by: null,
    internal_approved_at: null,
    level2_approved_by: null,
    level2_approved_at: null,
    sent_at: null,
    approved_at: null,
    rejected_at: null,
    rejection_reason: null,
    last_followup_at: null,
    followup_count: 0,
    client_viewed_at: null,
    client_view_count: 0,
    is_installation_testing: false,
    created_at: `${today}T00:00:00.000000Z`,
    updated_at: `${today}T00:00:00.000000Z`,
    deleted_at: null,
    customer: { id: 9201, name: 'Cliente E2E Exclusao' },
    seller: { id: 1, name: 'Administrador' },
    tags: [],
}

const receivableFixture = {
    id: 9301,
    description: 'Titulo E2E Exclusao',
    amount: '150.00',
    amount_paid: '0.00',
    due_date: today,
    status: 'pending',
    payment_method: 'pix',
    notes: null,
    chart_of_account_id: null,
    customer: { id: 9201, name: 'Cliente E2E Exclusao' },
    work_order: null,
    payments: [],
}

const payableFixture = {
    id: 9401,
    supplier_id: null,
    category_id: 9501,
    chart_of_account_id: null,
    supplier_relation: { id: 9601, name: 'Fornecedor E2E Exclusao' },
    category_relation: { id: 9501, name: 'Operacional', color: '#2563eb' },
    description: 'Conta E2E Exclusao',
    amount: '200.00',
    amount_paid: '0.00',
    due_date: today,
    paid_at: null,
    status: 'pending',
    payment_method: 'pix',
    notes: null,
    payments: [],
}

const expenseFixture = {
    id: 9701,
    description: 'Despesa E2E Exclusao',
    amount: '80.00',
    expense_date: today,
    status: 'pending',
    payment_method: 'pix',
    notes: null,
    receipt_path: null,
    affects_technician_cash: false,
    affects_net_value: true,
    category: { id: 9801, name: 'Operacional', color: '#2563eb' },
    creator: { id: 1, name: 'Administrador' },
    work_order: null,
}

const paymentMethodFixture = {
    id: 9901,
    name: 'PIX E2E Exclusao',
    code: 'pix_e2e_delete',
    is_active: true,
}

async function setupDeleteFixtures(page: Page, options: DeleteFixtureOptions = {}) {
    await page.route('**/api/v1/**', async (route) => {
        const request = route.request()
        const url = new URL(request.url())
        const path = url.pathname.replace(/^.*\/api\/v1/, '')

        const fulfillJson = (body: unknown, status = 200) => route.fulfill({
            status,
            contentType: 'application/json',
            body: JSON.stringify(body),
        })

        if (request.method() === 'DELETE') {
            const status = options.deleteStatus ?? 204
            if (status === 204) {
                await route.fulfill({ status })
                return
            }

            await fulfillJson({ message: options.deleteMessage ?? 'Falha ao excluir registro E2E.' }, status)
            return
        }

        if (request.method() !== 'GET') {
            await route.continue()
            return
        }

        if (path === '/quotes') {
            await fulfillJson({ data: [quoteFixture], current_page: 1, last_page: 1, total: 1 })
            return
        }

        if (path === '/quotes-summary') {
            await fulfillJson({ data: { draft: 1, pending_internal_approval: 0, internally_approved: 0, sent: 0, approved: 0, rejected: 0, expired: 0, in_execution: 0, installation_testing: 0, renegotiation: 0, invoiced: 0, total_month: 100, conversion_rate: 0 } })
            return
        }

        if (path === '/quote-tags') {
            await fulfillJson({ data: [] })
            return
        }

        if (path === '/users') {
            await fulfillJson({ data: [{ id: 1, name: 'Administrador' }], current_page: 1, last_page: 1, total: 1 })
            return
        }

        if (path === '/accounts-receivable') {
            await fulfillJson({ data: [receivableFixture], current_page: 1, last_page: 1, total: 1 })
            return
        }

        if (path === '/accounts-receivable-summary') {
            await fulfillJson({ pending: 150, overdue: 0, billed_this_month: 150, received_this_month: 0, total_open: 150 })
            return
        }

        if (path === '/accounts-payable') {
            await fulfillJson({ data: [payableFixture], current_page: 1, last_page: 1, total: 1 })
            return
        }

        if (path === '/accounts-payable-summary') {
            await fulfillJson({ pending: 200, overdue: 0, recorded_this_month: 200, paid_this_month: 0, total_open: 200 })
            return
        }

        if (path === '/account-payable-categories') {
            await fulfillJson({ data: [payableFixture.category_relation] })
            return
        }

        if (path === '/expenses') {
            await fulfillJson({ data: [expenseFixture], current_page: 1, last_page: 1, total: 1 })
            return
        }

        if (path === '/expense-summary') {
            await fulfillJson({ pending: 80, reviewed: 0, approved: 0, rejected: 0, reimbursed: 0, total: 80 })
            return
        }

        if (path === '/expense-categories') {
            await fulfillJson({ data: [expenseFixture.category] })
            return
        }

        if (path === '/expense-analytics') {
            await fulfillJson({ data: null })
            return
        }

        if (path === '/payment-methods' || path === '/financial/lookups/payment-methods') {
            await fulfillJson({ data: [paymentMethodFixture] })
            return
        }

        await route.continue()
    })
}

test.describe('Delete Flow - Orcamentos', () => {
    test('delete button opens confirmation dialog', async ({ page }) => {
        await ensureLoggedIn(page)
        await setupDeleteFixtures(page)
        await gotoAndWait(page, '/orcamentos')

        const deleteBtn = page.locator('button[title="Excluir"], button:has(svg.lucide-trash-2), [aria-label*="xcluir" i]').first()
        await expect(deleteBtn, 'Massa E2E deve exibir botao de exclusao em orcamentos').toBeVisible()

        await deleteBtn.click()
        await page.waitForTimeout(500)

        const dialog = page.locator('text=/certeza|confirmar|deseja excluir/i').first()
        await expect(dialog).toBeVisible({ timeout: 5000 })
    })

    test('cancel button closes dialog without deleting', async ({ page }) => {
        await ensureLoggedIn(page)
        await setupDeleteFixtures(page)
        await gotoAndWait(page, '/orcamentos')

        const deleteBtn = page.locator('button[title="Excluir"], button:has(svg.lucide-trash-2), [aria-label*="xcluir" i]').first()
        await expect(deleteBtn, 'Massa E2E deve exibir botao de exclusao para validar cancelamento').toBeVisible()

        const rowsBefore = await page.locator('tbody tr').count()

        await deleteBtn.click()
        await page.waitForTimeout(500)

        const cancelBtn = page.locator('button:has-text("Cancelar")').first()
        await expect(cancelBtn, 'Dialogo de exclusao deve expor botao Cancelar').toBeVisible()
        await cancelBtn.click()
        await page.waitForTimeout(500)

        const dialogGone = await page.locator('text=/certeza|deseja excluir/i').count() === 0
        expect(dialogGone).toBeTruthy()

        const rowsAfter = await page.locator('tbody tr').count()
        expect(rowsAfter).toBe(rowsBefore)
    })
})

test.describe('Delete Flow - Financeiro', () => {
    const pages = [
        { name: 'Contas a Receber', path: '/financeiro/receber' },
        { name: 'Contas a Pagar', path: '/financeiro/pagar' },
        { name: 'Despesas', path: '/financeiro/despesas' },
    ]

    for (const financialPage of pages) {
        test(`${financialPage.name}: delete opens confirmation modal`, async ({ page }) => {
            await ensureLoggedIn(page)
            await setupDeleteFixtures(page)
            await gotoAndWait(page, financialPage.path)

            const deleteBtn = page.locator('button[title="Excluir"], button:has(svg.lucide-trash-2), [aria-label="Excluir"]').first()
            await expect(deleteBtn, `Massa E2E deve exibir botao de exclusao em ${financialPage.name}`).toBeVisible()

            await deleteBtn.click()
            await page.waitForTimeout(500)

            const hasConfirm = await page.locator('text=/certeza|confirmar|excluir/i').count() > 0
            const hasModal = await page.locator('[role="dialog"], .fixed.inset-0').count() > 0
            expect(hasConfirm || hasModal).toBeTruthy()
        })
    }
})

test.describe('Delete Flow - Error Handling', () => {
    test('delete with 409 conflict shows restriction message', async ({ page }) => {
        await ensureLoggedIn(page)
        await setupDeleteFixtures(page, {
            deleteStatus: 409,
            deleteMessage: 'Nao e possivel excluir: existem registros vinculados.',
        })

        await gotoAndWait(page, '/financeiro/formas-pagamento')

        const deleteBtn = page.locator('button[title="Excluir"], button:has(svg.lucide-trash-2), [aria-label="Excluir"]').first()
        await expect(deleteBtn, 'Massa E2E deve exibir botao de exclusao para validar erro 409').toBeVisible()

        await deleteBtn.click()
        await page.waitForTimeout(500)

        const confirmBtn = page.locator('button:has-text("Excluir"), button:has-text("Confirmar")').last()
        await expect(confirmBtn, 'Dialogo de exclusao deve expor acao de confirmacao').toBeVisible()
        await confirmBtn.click()
        await page.waitForTimeout(2000)

        const body = await page.textContent('body')
        const hasErrorMsg = body?.match(/nao e possivel|nao é possivel|vinculados|dependencia|dependência|erro/i) !== null
        expect(hasErrorMsg).toBeTruthy()
    })

    test('delete with 403 forbidden shows permission error', async ({ page }) => {
        await ensureLoggedIn(page)
        await setupDeleteFixtures(page, {
            deleteStatus: 403,
            deleteMessage: 'Sem permissao para exclusao.',
        })

        await gotoAndWait(page, '/financeiro/formas-pagamento')

        const deleteBtn = page.locator('button[title="Excluir"], button:has(svg.lucide-trash-2), [aria-label="Excluir"]').first()
        await expect(deleteBtn, 'Massa E2E deve exibir botao de exclusao para validar erro 403').toBeVisible()

        await deleteBtn.click()
        await page.waitForTimeout(500)

        const confirmBtn = page.locator('button:has-text("Excluir"), button:has-text("Confirmar")').last()
        await expect(confirmBtn, 'Dialogo de exclusao deve expor acao de confirmacao').toBeVisible()
        await confirmBtn.click()
        await page.waitForTimeout(2000)

        const body = await page.textContent('body')
        const hasPermError = body?.match(/permissao|permissão|proibido|403|forbidden/i) !== null
        expect(hasPermError).toBeTruthy()
    })
})
