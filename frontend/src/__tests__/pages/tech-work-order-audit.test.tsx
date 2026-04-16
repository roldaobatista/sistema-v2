import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { fireEvent, render, screen, waitFor } from '@/__tests__/test-utils'
import TechWorkOrdersPage from '@/pages/tech/TechWorkOrdersPage'
import TechWorkOrderDetailPage from '@/pages/tech/TechWorkOrderDetailPage'
import TechChecklistPage from '@/pages/tech/TechChecklistPage'
import TechSignaturePage from '@/pages/tech/TechSignaturePage'
import BeforeAfterPhotos from '@/components/os/BeforeAfterPhotos'

const {
    mockNavigate,
    mockPutMany,
    mockGetById,
    mockApiGet,
    mockApiPost,
    mockToast,
    mockOfflinePost,
    mockPut,
    mockHasPermission,
    toastError,
    toastSuccess,
} = vi.hoisted(() => ({
    mockNavigate: vi.fn(),
    mockPutMany: vi.fn(),
    mockGetById: vi.fn(),
    mockApiGet: vi.fn(),
    mockApiPost: vi.fn(),
    mockToast: vi.fn(),
    mockOfflinePost: vi.fn(),
    mockPut: vi.fn(),
    mockHasPermission: vi.fn((permission: string) => permission === 'os.work_order.view'),
    toastError: vi.fn(),
    toastSuccess: vi.fn(),
}))

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
    return {
        ...actual,
        useNavigate: () => mockNavigate,
        useParams: () => ({ id: '1' }),
    }
})

vi.mock('@/hooks/useOfflineStore', () => ({
    useOfflineStore: () => ({
        items: [],
        putMany: mockPutMany,
        isLoading: false,
        getById: mockGetById,
        put: mockPut,
    }),
}))

vi.mock('@/hooks/usePullToRefresh', () => ({
    usePullToRefresh: () => ({
        containerRef: { current: null },
        isRefreshing: false,
        pullDistance: 0,
    }),
}))

vi.mock('@/hooks/useDisplacementTracking', () => ({
    useDisplacementTracking: vi.fn(),
}))

vi.mock('@/stores/tech-timer-store', () => ({
    useTechTimerStore: () => ({
        start: vi.fn(),
        stop: vi.fn(),
    }),
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        user: { id: 99 },
        hasPermission: mockHasPermission,
        hasRole: () => false,
    }),
}))

vi.mock('@/lib/syncEngine', () => ({
    offlinePost: mockOfflinePost,
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

vi.mock('@/components/ui/use-toast', () => ({
    useToast: () => ({ toast: mockToast }),
}))

vi.mock('sonner', () => ({
    toast: {
        error: toastError,
        success: toastSuccess,
    },
}))

vi.mock('@/components/common/SLACountdown', () => ({
    default: () => <div data-testid="sla-countdown" />,
}))

vi.mock('@/components/tech/TechChatDrawer', () => ({
    default: () => <div data-testid="tech-chat-drawer" />,
}))

vi.mock('@/components/qr/QrScannerModal', () => ({
    QrScannerModal: () => null,
}))

vi.mock('@/components/common/CurrencyInput', () => ({
    CurrencyInputInline: () => <div data-testid="currency-input" />,
}))

describe('Auditoria tecnica de OS', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        const canvasContext = {
            scale: vi.fn(),
            fillRect: vi.fn(),
            beginPath: vi.fn(),
            moveTo: vi.fn(),
            lineTo: vi.fn(),
            stroke: vi.fn(),
            set lineCap(_value: string) { },
            set lineJoin(_value: string) { },
            set lineWidth(_value: number) { },
            set strokeStyle(_value: string) { },
            set fillStyle(_value: string) { },
        }

        Object.defineProperty(HTMLCanvasElement.prototype, 'getContext', {
            configurable: true,
            value: vi.fn(() => canvasContext),
        })

        Object.defineProperty(HTMLCanvasElement.prototype, 'toDataURL', {
            configurable: true,
            value: vi.fn(() => 'data:image/png;base64,ZmFrZS1zaWduYXR1cmU='),
        })

        Object.defineProperty(HTMLCanvasElement.prototype, 'getBoundingClientRect', {
            configurable: true,
            value: vi.fn(() => ({
                width: 320,
                height: 180,
                top: 0,
                left: 0,
                right: 320,
                bottom: 180,
                x: 0,
                y: 0,
                toJSON: () => ({}),
            })),
        })

        mockApiGet.mockResolvedValue({ data: { data: [] } })
        mockApiPost.mockResolvedValue({ data: { data: { id: 1 } } })
        mockOfflinePost.mockResolvedValue(false)
        mockHasPermission.mockImplementation((permission: string) => permission === 'os.work_order.view')
        mockGetById.mockResolvedValue({
            id: 1,
            status: 'open',
            assigned_to: 99,
            technician_ids: [99],
            customer_name: 'Cliente Teste',
            description: 'OS tecnica',
            displacement_stops: [],
        })
    })

    it('sincroniza a lista tecnica a partir do envelope padrao da API', async () => {
        mockApiGet.mockResolvedValueOnce({
            data: {
                data: {
                    work_orders: [
                        {
                            id: 1,
                            status: 'open',
                            customer_name: 'Cliente A',
                        },
                    ],
                },
            },
        })

        render(<TechWorkOrdersPage />)

        await waitFor(() => {
            expect(mockPutMany).toHaveBeenCalledWith([
                expect.objectContaining({ id: 1, status: 'open' }),
            ])
        })
    })

    it('bloqueia envio de nota quando tecnico nao possui permissao de update', async () => {
        const user = userEvent.setup({ delay: null })
        mockApiGet.mockResolvedValue({ data: { data: [] } })

        render(<TechWorkOrderDetailPage />)

        const input = await screen.findByPlaceholderText('Adicionar observação...')
        await user.type(input, 'observacao interna')

        const sendButton = input.parentElement?.querySelector('button') as HTMLButtonElement | null
        expect(sendButton).not.toBeNull()
        expect(sendButton).toBeDisabled()
        expect(mockApiPost).not.toHaveBeenCalledWith('/work-orders/1/chats', expect.anything())
    })

    it('oculta cards operacionais de escrita quando tecnico nao possui permissao de update', async () => {
        render(<TechWorkOrderDetailPage />)

        expect(await screen.findByText('Chat Interno')).toBeInTheDocument()
        expect(screen.queryByText('Adicionar peça (QR)')).not.toBeInTheDocument()
        expect(screen.queryByText('Assinatura')).not.toBeInTheDocument()
    })

    it('nao envia anexo invalido no fluxo de fotos antes/depois', async () => {
        render(<BeforeAfterPhotos workOrderId={1} />)

        const input = screen.getByLabelText('Upload de foto') as HTMLInputElement
        const invalidFile = new File(['conteudo'], 'arquivo.txt', { type: 'text/plain' })

        fireEvent.change(input, { target: { files: [invalidFile] } })

        expect(mockApiPost).not.toHaveBeenCalled()
        expect(toastError).toHaveBeenCalledWith('Envie uma imagem JPG, PNG, WEBP ou GIF.')
    })

    it('nao permite salvar assinatura sem nome do assinante', async () => {
        const user = userEvent.setup({ delay: null })
        mockHasPermission.mockImplementation((permission: string) => ['os.work_order.view', 'os.work_order.update'].includes(permission))

        render(<TechSignaturePage />)

        const saveButton = screen.getByRole('button', { name: /salvar assinatura/i })
        const canvas = document.querySelector('canvas') as HTMLCanvasElement

        fireEvent.mouseDown(canvas, { clientX: 10, clientY: 10 })
        fireEvent.mouseMove(canvas, { clientX: 20, clientY: 20 })
        fireEvent.mouseUp(canvas)

        expect(saveButton).toBeDisabled()

        const signerInput = screen.getByLabelText('Nome do assinante')
        await user.type(signerInput, 'Cliente Campo')

        expect(saveButton).not.toBeDisabled()

        await user.click(saveButton)

        await waitFor(() => {
            expect(mockOfflinePost).toHaveBeenCalledWith('/tech/sync/batch', {
                mutations: [{
                    type: 'signature',
                    data: expect.objectContaining({
                        signer_name: 'Cliente Campo',
                        work_order_id: 1,
                    }),
                }],
            })
        })
        expect(mockPut).toHaveBeenCalled()
    })

    it('bloqueia checklist por url direta quando tecnico nao tem update', async () => {
        render(<TechChecklistPage />)

        expect(await screen.findByText(/edição bloqueada/i)).toBeInTheDocument()
        expect(screen.getByRole('button', { name: /salvar checklist/i })).toBeDisabled()
    })

})
