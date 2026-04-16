import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import { CrmOpportunitiesPage } from '@/pages/crm/CrmOpportunitiesPage'

const {
    mockNavigate,
    mockGetLatentOpportunities,
    mockCrmApi,
} = vi.hoisted(() => ({
    mockNavigate: vi.fn(),
    mockGetLatentOpportunities: vi.fn(),
    mockCrmApi: {
        getPipelines: vi.fn(),
        getDeals: vi.fn(),
        createDeal: vi.fn(),
        getDashboard: vi.fn(),
        getConstants: vi.fn(),
    },
}))

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
    return { ...actual, useNavigate: () => mockNavigate }
})

vi.mock('@/lib/api', () => ({
    default: { get: vi.fn(), post: vi.fn() },
    getApiErrorMessage: (_: unknown, fb: string) => fb,
    unwrapData: (r: { data?: { data?: unknown } | unknown } | null | undefined) => {
        const d = r?.data
        if (d != null && typeof d === 'object' && 'data' in d) return (d as { data: unknown }).data
        return d
    },
}))

vi.mock('@/lib/crm-api', () => ({
    crmApi: mockCrmApi,
}))

vi.mock('@/lib/crm-field-api', () => ({
    getLatentOpportunities: mockGetLatentOpportunities,
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/components/crm/NewDealModal', () => ({
    NewDealModal: () => <div data-testid="new-deal-modal" />,
}))

const makeOpportunity = (overrides: Record<string, unknown> = {}) => ({
    type: 'calibration_expiring' as const,
    customer: { id: 1, name: 'Cliente A' },
    details: 'Calibracao vencendo em 15 dias',
    ...overrides,
})

describe('CrmOpportunitiesPage (Pipeline substitute)', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('renders page title', async () => {
        mockGetLatentOpportunities.mockResolvedValue({ opportunities: [], summary: { total: 0 } })
        render(<CrmOpportunitiesPage />)
        expect(screen.getByText('Oportunidades Latentes')).toBeInTheDocument()
    })

    it('shows loading state', () => {
        mockGetLatentOpportunities.mockReturnValue(new Promise(() => {}))
        render(<CrmOpportunitiesPage />)
        const spinner = document.querySelector('.animate-spin')
        expect(spinner).toBeTruthy()
    })

    it('shows empty state when no opportunities', async () => {
        mockGetLatentOpportunities.mockResolvedValue({ opportunities: [], summary: { total: 0 } })
        render(<CrmOpportunitiesPage />)
        await waitFor(() => {
            expect(screen.getByText(/Nenhuma oportunidade latente/i)).toBeInTheDocument()
        })
    })

    it('displays summary cards', async () => {
        mockGetLatentOpportunities.mockResolvedValue({
            opportunities: [makeOpportunity()],
            summary: { total: 10, calibration_expiring: 5, inactive_customers: 3, contract_renewals: 2 },
        })
        render(<CrmOpportunitiesPage />)
        await waitFor(() => {
            expect(screen.getByText('10')).toBeInTheDocument()
            expect(screen.getByText('Total')).toBeInTheDocument()
        })
    })

    it('displays calibration opportunities count', async () => {
        mockGetLatentOpportunities.mockResolvedValue({
            opportunities: [makeOpportunity()],
            summary: { total: 5, calibration_expiring: 5, inactive_customers: 0, contract_renewals: 0 },
        })
        render(<CrmOpportunitiesPage />)
        await waitFor(() => {
            expect(screen.getByText('Calibracoes')).toBeInTheDocument()
        })
    })

    it('displays inactive customers count', async () => {
        mockGetLatentOpportunities.mockResolvedValue({
            opportunities: [],
            summary: { total: 3, calibration_expiring: 0, inactive_customers: 3, contract_renewals: 0 },
        })
        render(<CrmOpportunitiesPage />)
        await waitFor(() => {
            expect(screen.getByText('Clientes inativos')).toBeInTheDocument()
        })
    })

    it('displays contract renewals count', async () => {
        mockGetLatentOpportunities.mockResolvedValue({
            opportunities: [],
            summary: { total: 2, calibration_expiring: 0, inactive_customers: 0, contract_renewals: 2 },
        })
        render(<CrmOpportunitiesPage />)
        await waitFor(() => {
            expect(screen.getByText('Contratos')).toBeInTheDocument()
        })
    })

    it('shows error state', async () => {
        mockGetLatentOpportunities.mockRejectedValue(new Error('Network error'))
        render(<CrmOpportunitiesPage />)
        await waitFor(() => {
            expect(screen.getByText(/Nao foi possivel carregar/i)).toBeInTheDocument()
        })
    })

    it('renders opportunity items', async () => {
        mockGetLatentOpportunities.mockResolvedValue({
            opportunities: [makeOpportunity({ customer: { id: 1, name: 'Empresa XYZ' } })],
            summary: { total: 1, calibration_expiring: 1, inactive_customers: 0, contract_renewals: 0 },
        })
        render(<CrmOpportunitiesPage />)
        await waitFor(() => {
            expect(screen.getByText(/Empresa XYZ/)).toBeInTheDocument()
        })
    })
})
