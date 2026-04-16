import { http, HttpResponse } from 'msw'

const API = '/api/v1'

export const customerHandlers = [
    http.get(`${API}/customers`, () =>
        HttpResponse.json({ data: [], current_page: 1, last_page: 1, total: 0, per_page: 20 })
    ),
    http.get(`${API}/customers/options`, () =>
        HttpResponse.json({
            sources: {},
            segments: {},
            company_sizes: {},
            contract_types: {},
            ratings: {},
        })
    ),
    http.get(`${API}/customers/:id`, ({ params }) =>
        HttpResponse.json({
            data: {
                id: Number(params.id),
                name: 'Cliente Teste',
                type: 'PJ',
                document: null,
                email: null,
                is_active: true,
                created_at: new Date().toISOString(),
                updated_at: new Date().toISOString(),
            },
        })
    ),
    http.post(`${API}/customers`, async ({ request }) => {
        const body = (await request.json()) as Record<string, any>
        return HttpResponse.json(
            {
                data: {
                    id: 1,
                    ...body,
                    created_at: new Date().toISOString(),
                    updated_at: new Date().toISOString(),
                },
                message: 'Cliente criado com sucesso.',
            },
            { status: 201 }
        )
    }),
    http.put(`${API}/customers/:id`, async ({ request, params }) => {
        const body = (await request.json()) as Record<string, any>
        return HttpResponse.json({
            data: { id: Number(params.id), ...body, updated_at: new Date().toISOString() },
        })
    }),
    http.delete(`${API}/customers/:id`, () => HttpResponse.json(null, { status: 204 })),
]
