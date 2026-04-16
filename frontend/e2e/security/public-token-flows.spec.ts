import { test, expect } from '@playwright/test'

const publicQuotePayload = {
    id: 10,
    quote_number: 'ORC-E2E-010',
    reference: 'Proposta E2E Token',
    total: 1250.5,
    valid_until: '2026-12-31',
    customer_name: 'Cliente E2E Token',
    company_name: 'Kalibrium E2E',
    payment_terms: 'Entrada e saldo na entrega.',
    general_conditions: 'Token publico sem autenticacao.',
    items: [
        {
            id: 1,
            description: 'Calibracao rastreavel',
            quantity: 1,
            unit_price: 1250.5,
            subtotal: 1250.5,
        },
    ],
}

async function mockPublicQuote(page: import('@playwright/test').Page): Promise<string[]> {
    const requestedPaths: string[] = []

    await page.route(/\/api\/(?:v1\/)?quotes\/proposal\//, async (route) => {
        const request = route.request()
        const url = new URL(request.url())
        requestedPaths.push(`${request.method()} ${url.pathname}`)

        if (url.pathname.endsWith('/token-valido') && request.method() === 'GET') {
            await route.fulfill({ status: 200, json: { data: publicQuotePayload } })
            return
        }

        if (url.pathname.endsWith('/token-valido/approve') && request.method() === 'POST') {
            await route.fulfill({ status: 200, json: { message: 'Proposta aprovada com sucesso!' } })
            return
        }

        if (url.pathname.endsWith('/token-consumido/approve') && request.method() === 'POST') {
            await route.fulfill({ status: 410, json: { message: 'Token ja consumido.' } })
            return
        }

        if (url.pathname.endsWith('/token-consumido') && request.method() === 'GET') {
            await route.fulfill({ status: 200, json: { data: publicQuotePayload } })
            return
        }

        await route.fulfill({ status: 404, json: { message: 'Proposta nao encontrada.' } })
    })

    return requestedPaths
}

test.describe('Security - Public token flows', () => {
    test('token valido exibe proposta e aprova sem sessao autenticada', async ({ page }) => {
        const requestedPaths = await mockPublicQuote(page)

        await page.goto('/quotes/proposal/token-valido')

        await expect(page.getByText('Cliente E2E Token')).toBeVisible()
        await expect(page.getByText('ORC-E2E-010')).toBeVisible()
        await page.getByLabel('Aceitar termos da proposta').check()
        await page.getByRole('button', { name: 'Aprovar proposta' }).click()

        await expect(page.getByText('Aprovacao concluida')).toBeVisible()
        expect(requestedPaths).toContain('GET /api/quotes/proposal/token-valido')
        expect(requestedPaths).toContain('POST /api/quotes/proposal/token-valido/approve')
    })

    test('token invalido nao vaza dados da proposta', async ({ page }) => {
        await mockPublicQuote(page)

        await page.goto('/quotes/proposal/token-invalido')

        await expect(page.getByRole('heading', { name: 'Proposta indisponivel' })).toBeVisible()
        await expect(page.getByText('Cliente E2E Token')).toHaveCount(0)
    })

    test('token consumido exibe erro real ao tentar aprovar', async ({ page }) => {
        await mockPublicQuote(page)

        await page.goto('/quotes/proposal/token-consumido')

        await expect(page.getByText('Cliente E2E Token')).toBeVisible()
        await page.getByLabel('Aceitar termos da proposta').check()
        await page.getByRole('button', { name: 'Aprovar proposta' }).click()

        await expect(page.getByText('Token ja consumido.').last()).toBeVisible()
    })
})
