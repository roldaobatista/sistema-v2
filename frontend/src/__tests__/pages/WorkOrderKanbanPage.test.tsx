import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@/__tests__/test-utils'
import { WorkOrderKanbanPage } from '@/pages/os/WorkOrderKanbanPage'

const {
    mockHasPermission,
    mockNavigate,
    mockWorkOrderList,
    mockWorkOrderUpdateStatus,
    mockQueryClient,
} = vi.hoisted(() => ({
    mockHasPermission: vi.fn(),
    mockNavigate: vi.fn(),
    mockWorkOrderList: vi.fn(),
    mockWorkOrderUpdateStatus: vi.fn(),
    mockQueryClient: {
        invalidateQueries: vi.fn(),
    },
}))

vi.mock('@/lib/api', () => ({
    default: {
        get: vi.fn(),
    },
}))

vi.mock('@/lib/work-order-api', () => ({
    workOrderApi: {
        list: mockWorkOrderList,
        updateStatus: mockWorkOrderUpdateStatus,
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

vi.mock('@tanstack/react-query', async () => {
    const actual = await vi.importActual<typeof import('@tanstack/react-query')>('@tanstack/react-query')
    return {
        ...actual,
        useQueryClient: () => mockQueryClient,
    }
})

vi.mock('@/lib/cross-tab-sync', () => ({
    useCrossTabSync: vi.fn(),
    broadcastChange: vi.fn(),
}))

vi.mock('@/lib/query-keys', () => ({
    queryKeys: {
        workOrders: {
            all: ['work-orders'],
            kanban: (search: string) => ['work-orders', 'kanban', search],
        },
    },
}))

describe('WorkOrderKanbanPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockWorkOrderList.mockResolvedValue({ data: { data: [] } })
    })

    it('renderiza sem erros quando usuario tem permissao', async () => {
        mockHasPermission.mockReturnValue(true)

        render(<WorkOrderKanbanPage />)

        expect(screen.getByText(/kanban de os/i)).toBeInTheDocument()
    })

    it('mostra mensagem de permissao negada quando usuario nao tem permissao', () => {
        mockHasPermission.mockReturnValue(false)

        render(<WorkOrderKanbanPage />)

        expect(
            screen.getByText(/nao possui permissao para visualizar o kanban/i)
        ).toBeInTheDocument()
    })

    it('renderiza campo de busca e filtros de prioridade, tecnico e datas', () => {
        mockHasPermission.mockReturnValue(true)

        render(<WorkOrderKanbanPage />)

        // Campo de busca
        expect(screen.getByPlaceholderText(/buscar/i)).toBeInTheDocument()

        // Select de prioridade
        expect(screen.getByText(/todas prioridades/i)).toBeInTheDocument()
        expect(screen.getByRole('option', { name: /baixa/i })).toBeInTheDocument()
        expect(screen.getByRole('option', { name: /normal/i })).toBeInTheDocument()
        expect(screen.getByRole('option', { name: /alta/i })).toBeInTheDocument()
        expect(screen.getByRole('option', { name: /urgente/i })).toBeInTheDocument()

        // Select de tecnico
        expect(screen.getByText(/todos t.cnicos/i)).toBeInTheDocument()

        // Inputs de data
        expect(screen.getByTitle(/data in.cio/i)).toBeInTheDocument()
        expect(screen.getByTitle(/data fim/i)).toBeInTheDocument()
    })

    it('renderiza colunas do kanban para os status de ordem de servico', () => {
        mockHasPermission.mockReturnValue(true)

        render(<WorkOrderKanbanPage />)

        // Verifica algumas colunas-chave do kanban baseadas no statusConfig
        expect(screen.getByText('Aberta')).toBeInTheDocument()
        expect(screen.getByText('Aguard. Despacho')).toBeInTheDocument()
        expect(screen.getByText('Em Deslocamento')).toBeInTheDocument()
        expect(screen.getByText('Em Servico')).toBeInTheDocument()
        expect(screen.getByText('Finalizada')).toBeInTheDocument()
        expect(screen.getByText('Entregue')).toBeInTheDocument()
        expect(screen.getByText('Cancelada')).toBeInTheDocument()
    })

    it('exibe aviso de modo somente leitura quando usuario nao pode alterar status', () => {
        mockHasPermission.mockImplementation((perm: string) => {
            if (perm === 'os.work_order.view') return true
            if (perm === 'os.work_order.change_status') return false
            return false
        })

        render(<WorkOrderKanbanPage />)

        expect(
            screen.getByText(/modo somente leitura/i)
        ).toBeInTheDocument()
    })
})
