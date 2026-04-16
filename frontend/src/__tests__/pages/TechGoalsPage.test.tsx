import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import TechGoalsPage from '@/pages/tech/TechGoalsPage'

const {
    mockNavigate,
    mockApiGet,
} = vi.hoisted(() => ({
    mockNavigate: vi.fn(),
    mockApiGet: vi.fn(),
}))

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
    return { ...actual, useNavigate: () => mockNavigate }
})

vi.mock('@/lib/api', () => ({
    default: { get: mockApiGet },
}))

vi.mock('@/lib/utils', () => ({
    cn: (...args: unknown[]) => args.filter(Boolean).join(' '),
    getApiErrorMessage: (_: unknown, fb: string) => fb,
    formatCurrency: (value: number) => `R$ ${value.toFixed(2)}`,
}))

vi.mock('sonner', () => ({ toast: { error: vi.fn() } }))

describe('TechGoalsPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('busca metas do tecnico e campanhas ativas com o contrato esperado', async () => {
        mockApiGet
            .mockResolvedValueOnce({
                data: {
                    data: [
                        {
                            id: 11,
                            name: 'Meta de OS',
                            target_value: 20,
                            current_value: 8,
                            period: '2026-03',
                            status: 'active',
                        },
                    ],
                },
            })
            .mockResolvedValueOnce({
                data: {
                    data: [
                        {
                            id: 21,
                            name: 'Campanha Regional',
                            start_date: '2026-03-01',
                            end_date: '2026-03-31',
                            bonus_value: 300,
                            status: 'active',
                        },
                    ],
                },
            })

        render(<TechGoalsPage />)

        await waitFor(() => {
            expect(mockApiGet).toHaveBeenNthCalledWith(1, '/commission-goals', { params: { my: '1' } })
            expect(mockApiGet).toHaveBeenNthCalledWith(2, '/commission-campaigns', { params: { active: '1' } })
            expect(screen.getByText('Meta de OS')).toBeInTheDocument()
            expect(screen.getByText(/Período:\s*2026-03/i)).toBeInTheDocument()
        })
    })
})
