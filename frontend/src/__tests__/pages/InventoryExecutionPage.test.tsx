import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import InventoryExecutionPage from '@/pages/estoque/InventoryExecutionPage'

const {
    mockNavigate,
    mockHasPermission,
    mockDetail,
    mockUpdateItem,
    mockComplete,
    toastSuccess,
    toastError,
} = vi.hoisted(() => ({
    mockNavigate: vi.fn(),
    mockHasPermission: vi.fn(),
    mockDetail: vi.fn(),
    mockUpdateItem: vi.fn(),
    mockComplete: vi.fn(),
    toastSuccess: vi.fn(),
    toastError: vi.fn(),
}))

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
    return {
        ...actual,
        useNavigate: () => mockNavigate,
        useParams: () => ({ id: '42' }),
    }
})

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        hasPermission: mockHasPermission,
    }),
}))

vi.mock('@/lib/stock-api', () => ({
    stockApi: {
        inventories: {
            detail: mockDetail,
            updateItem: mockUpdateItem,
            complete: mockComplete,
        },
    },
}))

vi.mock('sonner', () => ({
    toast: {
        success: toastSuccess,
        error: toastError,
    },
}))

vi.mock('@/components/qr/QrScannerModal', () => ({
    QrScannerModal: () => null,
}))

describe('InventoryExecutionPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockHasPermission.mockReturnValue(true)
        mockUpdateItem.mockResolvedValue({ data: { data: { id: 91, counted_quantity: 4 } } })
        mockComplete.mockResolvedValue({ data: { message: 'ok' } })
        mockDetail.mockResolvedValue({
            data: {
                id: 42,
                reference: 'INV-042',
                status: 'open',
                warehouse: { id: 3, name: 'Deposito Central' },
                items: [
                    {
                        id: 91,
                        product_id: 10,
                        counted_quantity: null,
                        product: {
                            id: 10,
                            name: 'Parafuso M8',
                            code: 'PRD-001',
                        },
                        batch: {
                            id: 5,
                            number: 'L-2026-01',
                        },
                    },
                ],
            },
        })
    })

    it('usa o codigo real do produto para exibir e filtrar itens do inventario', async () => {
        const user = userEvent.setup({ delay: null })

        render(<InventoryExecutionPage />)

        expect(await screen.findByText('Parafuso M8')).toBeInTheDocument()
        expect(screen.getByText('SKU: PRD-001')).toBeInTheDocument()
        expect(screen.getByText('LT: L-2026-01')).toBeInTheDocument()

        const searchInput = screen.getByPlaceholderText('Buscar produto por nome ou SKU...')
        await user.type(searchInput, 'PRD-001')

        await waitFor(() => {
            expect(screen.getByText('Parafuso M8')).toBeInTheDocument()
        })
    })

    it('mantem zero visivel quando a contagem do item ja foi salva', async () => {
        mockDetail.mockResolvedValueOnce({
            data: {
                id: 42,
                reference: 'INV-042',
                status: 'open',
                warehouse: { id: 3, name: 'Deposito Central' },
                items: [
                    {
                        id: 92,
                        product_id: 11,
                        counted_quantity: 0,
                        product: {
                            id: 11,
                            name: 'Arruela M8',
                            code: 'PRD-002',
                        },
                    },
                ],
            },
        })

        render(<InventoryExecutionPage />)

        const input = await screen.findByDisplayValue('0')
        expect(input).toBeInTheDocument()
    })
})
