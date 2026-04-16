import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import EquipmentEditPage from '@/pages/equipamentos/EquipmentEditPage'

const {
    mockNavigate,
    mockHasPermission,
    mockEquipmentApi,
    mockApiGet,
} = vi.hoisted(() => ({
    mockNavigate: vi.fn(),
    mockHasPermission: vi.fn(),
    mockEquipmentApi: {
        detail: vi.fn(),
        constants: vi.fn(),
        update: vi.fn(),
    },
    mockApiGet: vi.fn(),
}))

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
    return {
        ...actual,
        useNavigate: () => mockNavigate,
        useParams: () => ({ id: '25' }),
    }
})

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        hasPermission: mockHasPermission,
    }),
}))

vi.mock('@/lib/equipment-api', () => ({
    equipmentApi: mockEquipmentApi,
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

vi.mock('@/components/common/CustomerAsyncSelect', () => ({
    CustomerAsyncSelect: ({ label }: { label?: string }) => <div>{label}</div>,
}))

vi.mock('@/components/ui/pageheader', () => ({
    PageHeader: ({ title }: { title: string }) => <div>{title}</div>,
}))

vi.mock('@/lib/cross-tab-sync', () => ({ broadcastQueryInvalidation: vi.fn() }))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

describe('EquipmentEditPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockHasPermission.mockReturnValue(true)
        mockEquipmentApi.detail.mockResolvedValue({
            id: 25,
            code: 'EQP-00018',
            customer_id: 1,
            status: 'ativo',
            capacity: '100000.0000',
            capacity_unit: 'kg',
            resolution: null,
            precision_class: null,
            location: null,
            calibration_interval_months: 6,
            inmetro_number: null,
            tag: null,
            is_critical: false,
            is_active: true,
            notes: null,
        })
        mockEquipmentApi.constants.mockResolvedValue({
            categories: { balanca_rodoviaria: 'Balança Rodoviária' },
            precision_classes: {},
            statuses: {
                active: 'Ativo',
                in_calibration: 'Em Calibração',
                in_maintenance: 'Em Manutenção',
                out_of_service: 'Fora de Uso',
                discarded: 'Descartado',
            },
        })
        mockEquipmentApi.update.mockResolvedValue({ id: 25, status: 'active' })
        mockApiGet.mockResolvedValue({ data: [] })
    })

    it('normaliza status legado antes de enviar a atualização', async () => {
        const user = userEvent.setup()

        render(<EquipmentEditPage />)

        await screen.findByText('Editar EQP-00018')
        await user.click(screen.getByRole('button', { name: /salvar/i }))

        await waitFor(() => {
            expect(mockEquipmentApi.update).toHaveBeenCalledWith(
                25,
                expect.objectContaining({
                    status: 'active',
                })
            )
        })
    })
})
