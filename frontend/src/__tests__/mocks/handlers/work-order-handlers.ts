import { http, HttpResponse } from 'msw'
import { createPaginatedResponse } from '../factories'

const API = '/api/v1'

function createWorkOrder(overrides: Record<string, unknown> = {}) {
    return {
        id: 1,
        tenant_id: 1,
        number: 'OS-001',
        status: 'open',
        customer_id: 1,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        ...overrides,
    }
}

export const workOrderHandlers = [
    http.get(`${API}/work-orders`, () =>
        HttpResponse.json(createPaginatedResponse([createWorkOrder()]))
    ),
    http.get(`${API}/work-orders/:id`, ({ params }) =>
        HttpResponse.json({ data: createWorkOrder({ id: Number(params.id) }) })
    ),
    http.post(`${API}/work-orders`, async ({ request }) => {
        const body = (await request.json()) as Record<string, unknown>
        return HttpResponse.json(
            { data: { id: 1, ...body, created_at: new Date().toISOString(), updated_at: new Date().toISOString() } },
            { status: 201 }
        )
    }),
    http.put(`${API}/work-orders/:id`, async ({ request, params }) => {
        const body = (await request.json()) as Record<string, unknown>
        return HttpResponse.json({
            data: { id: Number(params.id), ...body, updated_at: new Date().toISOString() },
        })
    }),
    http.post(`${API}/work-orders/:id/status`, async ({ request, params }) => {
        const body = (await request.json()) as Record<string, unknown>
        return HttpResponse.json({
            data: { id: Number(params.id), status: body.status ?? 'in_progress', updated_at: new Date().toISOString() },
        })
    }),
    http.delete(`${API}/work-orders/:id`, () => HttpResponse.json(null, { status: 204 })),
]
