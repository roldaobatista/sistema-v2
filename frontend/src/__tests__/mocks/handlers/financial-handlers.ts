import { http, HttpResponse } from 'msw'
import { createPaginatedResponse } from '../factories'

const API = '/api/v1'

export const financialHandlers = [
    http.get(`${API}/accounts-receivable`, () =>
        HttpResponse.json(createPaginatedResponse([]))
    ),
    http.get(`${API}/accounts-payable`, () =>
        HttpResponse.json(createPaginatedResponse([]))
    ),
    http.get(`${API}/expenses`, () =>
        HttpResponse.json(createPaginatedResponse([]))
    ),
    http.get(`${API}/cash-flow`, () =>
        HttpResponse.json([])
    ),
    http.get(`${API}/dre`, () =>
        HttpResponse.json({ revenue: 0, costs: 0, gross_profit: 0 })
    ),
    http.get(`${API}/bank-accounts`, () =>
        HttpResponse.json(createPaginatedResponse([]))
    ),
    http.post(`${API}/accounts-receivable`, async ({ request }) => {
        const body = (await request.json()) as Record<string, any>
        return HttpResponse.json(
            { data: { id: 1, ...body, created_at: new Date().toISOString() } },
            { status: 201 }
        )
    }),
    http.post(`${API}/accounts-payable`, async ({ request }) => {
        const body = (await request.json()) as Record<string, any>
        return HttpResponse.json(
            { data: { id: 1, ...body, created_at: new Date().toISOString() } },
            { status: 201 }
        )
    }),
    http.post(`${API}/expenses`, async ({ request }) => {
        const body = (await request.json()) as Record<string, any>
        return HttpResponse.json(
            { data: { id: 1, ...body, created_at: new Date().toISOString() } },
            { status: 201 }
        )
    }),
]
