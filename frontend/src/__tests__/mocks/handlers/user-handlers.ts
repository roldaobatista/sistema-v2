import { http, HttpResponse } from 'msw'
import { createPaginatedResponse } from '../factories'

const API = '/api/v1'

function createUser(overrides: Record<string, any> = {}) {
    return {
        id: 1,
        name: 'User Test',
        email: 'user@test.com',
        tenant_id: 1,
        current_tenant_id: 1,
        is_active: true,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        ...overrides,
    }
}

export const userHandlers = [
    http.get(`${API}/users`, () =>
        HttpResponse.json(createPaginatedResponse([createUser()]))
    ),
    http.get(`${API}/users/:id`, ({ params }) =>
        HttpResponse.json({ data: createUser({ id: Number(params.id) }) })
    ),
    http.get(`${API}/roles`, () =>
        HttpResponse.json({ data: [{ id: 1, name: 'admin', guard_name: 'web' }] })
    ),
    http.get(`${API}/permissions`, () =>
        HttpResponse.json({ data: [] })
    ),
]
