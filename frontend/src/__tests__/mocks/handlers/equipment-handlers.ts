import { http, HttpResponse } from 'msw'
import { createPaginatedResponse } from '../factories'

const API = '/api/v1'

function createEquipment(overrides: Record<string, any> = {}) {
    return {
        id: 1,
        tenant_id: 1,
        name: 'Equipamento Teste',
        serial_number: 'EQ-001',
        customer_id: null,
        is_active: true,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        ...overrides,
    }
}

export const equipmentHandlers = [
    http.get(`${API}/equipments`, () =>
        HttpResponse.json(createPaginatedResponse([createEquipment()]))
    ),
    http.get(`${API}/equipments/:id`, ({ params }) =>
        HttpResponse.json({ data: createEquipment({ id: Number(params.id) }) })
    ),
    http.post(`${API}/equipments`, async ({ request }) => {
        const body = (await request.json()) as Record<string, any>
        return HttpResponse.json(
            { data: { id: 1, ...body, created_at: new Date().toISOString(), updated_at: new Date().toISOString() } },
            { status: 201 }
        )
    }),
    http.put(`${API}/equipments/:id`, async ({ request, params }) => {
        const body = (await request.json()) as Record<string, any>
        return HttpResponse.json({
            data: { id: Number(params.id), ...body, updated_at: new Date().toISOString() },
        })
    }),
    http.delete(`${API}/equipments/:id`, () => HttpResponse.json(null, { status: 204 })),
]
