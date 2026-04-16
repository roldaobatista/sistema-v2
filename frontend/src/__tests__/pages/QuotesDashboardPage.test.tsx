import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'

import { render, screen } from '@/__tests__/test-utils'
import QuotesDashboardPage from '@/pages/orcamentos/QuotesDashboardPage'

// Mock @dnd-kit to avoid heavy DnD rendering (tests don't exercise drag-and-drop)
vi.mock('@dnd-kit/core', () => ({
    DndContext: ({ children }: Record<string, unknown>) => <>{children}</>,
    DragOverlay: ({ children }: Record<string, unknown>) => <>{children}</>,
    PointerSensor: class {},
    TouchSensor: class {},
    useSensor: () => ({}),
    useSensors: () => [],
    useDroppable: () => ({ setNodeRef: () => {} }),
    useDraggable: () => ({ attributes: {}, listeners: {}, setNodeRef: () => {}, transform: null, isDragging: false }),
    closestCorners: () => null,
}))

vi.mock('sonner', () => ({
    toast: {
        success: vi.fn(),
        error: vi.fn(),
        info: vi.fn(),
    },
}))

const { mockApiGet } = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        hasPermission: (permission: string) => permission === 'quotes.quote.view',
    }),
}))

vi.mock('@/lib/api', () => ({
    default: {
        get: mockApiGet,
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
        defaults: { baseURL: 'http://127.0.0.1:8000/api/v1' },
    },
    getApiErrorMessage: (err: unknown, fallback: string) => {
        const e = err as { response?: { data?: { message?: string } } }
        return e?.response?.data?.message ?? fallback
    },
    unwrapData: <T,>(r: { data?: { data?: T } | T }): T => {
        const d = r?.data
        if (d != null && typeof d === 'object' && 'data' in d) {
            return (d as { data: T }).data
        }
        return d as T
    },
}))

describe('QuotesDashboardPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()

        mockApiGet.mockImplementation((url: string) => {
            if (url === '/quotes-summary') {
                return Promise.resolve({
                    data: {
                        data: {
                            draft: 0,
                            pending_internal_approval: 0,
                            internally_approved: 0,
                            sent: 0,
                            approved: 0,
                            rejected: 0,
                            expired: 0,
                            in_execution: 0,
                            installation_testing: 1,
                            renegotiation: 0,
                            invoiced: 0,
                            total_month: 0,
                            conversion_rate: 0,
                        },
                    },
                })
            }

            if (url === '/quotes-advanced-summary') {
                return Promise.resolve({
                    data: {
                        data: {
                            total_quotes: 1,
                            total_approved: 0,
                            conversion_rate: 0,
                            avg_ticket: 0,
                            avg_conversion_days: 0,
                            top_sellers: [],
                            monthly_trend: [],
                        },
                    },
                })
            }

            if (url === '/quotes') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 77,
                                quote_number: 'ORC-00077',
                                status: 'installation_testing',
                                total: 1250,
                                revision: 1,
                                valid_until: '2026-03-20',
                                customer: { id: 9, name: 'Cliente Teste' },
                                seller: { id: 3, name: 'Vendedor Teste' },
                                tags: [],
                            },
                        ],
                        current_page: 1,
                        last_page: 1,
                        per_page: 300,
                        total: 1,
                    },
                })
            }

            return Promise.resolve({ data: { data: [] } })
        })
    })

    it('exibe orcamentos em instalacao para teste no kanban', async () => {
        const user = userEvent.setup({ delay: null })

        render(<QuotesDashboardPage />)

        await user.click(await screen.findByRole('button', { name: /Kanban/i }))

        expect(await screen.findByText(/Instala..o p\/ Teste/i)).toBeInTheDocument()
        expect(await screen.findByText('ORC-00077')).toBeInTheDocument()
        expect(await screen.findByText('Cliente Teste')).toBeInTheDocument()
    })
})
