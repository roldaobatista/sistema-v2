/**
 * Factories para dados de teste (MSW e testes de integração).
 * Gera entidades com IDs incrementais e valores realistas.
 */

let nextId = 1

export function createSupplier(overrides: Record<string, any> = {}): Record<string, any> {
    const id = nextId++
    return {
        id,
        tenant_id: 1,
        type: 'PJ',
        name: `Fornecedor ${id}`,
        document: null,
        email: `supplier${id}@test.com`,
        phone: null,
        is_active: true,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        ...overrides,
    }
}

/**
 * Resposta paginada no formato da API Laravel.
 */
export function createPaginatedResponse<T>(data: T[], total?: number): {
    data: T[]
    current_page: number
    per_page: number
    total: number
    last_page: number
    from: number | null
    to: number | null
} {
    const totalCount = total ?? data.length
    return {
        data,
        current_page: 1,
        per_page: 15,
        total: totalCount,
        last_page: Math.ceil(totalCount / 15) || 1,
        from: data.length > 0 ? 1 : null,
        to: data.length > 0 ? data.length : null,
    }
}
