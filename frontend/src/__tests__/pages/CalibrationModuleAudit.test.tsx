import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import CalibrationListPage from '@/pages/calibracao/CalibrationListPage'
import CalibrationReadingsPage from '@/pages/calibracao/CalibrationReadingsPage'
import CertificateTemplatesPage from '@/pages/calibracao/CertificateTemplatesPage'
import TechCertificatePage from '@/pages/tech/TechCertificatePage'

const {
    mockNavigate,
    mockApiGet,
    mockApiPost,
    mockApiDelete,
    toastError,
    toastSuccess,
} = vi.hoisted(() => ({
    mockNavigate: vi.fn(),
    mockApiGet: vi.fn(),
    mockApiPost: vi.fn(),
    mockApiDelete: vi.fn(),
    toastError: vi.fn(),
    toastSuccess: vi.fn(),
}))

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
    return {
        ...actual,
        useNavigate: () => mockNavigate,
        useParams: () => ({ id: '42', calibrationId: '99' }),
    }
})

vi.mock('@/lib/api', async () => {
    const actual = await vi.importActual<typeof import('@/lib/api')>('@/lib/api')
    return {
        ...actual,
        default: {
            get: mockApiGet,
            post: mockApiPost,
            put: vi.fn(),
            delete: mockApiDelete,
        },
        getApiOrigin: () => 'http://localhost:8000',
    }
})

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        hasPermission: () => true,
        hasRole: () => true,
    }),
}))

vi.mock('sonner', () => ({
    toast: {
        success: toastSuccess,
        error: toastError,
    },
}))

vi.mock('react-hook-form', async () => {
    const actual = await vi.importActual<typeof import('react-hook-form')>('react-hook-form')
    return actual
})

describe('auditoria do modulo de calibracao', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockApiPost.mockResolvedValue({ data: {} })
        mockApiDelete.mockResolvedValue({ data: {} })

        // Stub window.confirm para testes de delete
        vi.spyOn(window, 'confirm').mockReturnValue(true)

        mockApiGet.mockImplementation((url: string) => {
            if (url === '/calibration') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 1,
                                certificate_number: 'CERT-2026-001',
                                calibration_date: '2026-03-20',
                                result: 'approved',
                                equipment: {
                                    id: 91,
                                    code: 'BAL-091',
                                    brand: 'Toledo',
                                    model: 'Prix',
                                    serial_number: 'SN-091',
                                    precision_class: 'III',
                                    customer: { id: 7, name: 'Cliente Demo' },
                                },
                                performer: { id: 1, name: 'Tecnico A' },
                            },
                        ],
                        meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
                    },
                })
            }

            if (url === '/equipments') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 91,
                                code: 'BAL-091',
                                brand: 'Toledo',
                                model: 'Prix',
                                serial_number: 'SN-091',
                                precision_class: 'III',
                                capacity: 300,
                                capacity_unit: 'kg',
                                customer: { id: 7, name: 'Cliente Demo' },
                            },
                        ],
                    },
                })
            }

            if (url === '/certificate-templates') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 1,
                                name: 'Template RBC',
                                description: 'Layout principal',
                                layout_config: {
                                    show_readings: true,
                                    show_eccentricity: false,
                                    show_conformity: true,
                                    show_weights: true,
                                    show_qr_code: false,
                                },
                                is_default: true,
                                created_at: '2026-03-20T10:00:00Z',
                                updated_at: '2026-03-20T10:00:00Z',
                            },
                            {
                                id: 2,
                                name: 'Template Simples',
                                description: 'Layout simplificado',
                                layout_config: {
                                    show_readings: true,
                                    show_eccentricity: false,
                                    show_conformity: false,
                                    show_weights: false,
                                    show_qr_code: false,
                                },
                                is_default: false,
                                created_at: '2026-03-21T10:00:00Z',
                                updated_at: '2026-03-21T10:00:00Z',
                            },
                        ],
                    },
                })
            }

            if (url.match(/\/calibration\/\d+\/readings/)) {
                return Promise.resolve({ data: { data: [] } })
            }

            if (url.match(/\/work-orders\/\d+/)) {
                return Promise.resolve({
                    data: {
                        data: {
                            id: 42,
                            equipment: { id: 91, code: 'BAL-091', serial_number: 'SN-091' },
                            equipmentsList: [
                                { id: 91, code: 'BAL-091', serial_number: 'SN-091', equipment: { id: 91, code: 'BAL-091', serial_number: 'SN-091' } },
                            ],
                        },
                    },
                })
            }

            if (url.match(/\/equipments\/\d+\/calibrations/)) {
                return Promise.resolve({
                    data: {
                        calibrations: [
                            {
                                id: 1,
                                calibration_date: '2026-03-20',
                                result: 'approved',
                                certificate_number: 'CERT-2026-001',
                                work_order_id: 42,
                            },
                        ],
                    },
                })
            }

            return Promise.resolve({ data: { data: [] } })
        })
    })

    it('abre a selecao de equipamento usando envelope normalizado na lista de calibracoes', async () => {
        const user = userEvent.setup()
        render(<CalibrationListPage />)

        await user.click(await screen.findByRole('button', { name: /nova calibração/i }))

        await waitFor(() => {
            expect(screen.getAllByText(/BAL-091/i).length).toBeGreaterThanOrEqual(1)
            expect(screen.getAllByText(/Cliente Demo/i).length).toBeGreaterThanOrEqual(1)
        })
    })

    it('renderiza badges do template sem vazar suppressions no JSX', async () => {
        render(<CertificateTemplatesPage />)

        expect(await screen.findByText('Template RBC')).toBeInTheDocument()
        expect(screen.getAllByText('Leituras').length).toBeGreaterThanOrEqual(1)
        expect(screen.getByText('Conformidade')).toBeInTheDocument()
        expect(screen.getByText('Pesos Padrão')).toBeInTheDocument()
        expect(screen.queryByText(/@ts-ignore/i)).not.toBeInTheDocument()
    })

    it('CalibrationReadingsPage: impede submissao com leituras vazias (reference_value vazio)', async () => {
        const user = userEvent.setup()
        render(<CalibrationReadingsPage />)

        // Aguarda a pagina carregar com a leitura padrao (reference_value vazio)
        expect(await screen.findByText(/Leituras de Calibração/i)).toBeInTheDocument()

        // Clica em Salvar sem preencher nada
        await user.click(screen.getByRole('button', { name: /Salvar Leituras/i }))

        // Deve exibir toast de erro de validacao
        await waitFor(() => {
            expect(toastError).toHaveBeenCalledWith('Corrija os erros de validação antes de salvar')
        })

        // api.post NAO deve ter sido chamado (formulario invalido)
        expect(mockApiPost).not.toHaveBeenCalledWith(
            expect.stringContaining('/readings'),
            expect.anything()
        )
    })

    it('CalibrationReadingsPage: mostra erro quando reference_value esta ausente', async () => {
        const user = userEvent.setup()
        render(<CalibrationReadingsPage />)

        expect(await screen.findByText(/Leituras de Calibração/i)).toBeInTheDocument()

        // Clica em Salvar com reference_value vazio
        const salvarBtn = await screen.findByText(/Salvar Leituras/i)
        await user.click(salvarBtn)

        // Deve mostrar mensagem de erro inline OU toast de validacao
        await waitFor(() => {
            const hasInlineError = screen.queryByText(/Valor referência é obrigatório/i)
            const hasToastError = toastError.mock.calls.some(
                (call: string[]) => call[0]?.includes?.('Corrija os erros')
            )
            expect(hasInlineError || hasToastError).toBeTruthy()
        })
    })

    it('CertificateTemplatesPage: renderiza botao Excluir para cada template', async () => {
        render(<CertificateTemplatesPage />)

        // Aguarda os templates carregarem
        expect(await screen.findByText('Template RBC')).toBeInTheDocument()
        expect(screen.getByText('Template Simples')).toBeInTheDocument()

        // Deve ter botoes Excluir (um para cada template)
        const deleteButtons = screen.getAllByRole('button', { name: /Excluir/i })
        expect(deleteButtons.length).toBeGreaterThanOrEqual(2)
    })

    it('CertificateTemplatesPage: pede confirmacao antes de excluir template nao-padrao', async () => {
        const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(false)
        const user = userEvent.setup()
        render(<CertificateTemplatesPage />)

        // Aguarda os templates carregarem
        expect(await screen.findByText('Template Simples')).toBeInTheDocument()

        // Encontra os botoes Excluir e clica no do template nao-padrao
        const deleteButtons = screen.getAllByRole('button', { name: /Excluir/i })
        // Clica no segundo (Template Simples, nao-padrao)
        await user.click(deleteButtons[1])

        await waitFor(() => {
            expect(confirmSpy).toHaveBeenCalledWith(
                expect.stringContaining('Tem certeza que deseja excluir')
            )
        })

        // Como confirm retornou false, delete NAO deve ser chamado
        expect(mockApiDelete).not.toHaveBeenCalled()

        confirmSpy.mockRestore()
    })

    it('TechCertificatePage: possui botao de envio de email apos gerar certificado', async () => {
        // Mock da geracao do certificado
        mockApiPost.mockImplementation((url: string) => {
            if (url.includes('/generate-certificate')) {
                return Promise.resolve({
                    data: {
                        certificate_number: 'CERT-2026-001',
                        path: 'certificates/cert-001.pdf',
                        url: null,
                    },
                })
            }
            return Promise.resolve({ data: {} })
        })

        const user = userEvent.setup()
        render(<TechCertificatePage />)

        // Aguarda a pagina carregar
        expect(await screen.findByText(/Certificado de Calibração/i)).toBeInTheDocument()

        // Aguarda equipamento ser selecionado automaticamente (so 1 na OS)
        // e o botao Gerar aparecer
        const gerarBtn = await screen.findByRole('button', { name: /Gerar Certificado/i })
        expect(gerarBtn).toBeInTheDocument()

        // Clica para gerar certificado
        await user.click(gerarBtn)

        // Apos geracao, o campo de email deve aparecer
        await waitFor(() => {
            expect(screen.getByPlaceholderText(/E-mail do destinatário/i)).toBeInTheDocument()
        })

        // Deve ter botao Enviar (pode haver "Enviar por e-mail" como label e "Enviar" no botao)
        const enviarElements = screen.getAllByText(/Enviar/i)
        expect(enviarElements.length).toBeGreaterThanOrEqual(1)
    })

    it('CalibrationListPage: renderiza corretamente com dados de calibracao', async () => {
        render(<CalibrationListPage />)

        // Aguarda os dados renderizarem
        await waitFor(() => {
            expect(screen.getByText(/CERT-2026-001/i)).toBeInTheDocument()
        })

        // Verifica que equipamento e cliente estao visiveis (podem aparecer em multiplos contextos)
        expect(screen.getAllByText(/BAL-091/i).length).toBeGreaterThanOrEqual(1)
        expect(screen.getAllByText(/Cliente Demo/i).length).toBeGreaterThanOrEqual(1)
    })
})
