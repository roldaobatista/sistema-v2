import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import JourneyPage from '@/pages/rh/JourneyPage'

const {
    mockApiGet,
    mockApiPost,
    mockHasPermission,
    mockHasRole,
} = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
    mockApiPost: vi.fn(),
    mockHasPermission: vi.fn<(permission: string) => boolean>(),
    mockHasRole: vi.fn<(role: string) => boolean>(),
}))

vi.mock('@/lib/api', () => ({
    default: {
        get: mockApiGet,
        post: mockApiPost,
    },
    unwrapData: <T,>(response: { data: T }) => response.data,
    getApiErrorMessage: () => 'Erro ao calcular',
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        hasPermission: mockHasPermission,
        hasRole: mockHasRole,
    }),
}))

vi.mock('sonner', () => ({
    toast: {
        success: vi.fn(),
        error: vi.fn(),
    },
}))

describe('JourneyPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockHasRole.mockReturnValue(false)
        mockHasPermission.mockImplementation((permission: string) => permission === 'hr.journey.manage')
        mockApiPost.mockResolvedValue({ data: {} })
        mockApiGet.mockImplementation((url: string) => {
            if (url === '/technicians/options') {
                return Promise.resolve({
                    data: [{ id: 5, name: 'Carlos Técnico' }],
                })
            }

            if (url === '/hr/journey-entries') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 11,
                                user_id: 5,
                                date: '2026-03-15',
                                scheduled_hours: '8',
                                worked_hours: '9',
                                overtime_hours_50: '1',
                                overtime_hours_100: '0',
                                night_hours: '0',
                                absence_hours: '0',
                                hour_bank_balance: '1',
                                break_compliance: 'compliant',
                                is_holiday: false,
                                is_dsr: false,
                                status: 'calculated',
                            },
                        ],
                        summary: {
                            total_worked: '9',
                            total_overtime_50: '1',
                            total_overtime_100: '0',
                            total_night: '0',
                            total_absence: '0',
                            hour_bank_balance: '1',
                        },
                    },
                })
            }

            if (url === '/hr/hour-bank/balance') {
                return Promise.resolve({
                    data: {
                        balance: '1',
                    },
                })
            }

            return Promise.reject(new Error(`Unexpected GET ${url}`))
        })
    })

    it('usa a rota real de jornada e consome payload com data e summary', async () => {
        const user = userEvent.setup()

        render(<JourneyPage />)

        await screen.findByRole('option', { name: 'Carlos Técnico' }, { timeout: 5000 })
        await user.selectOptions(screen.getByRole('combobox', { name: /selecionar técnico/i }), '5')

        await waitFor(() => {
            expect(mockApiGet).toHaveBeenCalledWith('/hr/journey-entries', {
                params: {
                    user_id: 5,
                    year_month: expect.stringMatching(/^\d{4}-\d{2}$/),
                },
            })
        })

        expect(await screen.findByText('9.0h')).toBeInTheDocument()
        expect(screen.getAllByText('1.0h').length).toBeGreaterThan(0)
        expect(screen.getByText(/dom\., 15\/03/i)).toBeInTheDocument()
    })
})
