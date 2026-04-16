import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import EspelhoPontoPage from '@/pages/rh/EspelhoPontoPage'

const { mockApiGet, mockApiPost, mockToastSuccess, mockToastError } = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
    mockApiPost: vi.fn(),
    mockToastSuccess: vi.fn(),
    mockToastError: vi.fn(),
}))

vi.mock('@/lib/api', () => ({
    default: {
        get: mockApiGet,
        post: mockApiPost,
    },
    unwrapData: <T,>(response: { data: T }) => response.data,
    getApiErrorMessage: (_error: unknown, fallback: string) => fallback,
}))

vi.mock('sonner', () => ({
    toast: {
        success: mockToastSuccess,
        error: mockToastError,
    },
}))

function makeEspelho(overrides: Record<string, unknown> = {}) {
    return {
        employee: {
            id: 7,
            name: 'Maria Souza',
            pis: '123456',
            cpf: '***.456.789-00',
            admission_date: '01/02/2024',
            work_shift: '08:00-17:00',
            cbo_code: '0000',
        },
        period: {
            year: 2026,
            month: 3,
            month_name: 'março',
            start_date: '01/03/2026',
            end_date: '31/03/2026',
        },
        days: [
            {
                date: '15/03/2026',
                day_of_week: 'Dom',
                total_hours: 8.5,
                total_break_minutes: 60,
                entries: [
                    {
                        id: 11,
                        clock_in: '08:00',
                        clock_out: '17:30',
                        break_start: '12:00',
                        break_end: '13:00',
                        clock_method: 'app',
                        approval_status: 'approved',
                    },
                ],
            },
        ],
        summary: {
            total_work_days: 1,
            total_hours: 8.5,
            total_minutes: 510,
            total_break_minutes: 60,
            average_hours_per_day: 8.5,
        },
        confirmation: null,
        ...overrides,
    }
}

describe('EspelhoPontoPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockApiGet.mockResolvedValue({ data: makeEspelho() })
        mockApiPost.mockResolvedValue({ data: { id: 99 } })
    })

    it('exibe navegacao acessivel e assina o espelho com o payload esperado', async () => {
        const user = userEvent.setup()
        const now = new Date()
        const expectedYear = now.getFullYear()
        const expectedMonth = now.getMonth() + 1

        render(<EspelhoPontoPage />)

        await waitFor(() => {
            expect(mockApiGet).toHaveBeenCalled()
        })
        expect(await screen.findByText(/folha de registro de ponto/i, {}, { timeout: 5000 })).toBeInTheDocument()
        expect(screen.getByRole('button', { name: /assinar eletronicamente/i })).toBeInTheDocument()
        expect(screen.getByRole('button', { name: /mês anterior/i })).toBeInTheDocument()
        expect(screen.getByRole('button', { name: /próximo mês/i })).toBeInTheDocument()

        await user.click(screen.getByRole('button', { name: /assinar eletronicamente/i }))
        await user.type(screen.getByLabelText(/sua senha de acesso/i), 'secret123')
        await user.click(screen.getByRole('button', { name: /assinar documento/i }))

        await waitFor(() => {
            expect(mockApiPost).toHaveBeenCalledWith('/hr/clock/espelho/confirm', {
                year: expectedYear,
                month: expectedMonth,
                password: 'secret123',
            })
        })
    })

    it('mostra erro amigavel quando a consulta do espelho falha', async () => {
        mockApiGet.mockRejectedValueOnce(new Error('boom'))

        render(<EspelhoPontoPage />)

        expect(await screen.findByText(/erro ao carregar espelho de ponto/i, {}, { timeout: 5000 })).toBeInTheDocument()
    })
})
