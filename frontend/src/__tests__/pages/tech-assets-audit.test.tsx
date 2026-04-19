import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import TechAssetScanPage from '@/pages/tech/TechAssetScanPage'
import TechEquipmentSearchPage from '@/pages/tech/TechEquipmentSearchPage'

const {
    mockNavigate,
    mockApiGet,
} = vi.hoisted(() => ({
    mockNavigate: vi.fn(),
    mockApiGet: vi.fn(),
}))

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
    return {
        ...actual,
        useNavigate: () => mockNavigate,
        useSearchParams: () => [new URLSearchParams()],
    }
})

vi.mock('@/lib/api', async () => {
    const actual = await vi.importActual<typeof import('@/lib/api')>('@/lib/api')
    return {
        ...actual,
        default: {
            get: mockApiGet,
            post: vi.fn(),
        },
    }
})

vi.mock('sonner', () => ({
    toast: {
        error: vi.fn(),
        success: vi.fn(),
    },
}))

describe('Auditoria das telas tecnicas de ativos e equipamentos', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('desembrulha respostas envelopadas ao buscar equipamentos', async () => {
        mockApiGet.mockImplementation((url: string) => {
            if (url === '/equipments') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 7,
                                brand: 'Fluke',
                                model: '87V',
                                tag: 'EQ-87V',
                                serial_number: 'SN-001',
                                status: 'ativo',
                            },
                        ],
                    },
                })
            }

            if (url === '/equipments/7') {
                return Promise.resolve({ data: { data: { equipment: { location: 'Laboratório' } } } })
            }

            return Promise.resolve({ data: { data: [] } })
        })

        const user = userEvent.setup()
        render(<TechEquipmentSearchPage />)

        await user.type(screen.getByPlaceholderText('Buscar por TAG, nº série, nome...'), '87')
        await waitFor(() => {
            expect(mockApiGet).toHaveBeenCalledWith('/equipments', {
                params: { search: '87', per_page: 20 },
            })
        })

        expect(await screen.findByText('Fluke 87V')).toBeInTheDocument()

        const historyButton = screen
            .getAllByRole('button', { name: /Ver histórico/i })
            .find((element) => element.tagName === 'BUTTON')

        expect(historyButton).toBeDefined()
        expect(historyButton.parentElement?.closest('button')).toBeNull()
    })

    it('desembrulha listas envelopadas ao localizar ativo por codigo', async () => {
        const user = userEvent.setup()

        mockApiGet.mockImplementation((url: string) => {
            if (url === '/mobile/barcode-lookup') {
                return Promise.reject(new Error('not-found'))
            }

            if (url === '/equipments') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 1,
                                code: 'TAG-001',
                                model: 'Balança',
                                brand: 'Toledo',
                                status: 'active',
                            },
                        ],
                    },
                })
            }

            if (url === '/asset-tags') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 11,
                                tag_code: 'TAG-001',
                                tag_type: 'qr',
                                last_scanned_at: null,
                            },
                        ],
                    },
                })
            }

            if (url === '/equipments/1/calibrations') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 21,
                                calibration_date: '2026-03-20',
                                result: 'approved',
                            },
                        ],
                    },
                })
            }

            if (url === '/work-orders') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 31,
                                os_number: 'OS-31',
                                created_at: '2026-03-20',
                                status: 'open',
                            },
                        ],
                    },
                })
            }

            return Promise.resolve({ data: { data: [] } })
        })

        render(<TechAssetScanPage />)

        await user.type(screen.getByPlaceholderText('Ou digite tag/código'), 'TAG-001')
        await user.click(screen.getByRole('button', { name: /Buscar/i }))

        expect((await screen.findAllByText('TAG-001')).length).toBeGreaterThan(0)
        expect(screen.getByText('Balança')).toBeInTheDocument()
        expect(screen.getByText(/Histórico de Calibração/i)).toBeInTheDocument()
    })
})
