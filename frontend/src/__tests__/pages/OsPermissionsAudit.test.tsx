import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@/__tests__/test-utils'
import { ServiceChecklistsPage } from '@/pages/os/ServiceChecklistsPage'
import { SlaPoliciesPage } from '@/pages/os/SlaPoliciesPage'
import { SlaDashboardPage } from '@/pages/os/SlaDashboardPage'
import { RecurringContractsPage } from '@/pages/os/RecurringContractsPage'
import { WorkOrderDashboardPage } from '@/pages/os/WorkOrderDashboardPage'
import { WorkOrdersListPage } from '@/pages/os/WorkOrdersListPage'
import { WorkOrderKanbanPage } from '@/pages/os/WorkOrderKanbanPage'
import OSCalendarPage from '@/pages/os/OSCalendarPage'

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

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
    return {
        ...actual,
        useNavigate: () => vi.fn(),
    }
})

vi.mock('sonner', () => ({
    toast: {
        success: vi.fn(),
        error: vi.fn(),
    },
}))

describe('Auditoria de permissoes nas telas OS adjacentes', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockHasPermission.mockReturnValue(false)
        mockApiGet.mockResolvedValue({ data: { data: [] } })
    })

    it('bloqueia visualizacao de checklists sem permissao', async () => {
        render(<ServiceChecklistsPage />)

        expect(screen.getByText(/nao possui permissao para visualizar checklists/i)).toBeInTheDocument()
        expect(screen.queryByText(/novo checklist/i)).not.toBeInTheDocument()
    })

    it('bloqueia visualizacao de politicas SLA sem permissao', async () => {
        render(<SlaPoliciesPage />)

        expect(screen.getByText(/nao possui permissao para visualizar politicas de sla/i)).toBeInTheDocument()
        expect(screen.queryByText(/nova politica/i)).not.toBeInTheDocument()
    })

    it('bloqueia visualizacao do dashboard SLA sem permissao', async () => {
        render(<SlaDashboardPage />)

        expect(screen.getByText(/nao possui permissao para visualizar o dashboard de sla/i)).toBeInTheDocument()
        expect(screen.queryByText(/compliance por politica/i)).not.toBeInTheDocument()
    })

    it('bloqueia visualizacao de contratos recorrentes sem permissao', async () => {
        render(<RecurringContractsPage />)

        expect(screen.getByText(/nao possui permissao para visualizar contratos recorrentes/i)).toBeInTheDocument()
        expect(screen.queryByText(/novo contrato/i)).not.toBeInTheDocument()
    })

    it('bloqueia visualizacao do dashboard principal de OS sem permissao', async () => {
        render(<WorkOrderDashboardPage />)

        expect(screen.getByText(/nao possui permissao para visualizar o dashboard de ordens de servico/i)).toBeInTheDocument()
        expect(screen.queryByText(/top 5 clientes/i)).not.toBeInTheDocument()
    })

    it('bloqueia listagem de OS sem permissao de visualizacao', async () => {
        render(<WorkOrdersListPage />)

        expect(screen.getByText(/nao possui permissao para visualizar ordens de servico/i)).toBeInTheDocument()
        expect(screen.queryByText(/criar primeira os/i)).not.toBeInTheDocument()
    })

    it('bloqueia kanban de OS sem permissao de visualizacao', async () => {
        render(<WorkOrderKanbanPage />)

        expect(screen.getByText(/nao possui permissao para visualizar o kanban de ordens de servico/i)).toBeInTheDocument()
        expect(screen.queryByPlaceholderText(/buscar/i)).not.toBeInTheDocument()
    })

    it('bloqueia agenda de OS sem permissao de visualizacao', async () => {
        render(<OSCalendarPage />)

        expect(screen.getByText(/nao possui permissao para visualizar a agenda de ordens de servico/i)).toBeInTheDocument()
        expect(screen.queryByText(/hoje/i)).not.toBeInTheDocument()
    })
})
