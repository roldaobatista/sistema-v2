import { test, expect, type Page } from '@playwright/test'
import { gotoAuthenticated, loginAsAdmin } from './helpers'

async function ensureLoggedIn(page: Page) {
    const ok = await loginAsAdmin(page, { navigateToApp: false })
    expect(ok, 'Login admin E2E deve estar disponivel para paginas administrativas').toBe(true)
}

async function gotoAndWait(page: Page, path: string) {
    await gotoAuthenticated(page, path)
    await expect(page).toHaveURL(new RegExp(`${path.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`), { timeout: 15000 })
    await expect(page.locator('main')).toBeVisible({ timeout: 15000 })
}

function normalizeText(value: string | null | undefined) {
    return (value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
}

async function expectPageText(page: Page, tokens: string[]) {
    const heading = page.locator('h1, h2, [data-testid="page-title"]').first()
    const headingText = normalizeText(await heading.textContent().catch(() => ''))
    const mainText = normalizeText(await page.locator('main').textContent())
    const combinedText = `${headingText}\n${mainText}`

    expect(tokens.some((token) => combinedText.includes(normalizeText(token)))).toBeTruthy()
}

test.describe('IAM - Users & Roles', () => {
    test('users page loads', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/iam/usuarios')
        await expectPageText(page, ['usuarios', 'novo usuario', 'status'])
    })

    test('roles page loads', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/iam/roles')
        await expectPageText(page, ['roles', 'nova role', 'permissoes'])
    })

    test('permissions matrix page loads', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/iam/permissoes')
        await expectPageText(page, ['matriz de permissoes', 'concedida', 'negada'])
    })
})

test.describe('Configuracoes', () => {
    test('settings page loads', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/configuracoes')
        await expectPageText(page, ['configuracoes', 'numeracao', 'auditoria'])
    })

    test('profile page loads', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/perfil')
        await expectPageText(page, ['dados pessoais', 'alterar senha', 'informacoes da conta'])
    })

    test('audit logs page loads', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/configuracoes/auditoria')
        await expect(page.locator('main')).toBeVisible({ timeout: 15000 })
    })
})

test.describe('Relatorios', () => {
    test('reports page loads', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/relatorios')
        await expectPageText(page, ['relatorios', 'analise de desempenho', 'csv'])
    })
})

test.describe('Financeiro - Paginas Avancadas', () => {
    test('cash flow page loads', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/financeiro/fluxo-caixa')
        await expectPageText(page, ['fluxo de caixa', 'entradas', 'saidas'])
    })

    test('payment methods page loads', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/financeiro/formas-pagamento')
        await expectPageText(page, ['formas de pagamento', 'metodo', 'pagamento'])
    })

    test('commissions page loads', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/financeiro/comissoes')
        await expectPageText(page, ['gestao de comissoes', 'visao geral', 'eventos'])
    })

    test('invoices page loads', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/financeiro/faturamento')
        await expect(page.locator('main')).toBeVisible({ timeout: 15000 })
    })
})

test.describe('Cadastros - Produtos, Servicos, Fornecedores', () => {
    test('products page loads', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/cadastros/produtos')
        await expectPageText(page, ['produtos', 'novo produto', 'buscar por nome ou codigo'])
    })

    test('services page loads', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/cadastros/servicos')
        await expectPageText(page, ['servicos', 'novo servico', 'catalogo de servicos'])
    })

    test('suppliers page loads', async ({ page }) => {
        await ensureLoggedIn(page)
        await gotoAndWait(page, '/cadastros/fornecedores')
        await expectPageText(page, ['fornecedores', 'novo fornecedor', 'cadastro de fornecedores'])
    })
})
