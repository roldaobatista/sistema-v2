import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@/__tests__/test-utils'
import { CrmDashboardPage } from '@/pages/CrmDashboardPage'

const { mockGetDashboard, mockHasPermission } = vi.hoisted(() => ({
    mockGetDashboard: vi.fn(),
    mockHasPermission: vi.fn(() => true),
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        hasPermission: mockHasPermission,
    }),
}))

vi.mock('@/lib/crm-api', () => ({
    crmApi: {
        getDashboard: mockGetDashboard,
    },
}))

vi.mock('@/components/ui/select', () => ({
    Select: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
    SelectTrigger: ({ children, className }: { children: React.ReactNode; className?: string }) => <button className={className} type="button">{children}</button>,
    SelectValue: () => <span>Periodo</span>,
    SelectContent: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
    SelectItem: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

vi.mock('recharts', () => ({
    ResponsiveContainer: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
    BarChart: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
    Bar: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
    XAxis: () => null,
    YAxis: () => null,
    Tooltip: () => null,
    Cell: () => null,
    CartesianGrid: () => null,
}))

describe('CrmDashboardPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('renderiza o estado de erro como conteudo principal com alerta acessivel', async () => {
        mockGetDashboard.mockRejectedValue(new Error('Falha CRM'))

        render(<CrmDashboardPage />)

        expect(await screen.findByRole('main')).toBeInTheDocument()
        expect(screen.getByRole('alert')).toBeInTheDocument()
        expect(screen.getByRole('button', { name: 'Tentar novamente' })).toBeInTheDocument()
    })
})
