import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@/__tests__/test-utils'
import { WorkOrderDashboardPage } from '@/pages/os/WorkOrderDashboardPage'

const {
    mockApiGet,
    mockHasPermission,
    mockNavigate,
} = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
    mockHasPermission: vi.fn(),
    mockNavigate: vi.fn(),
}))

vi.mock('@/lib/api', () => ({
    default: {
        get: mockApiGet,
    },
    unwrapData: <T,>(res: { data?: { data?: T } | T } | undefined) => {
        if (!res) return undefined
        const d = res?.data
        if (d && typeof d === 'object' && 'data' in d) return (d as { data: T }).data
        return d as T
    },
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        hasPermission: mockHasPermission,
    }),
}))

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
    return {
        ...actual,
        useNavigate: () => mockNavigate,
    }
})

vi.mock('sonner', () => ({
    toast: {
        success: vi.fn(),
        error: vi.fn(),
        warning: vi.fn(),
    },
}))

vi.mock('@/lib/status-config', () => ({
    workOrderStatus: {
        open: { label: 'Aberta', variant: 'default' },
        awaiting_dispatch: { label: 'Aguardando Despacho', variant: 'warning' },
        in_displacement: { label: 'Em Deslocamento', variant: 'info' },
        in_service: { label: 'Em Atendimento', variant: 'warning' },
        completed: { label: 'Concluída', variant: 'success' },
        delivered: { label: 'Entregue', variant: 'success' },
        invoiced: { label: 'Faturada', variant: 'brand' },
        cancelled: { label: 'Cancelada', variant: 'destructive' },
    },
}))

vi.mock('@/lib/safe-array', () => ({
    safeArray: <T,>(val: T[] | undefined | null) => val ?? [],
}))

const mockDashboardStats = {
    total_orders: 42,
    month_revenue: 15000,
    avg_completion_hours: 4.5,
    sla_compliance: 92,
    by_status: {
        open: 5,
        in_service: 10,
        completed: 20,
        cancelled: 2,
    },
    top_customers: [
        { name: 'Cliente A', total_os: 10, revenue: 5000 },
    ],
}

describe('WorkOrderDashboardPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('renderiza sem erros', async () => {
        mockHasPermission.mockReturnValue(true)
        mockApiGet.mockImplementation((url: string) => {
            if (url.includes('dashboard-stats')) return Promise.resolve({ data: mockDashboardStats })
            return Promise.resolve({ data: { data: [] } })
        })

        render(<WorkOrderDashboardPage />)

        expect(await screen.findByText(/Dashboard de Ordens de Servico/i)).toBeInTheDocument()
    })

    it('mostra permissao negada sem permissao de visualizacao', () => {
        mockHasPermission.mockReturnValue(false)

        render(<WorkOrderDashboardPage />)

        expect(screen.getByText(/nao possui permissao para visualizar o dashboard/i)).toBeInTheDocument()
    })

    it('renderiza metricas e cards do dashboard', async () => {
        mockHasPermission.mockReturnValue(true)
        mockApiGet.mockImplementation((url: string) => {
            if (url.includes('dashboard-stats')) return Promise.resolve({ data: mockDashboardStats })
            return Promise.resolve({ data: { data: [] } })
        })

        render(<WorkOrderDashboardPage />)

        // Titulo do dashboard
        expect(await screen.findByText(/Dashboard de Ordens de Servico/i)).toBeInTheDocument()

        // KPI card: Total de OS
        expect(await screen.findByText('Total de OS')).toBeInTheDocument()
        expect(screen.getByText('42')).toBeInTheDocument()
    })
})
