import { http, HttpResponse } from 'msw'
import { createPaginatedResponse } from '../factories'

const API = '/api/v1'

function createService(overrides: Record<string, any> = {}) {
    return {
        id: 1,
        tenant_id: 1,
        name: 'Serviço Teste',
        code: 'SRV-001',
        default_price: 100,
        is_active: true,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        ...overrides,
    }
}

export const serviceHandlers = [
    http.get(`${API}/services`, () =>
        HttpResponse.json(createPaginatedResponse([createService()]))
    ),
    http.get(`${API}/services/:id`, ({ params }) =>
        HttpResponse.json({ data: createService({ id: Number(params.id) }) })
    ),
    http.post(`${API}/services`, async ({ request }) => {
        const body = (await request.json()) as Record<string, any>
        return HttpResponse.json(
            { data: { id: 1, ...body, created_at: new Date().toISOString(), updated_at: new Date().toISOString() } },
            { status: 201 }
        )
    }),
    http.put(`${API}/services/:id`, async ({ request, params }) => {
        const body = (await request.json()) as Record<string, any>
        return HttpResponse.json({
            data: { id: Number(params.id), ...body, updated_at: new Date().toISOString() },
        })
    }),
    http.delete(`${API}/services/:id`, () => HttpResponse.json(null, { status: 204 })),
]
