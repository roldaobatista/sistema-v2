import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import TechDaySummaryPage from '@/pages/tech/TechDaySummaryPage'

const {
    mockNavigate,
    mockApiGet,
    mockApiPost,
    toastSuccess,
    toastError,
} = vi.hoisted(() => ({
    mockNavigate: vi.fn(),
    mockApiGet: vi.fn(),
    mockApiPost: vi.fn(),
    toastSuccess: vi.fn(),
    toastError: vi.fn(),
}))

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
    return {
        ...actual,
        useNavigate: () => mockNavigate,
    }
})

vi.mock('@/lib/api', async () => {
    const actual = await vi.importActual<typeof import('@/lib/api')>('@/lib/api')
    return {
        ...actual,
        default: {
            get: mockApiGet,
            post: mockApiPost,
        },
    }
})

vi.mock('sonner', () => ({
    toast: {
        success: toastSuccess,
        error: toastError,
    },
}))

describe('TechDaySummaryPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()

        mockApiGet.mockImplementation((url: string) => {
            if (url === '/me') {
                return Promise.resolve({ data: { data: { user: { id: 77 } } } })
            }

            if (url === '/work-orders') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 1,
                                os_number: 'OS-001',
                                status: 'completed',
                                customer: { name: 'Cliente Demo' },
                                completed_at: '2026-03-20T13:00:00Z',
                                created_at: '2026-03-20T08:00:00Z',
                            },
                        ],
                    },
                })
            }

            if (url === '/expenses') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 9,
                                amount: 35.5,
                                category: { name: 'Combustivel' },
                            },
                        ],
                    },
                })
            }

            if (url === '/time-entries') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 14,
                                started_at: '2026-03-20T08:00:00Z',
                                ended_at: '2026-03-20T10:00:00Z',
                                duration_minutes: 120,
                                distance_km: 18,
                                work_order: { os_number: 'OS-001' },
                            },
                        ],
                    },
                })
            }

            return Promise.resolve({ data: { data: [] } })
        })
    })

    it('renderiza resumo com respostas envelopadas da API sem quebrar', async () => {
        render(<TechDaySummaryPage />)

        expect(await screen.findByText('OS Atendidas')).toBeInTheDocument()

        await waitFor(() => {
            expect(screen.getByText('1')).toBeInTheDocument()
            expect(screen.getAllByText('2h 0min')).toHaveLength(2)
            expect(screen.getByText('18 km')).toBeInTheDocument()
            expect(screen.getByText('OS-001')).toBeInTheDocument()
            expect(screen.getByText('Combustivel')).toBeInTheDocument()
        })
    })
})
