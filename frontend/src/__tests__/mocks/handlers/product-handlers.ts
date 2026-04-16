import { http, HttpResponse } from 'msw'
import { createPaginatedResponse } from '../factories'

const API = '/api/v1'

function createProduct(overrides: Record<string, any> = {}) {
    return {
        id: 1,
        tenant_id: 1,
        name: 'Produto Teste',
        code: 'PROD-001',
        type: 'product',
        is_active: true,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        ...overrides,
    }
}

export const productHandlers = [
    http.get(`${API}/products`, () =>
        HttpResponse.json(createPaginatedResponse([createProduct()]))
    ),
    http.get(`${API}/products/:id`, ({ params }) =>
        HttpResponse.json({ data: createProduct({ id: Number(params.id) }) })
    ),
    http.post(`${API}/products`, async ({ request }) => {
        const body = (await request.json()) as Record<string, any>
        return HttpResponse.json(
            { data: { id: 1, ...body, created_at: new Date().toISOString(), updated_at: new Date().toISOString() } },
            { status: 201 }
        )
    }),
    http.put(`${API}/products/:id`, async ({ request, params }) => {
        const body = (await request.json()) as Record<string, any>
        return HttpResponse.json({
            data: { id: Number(params.id), ...body, updated_at: new Date().toISOString() },
        })
    }),
    http.delete(`${API}/products/:id`, () => HttpResponse.json(null, { status: 204 })),
]
