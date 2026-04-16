import { test, expect, type Browser, type BrowserContext, type Page } from '@playwright/test'
import { API_BASE, BASE, waitForAppReady } from '../helpers'

type AuthProfile = {
    user: {
        email: string
        tenant_id: number | null
        roles: string[]
        permissions: string[]
    }
    tenant: { id: number; name: string } | null
}

type CustomerSummary = {
    name?: string
    tenant_id?: number
    document?: string
}

async function openState(browser: Browser, storageState: string): Promise<{ context: BrowserContext; page: Page }> {
    const context = await browser.newContext({ storageState })
    const page = await context.newPage()
    await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded' })
    return { context, page }
}

async function tokenFromPage(page: Page): Promise<string> {
    const token = await page.evaluate(() => window.localStorage.getItem('auth_token'))
    if (!token) {
        throw new Error('storageState E2E sem auth_token.')
    }
    return token
}

async function profileFromPage(page: Page): Promise<AuthProfile> {
    const token = await tokenFromPage(page)
    const response = await page.request.get(`${API_BASE}/me`, {
        headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
        },
    })

    const bodyText = await response.text()
    expect(response.status(), bodyText).toBe(200)

    const payload = JSON.parse(bodyText).data
    return {
        user: payload.user ?? payload,
        tenant: (payload.user ?? payload).tenant ?? payload.tenant ?? null,
    }
}

function customerItems(payload: unknown): CustomerSummary[] {
    if (!payload || typeof payload !== 'object') return []
    const root = payload as { data?: unknown }
    const data = root.data

    if (Array.isArray(data)) return data as CustomerSummary[]
    if (data && typeof data === 'object' && Array.isArray((data as { data?: unknown }).data)) {
        return (data as { data: CustomerSummary[] }).data
    }

    return []
}

async function searchCustomers(page: Page, token: string, search: string): Promise<CustomerSummary[]> {
    const params = new URLSearchParams({ search, per_page: '10' })
    const response = await page.request.get(`${API_BASE}/customers?${params.toString()}`, {
        headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
        },
    })

    const bodyText = await response.text()
    expect(response.status(), bodyText).toBe(200)

    return customerItems(JSON.parse(bodyText))
}

test.describe('Security - storageState por tenant e perfil', () => {
    test('admin e usuario restrito usam sessoes distintas', async ({ browser }) => {
        const adminState = await openState(browser, 'e2e/.auth/admin.json')
        const restrictedState = await openState(browser, 'e2e/.auth/restricted.json')

        try {
            const admin = await profileFromPage(adminState.page)
            const restricted = await profileFromPage(restrictedState.page)

            expect(admin.user.email).toBe('admin@example.test')
            expect(restricted.user.email).toBe('ricardo@techassist.com.br')
            expect(restricted.user.roles).not.toContain('super-admin')
            expect(restricted.user.tenant_id).not.toBe(admin.user.tenant_id)
        } finally {
            await adminState.context.close()
            await restrictedState.context.close()
        }
    })
})

test.describe('Security - tenant restrito', () => {
    test.use({ storageState: 'e2e/.auth/restricted.json' })

    test('usuario restrito nao acessa rotas administrativas de tenants', async ({ page }) => {
        await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' })
        await waitForAppReady(page)

        const token = await tokenFromPage(page)
        const response = await page.request.get(`${API_BASE}/tenants`, {
            headers: {
                Authorization: `Bearer ${token}`,
                Accept: 'application/json',
            },
        })

        expect([403, 404]).toContain(response.status())
    })

    test('usuario restrito enxerga apenas fixture tenant-bound do proprio tenant', async ({ page }) => {
        await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' })
        await waitForAppReady(page)

        const token = await tokenFromPage(page)
        const ownCustomers = await searchCustomers(page, token, 'Cliente E2E TechAssist')
        const foreignCustomers = await searchCustomers(page, token, 'Cliente E2E Calibracoes Brasil')

        expect(ownCustomers.some(customer => customer.name === 'Cliente E2E TechAssist')).toBe(true)
        expect(foreignCustomers.some(customer => customer.name === 'Cliente E2E Calibracoes Brasil')).toBe(false)
    })
})
