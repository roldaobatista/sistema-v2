import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import TechDashboardPage from '@/pages/tech/TechDashboardPage'

const {
    mockNavigate,
    mockApiGet,
    mockUser,
} = vi.hoisted(() => ({
    mockNavigate: vi.fn(),
    mockApiGet: vi.fn(),
    mockUser: {
        id: 7,
        name: 'Tecnico Teste',
    },
}))

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
    return { ...actual, useNavigate: () => mockNavigate }
})

vi.mock('@/lib/api', () => ({
    default: { get: mockApiGet },
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({ user: mockUser }),
}))

vi.mock('@/lib/utils', () => ({
    cn: (...args: unknown[]) => args.filter(Boolean).join(' '),
    getApiErrorMessage: (_: unknown, fb: string) => fb,
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/components/tech/TechSkeleton', () => ({
    DashboardSkeleton: () => <div data-testid="dashboard-skeleton">Loading...</div>,
}))

const mockProductivity = {
    completed_this_month: 25,
    average_time_hours: 2.5,
    nps_score: 9.2,
    pending_count: 8,
    in_progress_count: 3,
    completion_rate: 85,
    hours_worked_month: 160,
    streak_days: 15,
    weekly_completed: [
        { week: 'Semana 1', count: 5 },
        { week: 'Semana 2', count: 8 },
        { week: 'Semana 3', count: 6 },
        { week: 'Semana 4', count: 6 },
    ],
}

const mockRanking = {
    data: [
        { id: 3, position: 1 },
        { id: 7, position: 2 },
        { id: 9, position: 3 },
    ],
}

describe('TechDashboardPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('shows loading skeleton initially', () => {
        mockApiGet.mockReturnValue(new Promise(() => {}))
        render(<TechDashboardPage />)
        expect(screen.getByTestId('dashboard-skeleton')).toBeInTheDocument()
    })

    it('renders dashboard title', async () => {
        mockApiGet
            .mockResolvedValueOnce({ data: mockProductivity })
            .mockResolvedValueOnce(mockRanking)
        render(<TechDashboardPage />)
        await waitFor(() => {
            expect(screen.getByText('Dashboard')).toBeInTheDocument()
        })
    })

    it('displays KPI cards', async () => {
        mockApiGet
            .mockResolvedValueOnce({ data: mockProductivity })
            .mockResolvedValueOnce(mockRanking)
        render(<TechDashboardPage />)
        await waitFor(() => {
            expect(screen.getByText('OS Concluídas')).toBeInTheDocument()
            expect(screen.getByText('Tempo Médio')).toBeInTheDocument()
            expect(screen.getByText('NPS Pessoal')).toBeInTheDocument()
        })
    })

    it('displays productivity values', async () => {
        mockApiGet
            .mockResolvedValueOnce({ data: mockProductivity })
            .mockResolvedValueOnce(mockRanking)
        render(<TechDashboardPage />)
        expect(await screen.findByText('OS Concluídas')).toBeInTheDocument()
        expect(screen.getByText('25', { selector: 'p' })).toBeInTheDocument()
        expect(screen.getAllByText('2.5h', { selector: 'p' }).length).toBeGreaterThan(0)
    })

    it('shows ranking card', async () => {
        mockApiGet
            .mockResolvedValueOnce({ data: mockProductivity })
            .mockResolvedValueOnce(mockRanking)
        render(<TechDashboardPage />)
        await waitFor(() => {
            expect(screen.getByText('Ranking de Técnicos')).toBeInTheDocument()
            expect(screen.getByText('2º de 3 técnicos')).toBeInTheDocument()
        })
    })

    it('shows weekly chart', async () => {
        mockApiGet
            .mockResolvedValueOnce({ data: mockProductivity })
            .mockResolvedValueOnce(mockRanking)
        render(<TechDashboardPage />)
        await waitFor(() => {
            expect(screen.getByText('OS Concluídas por Semana')).toBeInTheDocument()
        })
    })

    it('shows quick action shortcuts', async () => {
        mockApiGet
            .mockResolvedValueOnce({ data: mockProductivity })
            .mockResolvedValueOnce(mockRanking)
        render(<TechDashboardPage />)
        await waitFor(() => {
            expect(screen.getByText('Atalhos Rápidos')).toBeInTheDocument()
            expect(screen.getByText('Minhas Comissões')).toBeInTheDocument()
            expect(screen.getByText('Metas')).toBeInTheDocument()
        })
    })

    it('shows streak days', async () => {
        mockApiGet
            .mockResolvedValueOnce({ data: mockProductivity })
            .mockResolvedValueOnce(mockRanking)
        render(<TechDashboardPage />)
        await waitFor(() => {
            expect(screen.getByText('15 dias')).toBeInTheDocument()
            expect(screen.getByText(/Sequência sem SLA/i)).toBeInTheDocument()
        })
    })
})
