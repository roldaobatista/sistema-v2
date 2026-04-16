import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@/__tests__/test-utils'
import { ContractsPage } from '@/pages/contratos/ContractsPage'

const { mockApiGet } = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
}))

vi.mock('@/lib/api', () => ({
    default: {
        get: mockApiGet,
    },
    unwrapData: (response: { data?: { data?: unknown } }) => response?.data?.data ?? response?.data ?? [],
}))

describe('ContractsPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockApiGet.mockResolvedValue({
            data: {
                data: [
                    {
                        id: 1,
                        name: 'Contrato Premium',
                        status: 'active',
                        value: '1500.50',
                        frequency: 'monthly',
                        customer: { name: 'Cliente A' },
                    },
                    {
                        id: 2,
                        status: 'expiring_soon',
                        value: 200,
                        frequency: null,
                        customer: null,
                    },
                ],
            },
        })
    })

    it('renderiza resumo e lista de contratos com payload envelopado', async () => {
        render(<ContractsPage />)

        expect(await screen.findByText('Contrato Premium')).toBeInTheDocument()
        expect(screen.getByText('Cliente A')).toBeInTheDocument()
        expect(screen.getByText('Contrato #2')).toBeInTheDocument()
        expect(screen.getByText('Ativo')).toBeInTheDocument()
        expect(screen.getByText('expiring_soon')).toBeInTheDocument()
        expect(screen.getByText('2')).toBeInTheDocument()
        expect(screen.getAllByText('1')).toHaveLength(2)
        expect(screen.getByText('R$ 1.700,50')).toBeInTheDocument()
    })
})
