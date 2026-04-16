import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen } from '@/__tests__/test-utils'
import { WorkOrderCreatePage } from '@/pages/os/WorkOrderCreatePage'

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
}))

vi.mock('@/lib/work-order-api', () => ({
    workOrderApi: {
        create: vi.fn(),
    },
}))

vi.mock('@/lib/customer-api', () => ({
    customerApi: {
        detail: vi.fn(),
    },
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        hasPermission: mockHasPermission,
    }),
}))

vi.mock('@/hooks/usePriceGate', () => ({
    usePriceGate: () => ({
        canViewPrices: false,
    }),
}))

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
    return {
        ...actual,
        useNavigate: () => mockNavigate,
        useSearchParams: () => [new URLSearchParams(), vi.fn()],
    }
})

vi.mock('sonner', () => ({
    toast: {
        success: vi.fn(),
        error: vi.fn(),
        warning: vi.fn(),
    },
}))

describe('WorkOrderCreatePage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockApiGet.mockResolvedValue({ data: { data: [] } })
    })

    it('bloqueia acesso sem permissao de criacao', async () => {
        mockHasPermission.mockReturnValue(false)

        render(<WorkOrderCreatePage />)

        expect(screen.getByText(/nao possui permissao para criar ordem de servico/i)).toBeInTheDocument()
        expect(screen.queryByRole('button', { name: /abrir os/i })).not.toBeInTheDocument()
    })

    it('exibe status retroativos suportados pelo backend', async () => {
        mockHasPermission.mockReturnValue(true)

        render(<WorkOrderCreatePage />)

        expect(await screen.findByText(/nova ordem de servi/i)).toBeInTheDocument()
        expect(screen.getByRole('option', { name: /aguardando despacho/i })).toBeInTheDocument()
        expect(screen.getByRole('option', { name: /em deslocamento/i })).toBeInTheDocument()
        expect(screen.getByRole('option', { name: /em atendimento/i })).toBeInTheDocument()
    })

    it('usa campos com data e hora no fluxo retroativo e expone entrega retroativa quando aplicavel', async () => {
        mockHasPermission.mockReturnValue(true)
        const user = userEvent.setup()

        render(<WorkOrderCreatePage />)

        const initialStatus = await screen.findByTitle('Status Inicial')
        await user.selectOptions(initialStatus, 'delivered')

        expect(screen.getByLabelText(/data e hora de recebimento/i)).toHaveAttribute('type', 'datetime-local')
        expect(screen.getByLabelText(/data e hora de inicio/i)).toHaveAttribute('type', 'datetime-local')
        expect(screen.getByLabelText(/data e hora de entrega/i)).toHaveAttribute('type', 'datetime-local')
    })
})
