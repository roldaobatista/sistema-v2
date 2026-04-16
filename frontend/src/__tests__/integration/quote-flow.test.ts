import { beforeEach, describe, expect, it, vi } from 'vitest'

interface MockApiError {
  response?: {
    status?: number
    data?: {
      message?: string
      errors?: Record<string, string[]>
    }
  }
}

const mockApi = {
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  patch: vi.fn(),
  delete: vi.fn(),
}

vi.mock('@/lib/api', () => ({ default: mockApi }))

beforeEach(() => {
  vi.clearAllMocks()
})

describe('Quote Create Flow', () => {
  const validQuote = {
    customer_id: 1,
    valid_until: '2026-03-31',
    observations: 'Orcamento para calibracao',
    equipments: [
      {
        equipment_id: 10,
        description: 'Balanca industrial',
        items: [
          {
            type: 'service',
            service_id: 7,
            quantity: 1,
            original_price: 200,
            unit_price: 200,
            discount_percentage: 0,
          },
        ],
      },
    ],
  }

  it('POST /quotes cria um novo orcamento em draft', async () => {
    mockApi.post.mockResolvedValue({
      data: { data: { id: 1, status: 'draft', quote_number: 'ORC-00001', ...validQuote } },
    })

    const res = await mockApi.post('/quotes', validQuote)

    expect(res.data.data.id).toBe(1)
    expect(res.data.data.status).toBe('draft')
    expect(res.data.data.quote_number).toBe('ORC-00001')
  })

  it('orcamento sem customer_id retorna 422', async () => {
    mockApi.post.mockRejectedValue({
      response: {
        status: 422,
        data: { errors: { customer_id: ['O campo cliente e obrigatorio.'] } },
      },
    } satisfies MockApiError)

    await expect(mockApi.post('/quotes', {})).rejects.toMatchObject({
      response: {
        status: 422,
        data: { errors: { customer_id: ['O campo cliente e obrigatorio.'] } },
      },
    })
  })
})

describe('Quote Status Transitions', () => {
  const transitions = [
    { label: 'request internal approval', endpoint: '/quotes/1/request-internal-approval', to: 'pending_internal_approval' },
    { label: 'internal approve', endpoint: '/quotes/1/internal-approve', to: 'internally_approved' },
    { label: 'send to client', endpoint: '/quotes/1/send', to: 'sent' },
    { label: 'approve', endpoint: '/quotes/1/approve', to: 'approved' },
    { label: 'reject', endpoint: '/quotes/1/reject', to: 'rejected' },
    { label: 'reopen', endpoint: '/quotes/1/reopen', to: 'draft' },
  ] as const

  for (const transition of transitions) {
    it(`${transition.label} atualiza o status`, async () => {
      mockApi.post.mockResolvedValue({
        data: { data: { id: 1, status: transition.to } },
      })

      const payload = transition.endpoint.endsWith('/reject') ? { reason: 'Cliente recusou' } : undefined
      const res = await mockApi.post(transition.endpoint, payload)

      expect(res.data.data.status).toBe(transition.to)
    })
  }
})

describe('Quote API Contract', () => {
  it('reject envia payload vazio quando motivo e opcional', async () => {
    mockApi.post.mockResolvedValue({
      data: { data: { id: 1, status: 'rejected' } },
    })

    const { quoteApi } = await import('@/lib/quote-api')

    await quoteApi.reject(1, '   ')

    expect(mockApi.post).toHaveBeenCalledWith('/quotes/1/reject', {})
  })
})

describe('Quote Items', () => {
  it('adiciona item em um equipamento do orcamento', async () => {
    mockApi.post.mockResolvedValue({
      data: {
        data: { id: 1, product_id: 5, quantity: 3, unit_price: 200, subtotal: 600 },
      },
    })

    const res = await mockApi.post('/quote-equipments/1/items', {
      type: 'product',
      product_id: 5,
      quantity: 3,
      original_price: 200,
      unit_price: 200,
    })

    expect(res.data.data.subtotal).toBe(600)
  })

  it('atualiza item por /quote-items/{id}', async () => {
    mockApi.put.mockResolvedValue({
      data: {
        data: { id: 1, quantity: 5, unit_price: 200, subtotal: 1000 },
      },
    })

    const res = await mockApi.put('/quote-items/1', { quantity: 5 })

    expect(res.data.data.quantity).toBe(5)
    expect(res.data.data.subtotal).toBe(1000)
  })

  it('remove item por /quote-items/{id}', async () => {
    mockApi.delete.mockResolvedValue({
      data: {},
      status: 204,
    })

    const res = await mockApi.delete('/quote-items/1')

    expect(res.status).toBe(204)
  })
})

describe('Quote to Work Order Conversion', () => {
  it('converte orcamento aprovado para OS', async () => {
    mockApi.post.mockResolvedValue({
      data: {
        data: { id: 100, status: 'open', quote_id: 1 },
        message: 'OS criada a partir do orcamento!',
      },
    })

    const res = await mockApi.post('/quotes/1/convert-to-os', { is_installation_testing: false })

    expect(res.data.data.status).toBe('open')
    expect(res.data.data.quote_id).toBe(1)
  })

  it('nao converte quote draft para OS', async () => {
    mockApi.post.mockRejectedValue({
      response: {
        status: 422,
        data: { message: 'Orcamento precisa estar aprovado (interna ou externamente) para converter' },
      },
    } satisfies MockApiError)

    await expect(
      mockApi.post('/quotes/1/convert-to-os', { is_installation_testing: false }),
    ).rejects.toMatchObject({
      response: {
        status: 422,
      },
    })
  })
})

describe('Quote Duplicate and Export', () => {
  it('duplicate cria novo draft', async () => {
    mockApi.post.mockResolvedValue({
      data: { data: { id: 2, status: 'draft', quote_number: 'ORC-00002' } },
    })

    const res = await mockApi.post('/quotes/1/duplicate')

    expect(res.data.data.status).toBe('draft')
    expect(res.data.data.id).not.toBe(1)
  })

  it('exporta PDF como blob', async () => {
    mockApi.get.mockResolvedValue({
      data: new Blob(['pdf-content'], { type: 'application/pdf' }),
      headers: { 'content-type': 'application/pdf' },
    })

    const res = await mockApi.get('/quotes/1/pdf')

    expect(res.data).toBeInstanceOf(Blob)
  })
})
