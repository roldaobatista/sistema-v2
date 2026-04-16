import { test, expect, type Page } from '@playwright/test'
import { loginAsAdmin, BASE, waitForAppReady } from './helpers'

function normalizeText(value: string | null | undefined) {
    return (value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
}

async function stubLayoutBackgroundRequests(page: Page) {
    await page.route('**/api/v1/notifications/unread-count', async (route) => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                success: true,
                data: { unread_count: 0 },
            }),
        })
    })

    await page.route('**/api/v1/my-tenants', async (route) => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                success: true,
                data: [
                    {
                        id: 1,
                        name: 'Empresa Principal',
                        document: '00.000.000/0001-00',
                        status: 'active',
                    },
                ],
            }),
        })
    })

    await page.route('**/api/v1/dashboard/team-status', async (route) => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                success: true,
                data: {
                    total_technicians: 0,
                    online: 0,
                    in_transit: 0,
                    working: 0,
                    idle: 0,
                    offline: 0,
                    active_work_orders: 0,
                    pending_work_orders: 0,
                },
            }),
        })
    })
}

interface SmokePageConfig {
    name: string
    path: string
    signals: string[]
    timeoutMs?: number
}

test.describe('Navegacao - Paginas Principais', () => {
    test('sidebar deve conter menus principais', async ({ page }) => {
        const loggedIn = await loginAsAdmin(page)
        expect(loggedIn, 'Login admin E2E deve estar disponivel para validar navegacao').toBe(true)

        await page.waitForSelector('nav', { timeout: 5000 })
        const menuTexts = await page.locator('nav').textContent()
        expect(menuTexts).toContain('Dashboard')
    })

    const pages: SmokePageConfig[] = [
        { name: 'Dashboard', path: '/', signals: ['dashboard', 'painel', 'ola', 'administrador'] },
        { name: 'Clientes', path: '/cadastros/clientes', signals: ['clientes', 'novo cliente', 'buscar por nome'] },
        { name: 'Produtos', path: '/cadastros/produtos', signals: ['produtos', 'novo produto', 'buscar por nome ou codigo'] },
        { name: 'Servicos', path: '/cadastros/servicos', signals: ['servicos', 'novo servico', 'catalogo de servicos'] },
        { name: 'Fornecedores', path: '/cadastros/fornecedores', signals: ['fornecedores', 'novo fornecedor', 'cadastro de fornecedores'] },
        { name: 'Ordens de Servico', path: '/os', signals: ['ordens de servico', 'kanban', 'nova os'] },
        { name: 'Orcamentos', path: '/orcamentos', signals: ['orcamentos', 'novo orcamento', 'dashboard'] },
        { name: 'Chamados', path: '/chamados', signals: ['chamados', 'novo chamado', 'dashboard'] },
        { name: 'Contas a Receber', path: '/financeiro/receber', signals: ['contas a receber', 'receber', 'recebimentos'] },
        { name: 'Contas a Pagar', path: '/financeiro/pagar', signals: ['contas a pagar', 'pagar', 'pagamentos'] },
        { name: 'Comissoes', path: '/financeiro/comissoes', signals: ['gestao de comissoes', 'visao geral', 'eventos'] },
        { name: 'Despesas', path: '/financeiro/despesas', signals: ['despesas', 'nova despesa', 'categoria'] },
        { name: 'Fluxo de Caixa', path: '/financeiro/fluxo-caixa', signals: ['fluxo de caixa', 'entradas', 'saidas'] },
        { name: 'Relatorios', path: '/relatorios', signals: ['relatorios', 'analise de desempenho', 'csv'] },
        { name: 'Estoque', path: '/estoque', signals: ['estoque', 'movimentacoes', 'inventarios'] },
        { name: 'Equipamentos', path: '/equipamentos', signals: ['equipamentos', 'novo equipamento', 'modelo'] },
        { name: 'INMETRO', path: '/inmetro', signals: ['inteligencia inmetro', 'proprietarios', 'instrumentos'], timeoutMs: 60_000 },
        { name: 'CRM', path: '/crm', signals: ['crm', 'pipeline', 'clientes'] },
        { name: 'Configuracoes', path: '/configuracoes', signals: ['configuracoes', 'numeracao', 'auditoria'] },
        { name: 'Perfil', path: '/perfil', signals: ['dados pessoais', 'alterar senha', 'informacoes da conta'] },
    ]

    for (const pageConfig of pages) {
        test(`pagina ${pageConfig.name} deve carregar (${pageConfig.path})`, async ({ page }) => {
            const contentTimeout = pageConfig.timeoutMs ?? 30_000
            test.setTimeout(Math.max(contentTimeout + 10_000, 30_000))

            const loggedIn = await loginAsAdmin(page, { navigateToApp: false })
            expect(loggedIn, `Login admin E2E deve estar disponivel para pagina ${pageConfig.name}`).toBe(true)

            await stubLayoutBackgroundRequests(page)
            await page.goto(BASE + pageConfig.path, { waitUntil: 'domcontentloaded' })
            await waitForAppReady(page)
            await expect(page).not.toHaveURL(/\/login$/, { timeout: 10000 })
            await expect(page.locator('main')).toBeVisible({ timeout: 10000 })

            await expect.poll(
                async () => {
                    const mainText = normalizeText(await page.locator('main').textContent({ timeout: 1000 }).catch(() => ''))

                    return pageConfig.signals.some((signal) => mainText.includes(normalizeText(signal)))
                },
                {
                    timeout: contentTimeout,
                    message: `A pagina ${pageConfig.name} deve renderizar um dos sinais esperados`,
                }
            ).toBe(true)
        })
    }
})
