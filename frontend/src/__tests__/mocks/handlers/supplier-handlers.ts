import { http, HttpResponse } from 'msw'
import { createPaginatedResponse, createSupplier } from '../factories'

const API = '/api/v1'

export const supplierHandlers = [
    http.get(`${API}/suppliers`, () =>
        HttpResponse.json(createPaginatedResponse([createSupplier(), createSupplier()]))
    ),
    http.get(`${API}/suppliers/:id`, ({ params }) =>
        HttpResponse.json({
            data: createSupplier({ id: Number(params.id) }),
        })
    ),
    http.post(`${API}/suppliers`, async ({ request }) => {
        const body = (await request.json()) as Record<string, any>
        return HttpResponse.json(
            {
                data: {
                    id: 1,
                    ...body,
                    created_at: new Date().toISOString(),
                    updated_at: new Date().toISOString(),
                },
                message: 'Fornecedor criado com sucesso.',
            },
            { status: 201 }
        )
    }),
    http.put(`${API}/suppliers/:id`, async ({ request, params }) => {
        const body = (await request.json()) as Record<string, any>
        return HttpResponse.json({
            data: { id: Number(params.id), ...body, updated_at: new Date().toISOString() },
        })
    }),
    http.delete(`${API}/suppliers/:id`, () => HttpResponse.json(null, { status: 204 })),
]
