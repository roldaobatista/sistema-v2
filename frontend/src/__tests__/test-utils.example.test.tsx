/**
 * Exemplo de uso do render wrapper e factories.
 * Não altera testes existentes — serve de referência para testes novos.
 */
import { describe, it, expect } from 'vitest'
import { render, screen, createTestQueryClient } from '@/__tests__/test-utils'
import { createSupplier, createPaginatedResponse } from '@/__tests__/mocks/factories'

describe('test-utils e factories', () => {
    it('createTestQueryClient retorna QueryClient com retry false', () => {
        const client = createTestQueryClient()
        expect(client.getDefaultOptions().queries?.retry).toBe(false)
    })

    it('createSupplier gera objeto com id e campos esperados', () => {
        const s1 = createSupplier({ name: 'Fornecedor A' })
        const s2 = createSupplier()
        expect(s1).toMatchObject({ name: 'Fornecedor A', tenant_id: 1, type: 'PJ' })
        expect(typeof (s1 as { id: number }).id).toBe('number')
        expect((s2 as { id: number }).id).not.toBe((s1 as { id: number }).id)
    })

    it('createPaginatedResponse retorna estrutura da API', () => {
        const items = [createSupplier(), createSupplier()]
        const res = createPaginatedResponse(items)
        expect(res.data).toHaveLength(2)
        expect(res.current_page).toBe(1)
        expect(res.per_page).toBe(15)
        expect(res.total).toBe(2)
        expect(res.last_page).toBe(1)
    })

    it('render com wrapper monta componente dentro de providers', () => {
        render(<div data-testid="wrapped">Conteúdo</div>)
        expect(screen.getByTestId('wrapped')).toHaveTextContent('Conteúdo')
    })
})
