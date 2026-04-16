import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@/__tests__/test-utils'
import { WorkOrderDetailPage } from '@/pages/os/WorkOrderDetailPage'

const {
    mockApiGet,
    mockHasPermission,
    mockNavigate,
    mockDetail,
    mockUseParams,
    mockUser,
} = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
    mockHasPermission: vi.fn(),
    mockNavigate: vi.fn(),
    mockDetail: vi.fn(),
    mockUseParams: vi.fn(),
    mockUser: { id: 1, name: 'Admin', roles: ['admin'], all_roles: ['admin'] },
}))

vi.mock('@/lib/api', () => ({
    default: {
        get: mockApiGet,
    },
    buildStorageUrl: (p: string) => p,
    unwrapData: <T,>(res: { data?: { data?: T } | T } | undefined) => {
        if (!res) return undefined
        const d = res?.data
        if (d && typeof d === 'object' && 'data' in d) return (d as { data: T }).data
        return d as T
    },
}))

vi.mock('@/lib/work-order-api', () => ({
    workOrderApi: {
        detail: mockDetail,
        create: vi.fn(),
        update: vi.fn(),
        updateStatus: vi.fn(),
        addItem: vi.fn(),
        removeItem: vi.fn(),
        pdf: vi.fn(),
        duplicate: vi.fn(),
        reopen: vi.fn(),
        authorizeDispatch: vi.fn(),
        checklistResponses: vi.fn().mockResolvedValue({ data: [] }),
        applyKit: vi.fn(),
    },
}))

vi.mock('@/lib/query-keys', () => ({
    queryKeys: {
        workOrders: {
            all: ['work-orders'],
            detail: (id: number) => ['work-orders', 'detail', id],
            checklist: (id: string) => ['work-orders', 'checklist', id],
            checklistTemplate: (id?: number) => ['work-orders', 'checklist-template', id],
            customerEquipments: (id: number) => ['work-orders', 'customer-equipments', id],
            costEstimate: (id: string) => ['work-orders', 'cost-estimate', id],
        },
        products: { all: ['products'], options: ['products', 'options'] },
        services: { all: ['services'], options: ['services', 'options'] },
        stock: { all: ['stock'] },
    },
}))

vi.mock('@/lib/cross-tab-sync', () => ({
    broadcastQueryInvalidation: vi.fn(),
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        hasPermission: mockHasPermission,
        user: mockUser,
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
        useParams: () => mockUseParams() ?? { id: '1' },
        Link: ({ children, ...props }: { children: React.ReactNode; to: string }) => <a {...props}>{children}</a>,
    }
})

vi.mock('sonner', () => ({
    toast: {
        success: vi.fn(),
        error: vi.fn(),
        warning: vi.fn(),
    },
}))

vi.mock('@/lib/work-order-detail-utils', () => ({
    extractWorkOrderQrProduct: vi.fn(),
    isPrivilegedFieldRole: vi.fn().mockReturnValue(true),
    isTechnicianLinkedToWorkOrder: vi.fn().mockReturnValue(true),
}))

vi.mock('@/lib/labelQr', () => ({
    parseLabelQrPayload: vi.fn(),
}))

vi.mock('@/lib/calibration-utils', () => ({
    getCalibrationReadingsPath: vi.fn(),
}))

vi.mock('@/lib/status-config', () => ({
    workOrderStatus: {
        open: { label: 'Aberta', variant: 'default' },
        in_progress: { label: 'Em Andamento', variant: 'warning' },
        completed: { label: 'Concluída', variant: 'success' },
        delivered: { label: 'Entregue', variant: 'success' },
        cancelled: { label: 'Cancelada', variant: 'destructive' },
    },
}))

vi.mock('@/components/common/SLACountdown', () => ({
    default: () => <span data-testid="sla-countdown" />,
}))

vi.mock('@/components/signature/SignaturePad', () => ({
    SignaturePad: () => <div data-testid="signature-pad" />,
}))

vi.mock('@/components/os/PhotoChecklist', () => ({
    default: () => <div data-testid="photo-checklist" />,
}))

vi.mock('@/components/os/DeliveryForecast', () => ({
    default: () => <div data-testid="delivery-forecast" />,
}))

vi.mock('@/components/os/ApprovalChain', () => ({
    default: () => <div data-testid="approval-chain" />,
}))

vi.mock('@/components/qr/QrScannerModal', () => ({
    QrScannerModal: () => <div data-testid="qr-scanner" />,
}))

const mockWorkOrder = {
    id: 1,
    business_number: 'OS-2024-001',
    os_number: '001',
    number: 1,
    status: 'open',
    priority: 'normal',
    is_warranty: false,
    customer_id: 10,
    customer: { id: 10, name: 'Cliente Teste LTDA', document: '12.345.678/0001-00', email: 'teste@teste.com', contacts: [{ phone: '(11) 99999-0000' }] },
    description: 'Manutenção preventiva',
    created_at: '2024-01-15T10:00:00Z',
    creator: { name: 'Admin' },
    origin_type: 'manual',
    sla_due_at: null,
    sla_responded_at: null,
    assigned_to: null,
    seller_id: null,
    driver_id: null,
    technician_ids: [],
    items: [],
    attachments: [],
    equipments: [],
    children: [],
    parent: null,
    parent_id: null,
    waze_link: null,
    google_maps_link: null,
    dispatch_authorized_at: null,
    return_destination: null,
    displacement_value: '0',
    estimated_profit: null,
    checklist_id: null,
}

describe('WorkOrderDetailPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockUseParams.mockReturnValue({ id: '1' })
        mockApiGet.mockResolvedValue({ data: { data: [] } })
    })

    it('renderiza sem erros quando dados da OS estao carregados', async () => {
        mockHasPermission.mockReturnValue(true)
        mockDetail.mockResolvedValue({ data: { data: mockWorkOrder } })

        render(<WorkOrderDetailPage />)

        expect(await screen.findByText('OS-2024-001')).toBeInTheDocument()
    })

    it('mostra permissao negada sem permissao de transicao', async () => {
        mockHasPermission.mockImplementation((perm: string) => {
            if (perm === 'os.work_order.change_status') return false
            return true
        })
        mockDetail.mockResolvedValue({ data: { data: mockWorkOrder } })

        render(<WorkOrderDetailPage />)

        expect(await screen.findByText('OS-2024-001')).toBeInTheDocument()
        expect(screen.getByText(/nao tem permissao para executar transicoes/i)).toBeInTheDocument()
    })

    it('renderiza informacoes da OS (numero, status, cliente)', async () => {
        mockHasPermission.mockReturnValue(true)
        mockDetail.mockResolvedValue({ data: { data: mockWorkOrder } })

        render(<WorkOrderDetailPage />)

        // Numero da OS
        expect(await screen.findByText('OS-2024-001')).toBeInTheDocument()

        // Status badge (pode aparecer mais de uma vez)
        expect(screen.getAllByText('Aberta').length).toBeGreaterThan(0)

        // Cliente
        expect(screen.getByText('Cliente Teste LTDA')).toBeInTheDocument()
    })
})
