import { http, HttpResponse } from 'msw'
import { createPaginatedResponse } from '../factories'

const API = '/api/v1'

export const stockHandlers = [
    http.get(`${API}/warehouses`, () =>
        HttpResponse.json(createPaginatedResponse([]))
    ),
    http.get(`${API}/batches`, () =>
        HttpResponse.json(createPaginatedResponse([]))
    ),
    http.get(`${API}/stock-movements`, () =>
        HttpResponse.json(createPaginatedResponse([]))
    ),
    http.post(`${API}/warehouses`, async ({ request }) => {
        const body = (await request.json()) as Record<string, any>
        return HttpResponse.json(
            { data: { id: 1, ...body, created_at: new Date().toISOString() } },
            { status: 201 }
        )
    }),
    http.put(`${API}/warehouses/:id`, async ({ request, params }) => {
        const body = (await request.json()) as Record<string, any>
        return HttpResponse.json({
            data: { id: Number(params.id), ...body, updated_at: new Date().toISOString() },
        })
    }),
    http.delete(`${API}/warehouses/:id`, () => HttpResponse.json(null, { status: 204 })),
]
