import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen } from '@/__tests__/test-utils'
import { CrmPipelinePage } from '@/pages/CrmPipelinePage'

const { mockHasPermission, mockGetPipelines, mockGetDeals } = vi.hoisted(() => ({
    mockHasPermission: vi.fn(() => true),
    mockGetPipelines: vi.fn(),
    mockGetDeals: vi.fn(),
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        hasPermission: mockHasPermission,
    }),
}))

vi.mock('@/lib/crm-api', () => ({
    crmApi: {
        getPipelines: mockGetPipelines,
        getDeals: mockGetDeals,
        updateDealStage: vi.fn(),
        dealsBulkUpdate: vi.fn(),
    },
}))

vi.mock('@/lib/crm-features-api', () => ({
    crmFeaturesApi: {
        exportDealsCsv: vi.fn(),
        importDealsCsv: vi.fn(),
    },
}))

vi.mock('@/components/crm/DealCard', () => ({
    DealCard: ({ deal, onClick }: { deal: { title: string }; onClick?: () => void }) => (
        <button type="button" onClick={onClick}>
            {deal.title}
        </button>
    ),
}))

vi.mock('@/components/crm/DealDetailDrawer', () => ({
    DealDetailDrawer: () => null,
}))

vi.mock('@/components/crm/NewDealModal', () => ({
    NewDealModal: () => null,
}))

vi.mock('@dnd-kit/core', () => ({
    DndContext: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
    DragOverlay: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
    PointerSensor: class {},
    closestCorners: vi.fn(),
    useSensor: vi.fn(() => ({})),
    useSensors: vi.fn(() => []),
    useDroppable: vi.fn(() => ({ setNodeRef: vi.fn(), isOver: false })),
}))

vi.mock('@dnd-kit/sortable', () => ({
    SortableContext: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
    verticalListSortingStrategy: {},
}))

describe('CrmPipelinePage accessibility', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockGetPipelines.mockResolvedValue([
            {
                id: 1,
                name: 'Pipeline Principal',
                is_default: true,
                stages: [{ id: 10, name: 'Qualificacao', is_won: false, is_lost: false, color: '#3b82f6' }],
            },
        ])
        mockGetDeals.mockResolvedValue([
            {
                id: 99,
                title: 'Deal acessivel',
                customer: { name: 'Cliente Alfa' },
                stage_id: 10,
                value: 5000,
                probability: 50,
                expected_close_date: '2026-04-01',
                updated_at: '2026-03-27T10:00:00Z',
                assignee: { name: 'Vendedor 1' },
                source: 'site',
            },
        ])
    })

    it('expoe a busca da tabela com nome acessivel e botao para limpar a pesquisa', async () => {
        const user = userEvent.setup()

        render(<CrmPipelinePage />)

        await user.click(await screen.findByRole('button', { name: /tabela/i }))
        const searchbox = await screen.findByRole('textbox', { name: /buscar deals/i })

        await user.type(searchbox, 'alfa')

        expect(searchbox).toHaveValue('alfa')
        expect(screen.getByRole('button', { name: 'Limpar busca' })).toBeInTheDocument()
    })
})
