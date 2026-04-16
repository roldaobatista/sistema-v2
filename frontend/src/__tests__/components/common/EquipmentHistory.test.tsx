import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import { EquipmentHistory } from '@/components/common/EquipmentHistory'

const { mockApiGet } = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
}))

vi.mock('@/lib/api', async () => {
    const actual = await vi.importActual<typeof import('@/lib/api')>('@/lib/api')
    return {
        ...actual,
        default: {
            get: mockApiGet,
        },
        unwrapData: (response: any) => response?.data?.data ?? response?.data ?? response,
    }
})

vi.mock('@/components/ui/card', () => ({
    Card: ({ children, className }: { children: React.ReactNode; className?: string }) => (
        <div data-testid="card" className={className}>{children}</div>
    ),
}))

vi.mock('@/components/ui/badge', () => ({
    Badge: ({ children, variant }: { children: React.ReactNode; variant?: string }) => (
        <span data-testid="badge" data-variant={variant}>{children}</span>
    ),
}))

vi.mock('@/components/ui/skeleton', () => ({
    Skeleton: ({ className }: { className?: string }) => (
        <div data-testid="skeleton" className={className} />
    ),
}))

const mockHistory = [
    {
        id: 1,
        type: 'calibration',
        date: '2026-03-10',
        title: 'Calibracao semestral',
        result: 'aprovado',
        performer: 'Carlos Silva',
        work_order: null,
        description: null,
        details: { notes: 'Dentro da tolerancia' },
    },
    {
        id: 2,
        type: 'work_order',
        date: '2026-02-15',
        title: 'Manutencao corretiva',
        result: null,
        performer: 'Pedro Santos',
        work_order: { id: 45, number: '45', os_number: 'OS-045', status: 'completed' },
        description: 'Troca de componente',
        details: null,
    },
    {
        id: 3,
        type: 'maintenance',
        date: '2026-01-20',
        title: 'Revisao geral',
        result: null,
        performer: null,
        work_order: null,
        description: null,
        details: null,
    },
]

describe('EquipmentHistory', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('shows loading skeletons while fetching', () => {
        mockApiGet.mockReturnValue(new Promise(() => {})) // Never resolves
        render(<EquipmentHistory equipmentId={1} />)

        const skeletons = screen.getAllByTestId('skeleton')
        expect(skeletons.length).toBe(3)
    })

    it('shows empty state when no history', async () => {
        mockApiGet.mockResolvedValue({ data: { data: [] } })
        render(<EquipmentHistory equipmentId={1} />)

        await waitFor(() => {
            expect(screen.getByText(/Nenhum histórico encontrado/)).toBeInTheDocument()
        })
    })

    it('renders history items', async () => {
        mockApiGet.mockResolvedValue({ data: { data: mockHistory } })
        render(<EquipmentHistory equipmentId={1} />)

        await waitFor(() => {
            expect(screen.getByText('Calibracao semestral')).toBeInTheDocument()
            expect(screen.getByText('Manutencao corretiva')).toBeInTheDocument()
            expect(screen.getByText('Revisao geral')).toBeInTheDocument()
        })
    })

    it('shows approval badge for calibration results', async () => {
        mockApiGet.mockResolvedValue({ data: { data: mockHistory } })
        render(<EquipmentHistory equipmentId={1} />)

        await waitFor(() => {
            expect(screen.getByText('Aprovado')).toBeInTheDocument()
        })
    })

    it('shows OS number badge for work order items', async () => {
        mockApiGet.mockResolvedValue({ data: { data: mockHistory } })
        render(<EquipmentHistory equipmentId={1} />)

        await waitFor(() => {
            expect(screen.getByText('OS #OS-045')).toBeInTheDocument()
        })
    })

    it('shows performer name', async () => {
        mockApiGet.mockResolvedValue({ data: { data: mockHistory } })
        render(<EquipmentHistory equipmentId={1} />)

        await waitFor(() => {
            expect(screen.getByText(/Carlos Silva/)).toBeInTheDocument()
        })
    })

    it('shows "Sistema" when performer is null', async () => {
        mockApiGet.mockResolvedValue({ data: { data: mockHistory } })
        render(<EquipmentHistory equipmentId={1} />)

        await waitFor(() => {
            expect(screen.getByText(/Sistema/)).toBeInTheDocument()
        })
    })

    it('displays notes from details', async () => {
        mockApiGet.mockResolvedValue({ data: { data: mockHistory } })
        render(<EquipmentHistory equipmentId={1} />)

        await waitFor(() => {
            expect(screen.getByText('Dentro da tolerancia')).toBeInTheDocument()
        })
    })

    it('displays fallback text when no notes or description', async () => {
        mockApiGet.mockResolvedValue({ data: { data: mockHistory } })
        render(<EquipmentHistory equipmentId={1} />)

        await waitFor(() => {
            expect(screen.getByText('Sem observacoes registradas.')).toBeInTheDocument()
        })
    })
})
