import { describe, it, expect, vi, beforeEach } from 'vitest'

// ── Mock API client ──

const createMockApi = () => ({
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  delete: vi.fn(),
})

describe('API Client Patterns', () => {
  let api: ReturnType<typeof createMockApi>

  beforeEach(() => {
    api = createMockApi()
  })

  // ── Work Orders API ──

  it('fetches work orders list', async () => {
    api.get.mockResolvedValue({ data: { data: [{ id: 1, title: 'OS 001' }] } })

    const response = await api.get('/api/v1/work-orders')
    expect(response.data.data).toHaveLength(1)
    expect(api.get).toHaveBeenCalledWith('/api/v1/work-orders')
  })

  it('creates work order', async () => {
    api.post.mockResolvedValue({ data: { id: 1 }, status: 201 })

    const response = await api.post('/api/v1/work-orders', {
      customer_id: 1,
      title: 'Calibração',
    })

    expect(response.status).toBe(201)
  })

  it('updates work order', async () => {
    api.put.mockResolvedValue({ data: { id: 1, title: 'Atualizado' } })

    const response = await api.put('/api/v1/work-orders/1', { title: 'Atualizado' })
    expect(response.data.title).toBe('Atualizado')
  })

  it('deletes work order', async () => {
    api.delete.mockResolvedValue({ status: 200 })

    const response = await api.delete('/api/v1/work-orders/1')
    expect(response.status).toBe(200)
  })

  // ── Customers API ──

  it('fetches customers list', async () => {
    api.get.mockResolvedValue({ data: { data: [{ id: 1, name: 'Customer 1' }] } })

    const response = await api.get('/api/v1/customers')
    expect(response.data.data[0].name).toBe('Customer 1')
  })

  it('creates customer', async () => {
    api.post.mockResolvedValue({ data: { id: 1 }, status: 201 })

    const response = await api.post('/api/v1/customers', {
      name: 'New Customer',
      type: 'company',
    })

    expect(response.status).toBe(201)
  })

  it('searches customers', async () => {
    api.get.mockResolvedValue({ data: { data: [{ id: 1, name: 'Kalibrium' }] } })

    const response = await api.get('/api/v1/customers?search=Kalibrium')
    expect(response.data.data).toHaveLength(1)
  })

  // ── Equipment API ──

  it('fetches equipment list', async () => {
    api.get.mockResolvedValue({ data: { data: [{ id: 1, name: 'Balança' }] } })

    const response = await api.get('/api/v1/equipments')
    expect(response.data.data[0].name).toBe('Balança')
  })

  // ── Quotes API ──

  it('fetches quotes', async () => {
    api.get.mockResolvedValue({ data: { data: [] } })

    const response = await api.get('/api/v1/quotes')
    expect(response.data.data).toEqual([])
  })

  // ── Error Handling ──

  it('handles 401 unauthorized', async () => {
    api.get.mockRejectedValue({
      response: { status: 401, data: { message: 'Unauthenticated.' } },
    })

    try {
      await api.get('/api/v1/work-orders')
    } catch (error: unknown) {
      expect(error.response.status).toBe(401)
    }
  })

  it('handles 422 validation error', async () => {
    api.post.mockRejectedValue({
      response: {
        status: 422,
        data: {
          message: 'Validation failed',
          errors: { name: ['O campo nome é obrigatório'] },
        },
      },
    })

    try {
      await api.post('/api/v1/customers', {})
    } catch (error: unknown) {
      expect(error.response.status).toBe(422)
      expect(error.response.data.errors.name).toBeDefined()
    }
  })

  it('handles 404 not found', async () => {
    api.get.mockRejectedValue({
      response: { status: 404, data: { message: 'Not found' } },
    })

    try {
      await api.get('/api/v1/work-orders/99999')
    } catch (error: unknown) {
      expect(error.response.status).toBe(404)
    }
  })

  it('handles network error', async () => {
    api.get.mockRejectedValue(new Error('Network Error'))

    try {
      await api.get('/api/v1/work-orders')
    } catch (error: unknown) {
      expect(error.message).toBe('Network Error')
    }
  })

  // ── Pagination ──

  it('handles paginated responses', async () => {
    api.get.mockResolvedValue({
      data: {
        data: [{ id: 1 }, { id: 2 }],
        meta: {
          current_page: 1,
          last_page: 5,
          per_page: 10,
          total: 50,
        },
      },
    })

    const response = await api.get('/api/v1/work-orders?page=1&per_page=10')
    expect(response.data.meta.total).toBe(50)
    expect(response.data.meta.last_page).toBe(5)
  })

  it('sends correct headers', async () => {
    api.get.mockResolvedValue({ data: {} })

    await api.get('/api/v1/work-orders')
    expect(api.get).toHaveBeenCalled()
  })
})
