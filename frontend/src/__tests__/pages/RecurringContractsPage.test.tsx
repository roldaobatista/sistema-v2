import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import { RecurringContractsPage } from '@/pages/os/RecurringContractsPage'

const {
    mockApiGet,
    mockHasPermission,
} = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
    mockHasPermission: vi.fn(),
}))

vi.mock('@/lib/api', () => ({
    default: {
        get: mockApiGet,
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        hasPermission: mockHasPermission,
    }),
}))

vi.mock('sonner', () => ({
    toast: {
        success: vi.fn(),
        error: vi.fn(),
    },
}))

describe('RecurringContractsPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockHasPermission.mockImplementation((permission: string) => permission === 'os.work_order.view')
        mockApiGet.mockImplementation((url: string) => {
            if (url === '/recurring-contracts') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 1,
                                name: 'Contrato Preventivo',
                                description: 'Visita mensal',
                                frequency: 'monthly',
                                start_date: '2026-03-01',
                                end_date: null,
                                next_run_date: '2026-04-01',
                                priority: 'normal',
                                is_active: true,
                                generated_count: 4,
                                customer: { id: 1, name: 'Cliente 1' },
                                equipment: null,
                                assignee: null,
                                items: [],
                            },
                        ],
                    },
                })
            }

            if (url === '/customers') {
                return Promise.resolve({
                    data: {
                        data: [],
                    },
                })
            }

            return Promise.resolve({ data: { data: [] } })
        })
    })

    it('oculta acoes de criar, editar, gerar e excluir sem permissao adequada', async () => {
        render(<RecurringContractsPage />)

        expect(await screen.findByText('Contrato Preventivo')).toBeInTheDocument()

        expect(screen.queryByRole('button', { name: /novo contrato/i })).not.toBeInTheDocument()

        await waitFor(() => {
            expect(screen.queryByTitle('Gerar OS agora')).not.toBeInTheDocument()
        })

        const buttons = screen.getAllByRole('button')
        expect(buttons.some((button) => button.querySelector('svg'))).toBe(true)
        expect(screen.queryByText('Geradas')).toBeInTheDocument()
        expect(screen.getByText('4')).toBeInTheDocument()
    })
})
