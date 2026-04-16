import { http, HttpResponse } from 'msw'
import { createPaginatedResponse } from '../factories'

const API = '/api/v1'

function createQuote(overrides: Record<string, unknown> = {}) {
    return {
        id: 1,
        tenant_id: 1,
        number: 'ORC-001',
        status: 'draft',
        customer_id: 1,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        ...overrides,
    }
}

export const quoteHandlers = [
    http.get(`${API}/quotes`, () =>
        HttpResponse.json(createPaginatedResponse([createQuote()]))
    ),
    http.get(`${API}/quotes/:id`, ({ params }) =>
        HttpResponse.json({ data: createQuote({ id: Number(params.id) }) })
    ),
    http.post(`${API}/quotes`, async ({ request }) => {
        const body = (await request.json()) as Record<string, unknown>
        return HttpResponse.json(
            { data: { id: 1, ...body, created_at: new Date().toISOString(), updated_at: new Date().toISOString() } },
            { status: 201 }
        )
    }),
    http.put(`${API}/quotes/:id`, async ({ request, params }) => {
        const body = (await request.json()) as Record<string, unknown>
        return HttpResponse.json({
            data: { id: Number(params.id), ...body, updated_at: new Date().toISOString() },
        })
    }),
    http.patch(`${API}/quotes/:id/status`, async ({ request, params }) => {
        const body = (await request.json()) as Record<string, unknown>
        return HttpResponse.json({
            data: { id: Number(params.id), status: body.status ?? 'sent', updated_at: new Date().toISOString() },
        })
    }),
    http.delete(`${API}/quotes/:id`, () => HttpResponse.json(null, { status: 204 })),
]
