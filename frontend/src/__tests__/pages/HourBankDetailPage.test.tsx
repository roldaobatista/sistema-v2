import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import HourBankDetailPage from '@/pages/rh/HourBankDetailPage'

const { mockApiGet, mockUser } = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
    mockUser: { id: 42, name: 'Maria RH' },
}))

vi.mock('@/lib/api', () => ({
    default: {
        get: mockApiGet,
    },
    unwrapData: <T,>(response: { data: T }) => response.data,
    getApiErrorMessage: (_error: unknown, fallback: string) => fallback,
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        user: mockUser,
    }),
}))

describe('HourBankDetailPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockApiGet.mockImplementation((url: string) => {
            if (url === '/hr/hour-bank/balance') {
                return Promise.resolve({
                    data: {
                        user_id: mockUser.id,
                        balance: '1.5',
                    },
                })
            }

            if (url === '/hr/journey-entries') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 7,
                                date: '2026-03-10',
                                scheduled_hours: '8',
                                worked_hours: '9.5',
                            },
                        ],
                    },
                })
            }

            return Promise.reject(new Error(`Unexpected GET ${url}`))
        })
    })

    it('consulta saldo e extrato mensal com user_id e renderiza horas do contrato real', async () => {
        render(<HourBankDetailPage />)

        await waitFor(() => {
            expect(mockApiGet).toHaveBeenCalledWith('/hr/hour-bank/balance', {
                params: { user_id: 42 },
            })
        })

        await waitFor(() => {
            expect(mockApiGet).toHaveBeenCalledWith('/hr/journey-entries', {
                params: {
                    user_id: 42,
                    year_month: expect.stringMatching(/^\d{4}-\d{2}$/),
                },
            })
        })

        expect(await screen.findByText('1h30')).toBeInTheDocument()
        expect(screen.getByText('9h30')).toBeInTheDocument()
        expect(screen.getByText('8h00')).toBeInTheDocument()
        expect(screen.getByText('+1h30')).toBeInTheDocument()
    })

    it('exibe estado de erro quando o extrato mensal falha', async () => {
        mockApiGet.mockImplementation((url: string) => {
            if (url === '/hr/hour-bank/balance') {
                return Promise.resolve({
                    data: {
                        user_id: mockUser.id,
                        balance: '1.5',
                    },
                })
            }

            if (url === '/hr/journey-entries') {
                return Promise.reject(new Error('boom'))
            }

            return Promise.reject(new Error(`Unexpected GET ${url}`))
        })

        render(<HourBankDetailPage />)

        expect(await screen.findByText(/nao foi possivel carregar o banco de horas agora/i)).toBeInTheDocument()
    })
})
