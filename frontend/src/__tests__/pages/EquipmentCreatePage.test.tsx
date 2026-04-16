import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@/__tests__/test-utils'
import EquipmentCreatePage from '@/pages/equipamentos/EquipmentCreatePage'

const {
    mockHasPermission,
    mockNavigate,
    mockEquipmentConstants,
    mockCustomerDetail,
    mockApiGet,
} = vi.hoisted(() => ({
    mockHasPermission: vi.fn(),
    mockNavigate: vi.fn(),
    mockEquipmentConstants: vi.fn(),
    mockCustomerDetail: vi.fn(),
    mockApiGet: vi.fn(),
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
        useSearchParams: () => [new URLSearchParams('customer_id=74'), vi.fn()],
    }
})

vi.mock('@/lib/equipment-api', () => ({
    equipmentApi: {
        constants: mockEquipmentConstants,
        create: vi.fn(),
    },
}))

vi.mock('@/lib/customer-api', () => ({
    customerApi: {
        detail: mockCustomerDetail,
    },
}))

vi.mock('@/lib/api', async () => {
    const actual = await vi.importActual<typeof import('@/lib/api')>('@/lib/api')

    return {
        ...actual,
        default: {
            ...actual.default,
            get: mockApiGet,
        },
    }
})

vi.mock('@/components/common/LookupCombobox', () => ({
    LookupCombobox: ({ label }: { label?: string }) => <div>{label}</div>,
}))

vi.mock('sonner', () => ({
    toast: {
        error: vi.fn(),
        success: vi.fn(),
        warning: vi.fn(),
    },
}))

describe('EquipmentCreatePage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockHasPermission.mockReturnValue(true)
        mockEquipmentConstants.mockResolvedValue({
            categories: { balanca_plataforma: 'Balancas Comerciais' },
            precision_classes: {},
        })
        mockCustomerDetail.mockResolvedValue({
            id: 74,
            name: 'Cliente Print',
            document: '12.345.678/0001-90',
            phone: '(65) 99999-0001',
            address_city: 'Cuiaba',
            address_state: 'MT',
        })
        mockApiGet.mockResolvedValue({ data: { data: [] } })
    })

    it('pre-seleciona o cliente vindo da url no cadastro de equipamento', async () => {
        render(<EquipmentCreatePage />)

        expect(await screen.findByText('Cliente Print')).toBeInTheDocument()
    })
})
