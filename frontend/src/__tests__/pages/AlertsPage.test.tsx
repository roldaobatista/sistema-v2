import type { ReactNode } from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import AlertsPage from '@/pages/alertas/AlertsPage'

const {
    mockApiGet,
    mockApiPost,
    mockApiPut,
    toastSuccess,
    toastError,
} = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
    mockApiPost: vi.fn(),
    mockApiPut: vi.fn(),
    toastSuccess: vi.fn(),
    toastError: vi.fn(),
}))

vi.mock('@/lib/api', () => ({
    default: {
        get: mockApiGet,
        post: mockApiPost,
        put: mockApiPut,
    },
    getApiErrorMessage: (err: unknown, fallback: string) =>
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? fallback,
}))

vi.mock('sonner', () => ({
    toast: {
        success: toastSuccess,
        error: toastError,
    },
}))

vi.mock('@/components/ui/select', () => ({
    Select: ({
        value,
        onValueChange,
    }: {
        value: string
        onValueChange: (value: string) => void
    }) => (
        <select
            aria-label="Agrupar por"
            value={value}
            onChange={(event) => onValueChange(event.target.value)}
        >
            <option value="none">Lista normal</option>
            <option value="alert_type">Por tipo</option>
            <option value="entity">Por entidade</option>
        </select>
    ),
    SelectTrigger: ({ children }: { children: ReactNode }) => <>{children}</>,
    SelectValue: ({ placeholder }: { placeholder?: string }) => <span>{placeholder}</span>,
    SelectContent: ({ children }: { children: ReactNode }) => <>{children}</>,
    SelectItem: ({ children }: { children: ReactNode; value: string }) => <>{children}</>,
}))

const pendingAlertsPayload = {
    data: [
        {
            id: 1,
            priority: 'high',
            title: 'Contrato sem retorno',
            description: 'Cliente está sem contato há 30 dias',
            type: 'no_contact',
            status: 'pending',
            created_at: '2026-03-20T10:00:00Z',
            customer: { id: 11, name: 'Cliente Atlas' },
        },
        {
            id: 2,
            priority: 'critical',
            title: 'Calibração vencendo',
            description: 'Equipamento precisa de ação imediata',
            type: 'calibration_expiring',
            status: 'pending',
            created_at: '2026-03-20T11:00:00Z',
            equipment: { id: 7, code: 'EQ-77', brand: 'Toledo', model: '9090' },
        },
    ],
    meta: {
        total: 2,
    },
}

describe('AlertsPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()

        mockApiGet.mockImplementation((url: string, config?: { params?: Record<string, string | number> }) => {
            if (url === '/alerts') {
                expect(config?.params).toMatchObject({
                    status: 'pending',
                    per_page: 100,
                })

                return Promise.resolve({
                    data: pendingAlertsPayload,
                })
            }

            return Promise.resolve({ data: {} })
        })

        mockApiPost.mockResolvedValue({
            data: {
                message: '2 alertas gerados',
            },
        })

        mockApiPut.mockResolvedValue({ data: { data: { id: 1 } } })
    })

    it('renderiza alertas pendentes usando o contrato real do backend', async () => {
        render(<AlertsPage />)

        expect(await screen.findByText('Contrato sem retorno')).toBeInTheDocument()
        expect(screen.getByText('Cliente está sem contato há 30 dias')).toBeInTheDocument()
        expect(screen.getByText('Cliente Atlas')).toBeInTheDocument()
        expect(screen.getByText('Equipamento precisa de ação imediata')).toBeInTheDocument()
        expect(screen.getByText('Total Ativos')).toBeInTheDocument()
    })

    it('agrupa localmente por tipo quando solicitado', async () => {
        const user = userEvent.setup({ delay: null })

        render(<AlertsPage />)

        await screen.findByText('Contrato sem retorno')
        await user.selectOptions(screen.getByRole('combobox', { name: 'Agrupar por' }), 'alert_type')

        expect(screen.getByText('Sem contato')).toBeInTheDocument()
        expect(screen.getByText('Calibração vencendo')).toBeInTheDocument()
        expect(screen.getAllByText('1 alerta(s)')).toHaveLength(2)
    })

    it('usa POST para reconhecer e POST /alerts/run-engine para executar verificacao', async () => {
        const user = userEvent.setup({ delay: null })

        render(<AlertsPage />)

        await screen.findByText('Contrato sem retorno')

        await user.click(screen.getAllByRole('button', { name: 'Reconhecer alerta' })[0])
        await waitFor(() => {
            expect(mockApiPost).toHaveBeenCalledWith('/alerts/1/acknowledge')
        })

        await user.click(screen.getByRole('button', { name: /Executar Verificação/i }))
        await waitFor(() => {
            expect(mockApiPost).toHaveBeenCalledWith('/alerts/run-engine')
            expect(toastSuccess).toHaveBeenCalledWith('2 alertas gerados')
        })
    })
})
