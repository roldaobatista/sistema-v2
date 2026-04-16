import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@/__tests__/test-utils'
import { ExecutionActions } from '@/components/os/ExecutionActions'

const { mockApiPost } = vi.hoisted(() => ({
    mockApiPost: vi.fn(),
}))

vi.mock('@/lib/api', async () => {
    const actual = await vi.importActual<typeof import('@/lib/api')>('@/lib/api')
    return {
        ...actual,
        default: {
            post: mockApiPost,
        },
    }
})

vi.mock('sonner', () => ({
    toast: {
        success: vi.fn(),
        error: vi.fn(),
    },
}))

describe('ExecutionActions', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    // ─── 1. Renders action buttons based on work order status ───

    it('renders "Iniciar Deslocamento" when status is open', () => {
        render(<ExecutionActions workOrderId={1} status="open" />)
        expect(screen.getByRole('button', { name: /Iniciar Deslocamento/i })).toBeInTheDocument()
    })

    it('renders "Iniciar Deslocamento" when status is awaiting_dispatch', () => {
        render(<ExecutionActions workOrderId={1} status="awaiting_dispatch" />)
        expect(screen.getByRole('button', { name: /Iniciar Deslocamento/i })).toBeInTheDocument()
    })

    it('renders displacement actions when status is in_displacement', () => {
        render(<ExecutionActions workOrderId={1} status="in_displacement" />)
        expect(screen.getByRole('button', { name: /Pausar Deslocamento/i })).toBeInTheDocument()
        expect(screen.getByRole('button', { name: /Cheguei no Cliente/i })).toBeInTheDocument()
    })

    it('renders "Retomar Deslocamento" when status is displacement_paused', () => {
        render(<ExecutionActions workOrderId={1} status="displacement_paused" />)
        expect(screen.getByRole('button', { name: /Retomar Deslocamento/i })).toBeInTheDocument()
    })

    it('renders "Iniciar Serviço" when status is at_client', () => {
        render(<ExecutionActions workOrderId={1} status="at_client" />)
        expect(screen.getByRole('button', { name: /Iniciar Serviço/i })).toBeInTheDocument()
    })

    it('renders service actions when status is in_service', () => {
        render(<ExecutionActions workOrderId={1} status="in_service" />)
        expect(screen.getByRole('button', { name: /Pausar Serviço/i })).toBeInTheDocument()
        expect(screen.getByRole('button', { name: /Finalizar Serviço/i })).toBeInTheDocument()
    })

    it('normalizes in_progress to in_service and renders service actions', () => {
        render(<ExecutionActions workOrderId={1} status="in_progress" />)
        expect(screen.getByRole('button', { name: /Pausar Serviço/i })).toBeInTheDocument()
        expect(screen.getByRole('button', { name: /Finalizar Serviço/i })).toBeInTheDocument()
    })

    it('renders "Retomar Serviço" when status is service_paused', () => {
        render(<ExecutionActions workOrderId={1} status="service_paused" />)
        expect(screen.getByRole('button', { name: /Retomar Serviço/i })).toBeInTheDocument()
    })

    it('renders return actions when status is awaiting_return', () => {
        render(<ExecutionActions workOrderId={1} status="awaiting_return" />)
        expect(screen.getByRole('button', { name: /Iniciar Retorno/i })).toBeInTheDocument()
        expect(screen.getByRole('button', { name: /Encerrar OS/i })).toBeInTheDocument()
    })

    it('renders in_return actions', () => {
        render(<ExecutionActions workOrderId={1} status="in_return" />)
        expect(screen.getByRole('button', { name: /Pausar Retorno/i })).toBeInTheDocument()
        expect(screen.getByRole('button', { name: /Cheguei no Destino/i })).toBeInTheDocument()
    })

    it('renders "Retomar Retorno" when status is return_paused', () => {
        render(<ExecutionActions workOrderId={1} status="return_paused" />)
        expect(screen.getByRole('button', { name: /Retomar Retorno/i })).toBeInTheDocument()
    })

    it('renders nothing for completed/unknown status', () => {
        const { container } = render(<ExecutionActions workOrderId={1} status="completed" />)
        expect(container.innerHTML).toBe('')
    })

    // ─── 2. Buttons disabled / blocked when user lacks permission ───

    it('shows blocked message when canExecute is false', () => {
        render(<ExecutionActions workOrderId={1} status="open" canExecute={false} />)
        expect(screen.queryByRole('button', { name: /Iniciar Deslocamento/i })).not.toBeInTheDocument()
        expect(screen.getByText(/Voce nao pode executar o fluxo desta OS/i)).toBeInTheDocument()
    })

    it('shows custom blocked message when provided', () => {
        render(
            <ExecutionActions
                workOrderId={1}
                status="open"
                canExecute={false}
                blockedMessage="Somente o tecnico designado pode executar."
            />,
        )
        expect(screen.getByText('Somente o tecnico designado pode executar.')).toBeInTheDocument()
    })

    it('shows "Execucao em Campo" header in blocked state', () => {
        render(<ExecutionActions workOrderId={1} status="in_displacement" canExecute={false} />)
        expect(screen.getByText(/Execucao em Campo/i)).toBeInTheDocument()
    })

    // ─── 3. Correct button labels for different statuses (summary table) ───

    const statusButtonMap: Record<string, string[]> = {
        open: ['Iniciar Deslocamento'],
        awaiting_dispatch: ['Iniciar Deslocamento'],
        in_displacement: ['Pausar Deslocamento', 'Cheguei no Cliente'],
        displacement_paused: ['Retomar Deslocamento'],
        at_client: ['Iniciar Serviço'],
        in_service: ['Pausar Serviço', 'Finalizar Serviço'],
        service_paused: ['Retomar Serviço'],
        awaiting_return: ['Iniciar Retorno', 'Encerrar OS (sem retorno)'],
        in_return: ['Pausar Retorno', 'Cheguei no Destino'],
        return_paused: ['Retomar Retorno'],
    }

    Object.entries(statusButtonMap).forEach(([status, expectedLabels]) => {
        it(`status "${status}" renders exactly ${expectedLabels.length} button(s): ${expectedLabels.join(', ')}`, () => {
            render(<ExecutionActions workOrderId={1} status={status} />)
            const buttons = screen.getAllByRole('button')
            expect(buttons).toHaveLength(expectedLabels.length)
            expectedLabels.forEach((label) => {
                expect(screen.getByRole('button', { name: new RegExp(label.replace(/[()]/g, '\\$&'), 'i') })).toBeInTheDocument()
            })
        })
    })
})
