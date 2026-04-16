import { http, HttpResponse } from 'msw'
import { createPaginatedResponse } from '../factories'

const API = '/api/v1'

function createCentralItem(overrides: Record<string, any> = {}) {
    return {
        id: 1,
        tenant_id: 1,
        title: 'Item Central',
        status: 'open',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        ...overrides,
    }
}

export const centralHandlers = [
    http.get(`${API}/agenda/items`, () =>
        HttpResponse.json(createPaginatedResponse([createCentralItem()]))
    ),
    http.get(`${API}/agenda/items/:id`, ({ params }) =>
        HttpResponse.json({ data: createCentralItem({ id: Number(params.id) }) })
    ),
    http.post(`${API}/agenda/items`, async ({ request }) => {
        const body = (await request.json()) as Record<string, any>
        return HttpResponse.json(
            { data: { id: 1, ...body, created_at: new Date().toISOString(), updated_at: new Date().toISOString() } },
            { status: 201 }
        )
    }),
    http.put(`${API}/agenda/items/:id`, async ({ request, params }) => {
        const body = (await request.json()) as Record<string, any>
        return HttpResponse.json({
            data: { id: Number(params.id), ...body, updated_at: new Date().toISOString() },
        })
    }),
    http.delete(`${API}/agenda/items/:id`, () => HttpResponse.json(null, { status: 204 })),
]
