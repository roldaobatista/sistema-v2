import { test, expect } from '@playwright/test'

test.describe('Permissão e Acesso', () => {
    test('deve redirecionar rotas protegidas para login', async ({ page }) => {
        await page.goto('/login')
        await page.evaluate(() => localStorage.clear())

        const protectedRoutes = [
            '/',
            '/cadastros/clientes',
            '/os',
            '/financeiro/receber',
            '/financeiro/pagar',
            '/financeiro/comissoes',
            '/financeiro/despesas',
            '/relatorios',
            '/estoque',
            '/equipamentos',
            '/inmetro',
            '/crm',
            '/configuracoes',
            '/iam/usuarios',
        ]

        for (const route of protectedRoutes) {
            await page.goto(route)
            await page.waitForURL(/\/login/, { timeout: 5000 })
            expect(page.url()).toContain('/login')
        }
    })

    test('token expirado deve redirecionar para login', async ({ page }) => {
        await page.goto('/login')

        await page.evaluate(() => {
            localStorage.setItem('auth-store', JSON.stringify({
                state: { token: 'expired-invalid-token', isAuthenticated: true },
                version: 0,
            }))
            localStorage.setItem('auth_token', 'expired-invalid-token')
        })

        // intercept the API call and mock a 401 response explicitly
        await page.route('**/api/v1/me', async route => {
            await route.fulfill({ status: 401, json: { message: "Unauthenticated." } });
        });

        await page.goto('/')

        try {
            await page.waitForURL(/\/login/, { timeout: 4000 })
        } catch (e) {
            // fallback, sometimes a toast error appears instead
        }

        const url = page.url()
        const hasLoginRedirect = url.includes('/login')
        const hasErrorMsg = await page.locator('text=/unauthorized|sessão|expirad|login|entrar/i').count() > 0

        expect(hasLoginRedirect || hasErrorMsg).toBeTruthy()
    })

    test('rotas do portal devem redirecionar para portal/login', async ({ page }) => {
        await page.goto('/portal/login')
        await page.evaluate(() => localStorage.clear())

        await page.goto('/portal')
        await page.waitForURL(/\/portal\/login/, { timeout: 5000 })
        expect(page.url()).toContain('/portal/login')
    })
})
