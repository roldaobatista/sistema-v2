import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@/__tests__/test-utils'
import ExecutionTimer from '@/components/os/ExecutionTimer'

const { mockApiGet, mockApiPost, toastSuccess, toastError } = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
    mockApiPost: vi.fn(),
    toastSuccess: vi.fn(),
    toastError: vi.fn(),
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: (selector?: (state: { user: { id: number } }) => unknown) => selector ? selector({
        user: { id: 99 },
    }) : {
        user: { id: 99 },
    },
}))

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

describe('ExecutionTimer', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('ignora log aberto de outro usuario e permite iniciar timer proprio', async () => {
        mockApiGet.mockResolvedValue({
            data: {
                data: [
                    {
                        id: 10,
                        user_id: 7,
                        started_at: '2026-03-21T12:00:00Z',
                        ended_at: null,
                        duration_seconds: null,
                        activity_type: 'work',
                        user: { id: 7, name: 'Outro Técnico' },
                    },
                ],
            },
        })
        mockApiPost.mockResolvedValue({ data: { data: { id: 11 } } })

        render(<ExecutionTimer workOrderId={123} status="in_service" />)

        await waitFor(() => {
            expect(mockApiGet).toHaveBeenCalledWith('/work-order-time-logs', { params: { work_order_id: 123 } })
        })

        expect(screen.getByRole('button', { name: 'Iniciar' })).toBeInTheDocument()
        expect(screen.queryByRole('button', { name: 'Parar' })).not.toBeInTheDocument()

        fireEvent.click(screen.getByRole('button', { name: 'Iniciar' }))

        await waitFor(() => {
            expect(mockApiPost).toHaveBeenCalledWith('/work-order-time-logs/start', {
                work_order_id: 123,
                activity_type: 'work',
            })
        })
    })

    it('para apenas o log aberto do usuario autenticado quando ha multiplos logs abertos na OS', async () => {
        mockApiGet.mockResolvedValue({
            data: {
                data: [
                    {
                        id: 21,
                        user_id: 7,
                        started_at: '2026-03-21T11:50:00Z',
                        ended_at: null,
                        duration_seconds: null,
                        activity_type: 'travel',
                        user: { id: 7, name: 'Outro Técnico' },
                    },
                    {
                        id: 22,
                        user_id: 99,
                        started_at: '2026-03-21T11:55:00Z',
                        ended_at: null,
                        duration_seconds: null,
                        activity_type: 'work',
                        user: { id: 99, name: 'Usuário Atual' },
                    },
                ],
            },
        })
        mockApiPost.mockResolvedValue({ data: { data: { id: 22 } } })

        render(<ExecutionTimer workOrderId={456} status="in_service" />)

        await waitFor(() => {
            expect(screen.getByRole('button', { name: 'Parar' })).toBeInTheDocument()
        })

        fireEvent.click(screen.getByRole('button', { name: 'Parar' }))

        await waitFor(() => {
            expect(mockApiPost).toHaveBeenCalledWith('/work-order-time-logs/22/stop')
        })
    })
})
