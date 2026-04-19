import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import TechCalibrationReadingsPage from '@/pages/tech/TechCalibrationReadingsPage'
import TechCertificatePage from '@/pages/tech/TechCertificatePage'

const {
    mockNavigate,
    mockApiGet,
    toastError,
    toastSuccess,
} = vi.hoisted(() => ({
    mockNavigate: vi.fn(),
    mockApiGet: vi.fn(),
    toastError: vi.fn(),
    toastSuccess: vi.fn(),
}))

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
    return {
        ...actual,
        useNavigate: () => mockNavigate,
        useParams: () => ({ id: '42' }),
    }
})

vi.mock('@/lib/api', () => ({
    default: {
        get: mockApiGet,
        post: vi.fn(),
    },
    getApiOrigin: () => 'http://localhost:8000',
}))

vi.mock('sonner', () => ({
    toast: {
        error: toastError,
        success: toastSuccess,
    },
}))

describe('paginas tecnicas de calibracao e certificado', () => {
    beforeEach(() => {
        vi.clearAllMocks()

        mockApiGet.mockImplementation((url: string) => {
            if (url === '/work-orders/42') {
                return Promise.resolve({
                    data: {
                        data: {
                            equipment: { id: 1, code: 'BAL-01', serial_number: 'SN-001' },
                            equipmentsList: [
                                { equipment: { id: 2, code: 'BAL-02', serial_number: 'SN-002' } },
                                { id: 1, code: 'BAL-01', serial_number: 'SN-001' },
                            ],
                        },
                    },
                })
            }

            if (url === '/certificate-templates') {
                return Promise.resolve({
                    data: {
                        data: [
                            { id: 10, name: 'Template Padrao', is_default: true },
                            { id: 11, name: 'Template Reserva', is_default: false },
                        ],
                    },
                })
            }

            if (url === '/equipments/1/calibrations') {
                return Promise.resolve({
                    data: {
                        calibrations: {
                            data: [
                                {
                                    id: 100,
                                    work_order_id: 42,
                                    calibration_date: '2026-03-20',
                                    result: 'approved',
                                    certificate_number: 'CERT-001',
                                },
                            ],
                        },
                    },
                })
            }

            if (url === '/equipments/2/calibrations') {
                return Promise.resolve({
                    data: {
                        calibrations: [],
                    },
                })
            }

            return Promise.resolve({ data: { data: [] } })
        })
    })

    it('lista equipamentos unicos na tela de leituras mesmo com payload misto da OS', async () => {
        render(<TechCalibrationReadingsPage />)

        expect(await screen.findByText('Selecione o equipamento')).toBeInTheDocument()

        await waitFor(() => {
            expect(screen.getByText('BAL-01')).toBeInTheDocument()
            expect(screen.getByText('BAL-02')).toBeInTheDocument()
        })

        expect(screen.queryAllByText('BAL-01')).toHaveLength(1)
        expect(toastError).not.toHaveBeenCalled()
    })

    it('normaliza equipamentos e templates envelopados na tela de certificado', async () => {
        const user = userEvent.setup()
        render(<TechCertificatePage />)

        expect(await screen.findByText('Certificado de Calibração')).toBeInTheDocument()

        await user.click(await screen.findByRole('button', { name: /bal-01/i }))

        await waitFor(() => {
            expect(screen.getByText('BAL-01')).toBeInTheDocument()
            expect(screen.getByText('BAL-02')).toBeInTheDocument()
            expect(screen.getByText('Template Padrao')).toBeInTheDocument()
            expect(screen.getByText('CERT-001')).toBeInTheDocument()
        })

        expect(screen.queryAllByText('BAL-01')).toHaveLength(1)
        expect(toastError).not.toHaveBeenCalled()
    })
})
