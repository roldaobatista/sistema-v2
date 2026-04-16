import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import TechCommissionsPage from '@/pages/tech/TechCommissionsPage'
import { CommissionDisputesTab } from '@/pages/financeiro/commissions/CommissionDisputesTab'
import { CommissionEventsTab } from '@/pages/financeiro/commissions/CommissionEventsTab'
import { CommissionRulesTab } from '@/pages/financeiro/commissions/CommissionRulesTab'
import { CommissionSettlementsTab } from '@/pages/financeiro/commissions/CommissionSettlementsTab'
import { CommissionGoalsTab } from '@/pages/financeiro/commissions/CommissionGoalsTab'

const {
    mockNavigate,
    mockApiGet,
    mockApiPost,
    mockApiPut,
    mockApiDelete,
    toastError,
    toastSuccess,
    mockPermissions,
    mockUser,
} = vi.hoisted(() => ({
    mockNavigate: vi.fn(),
    mockApiGet: vi.fn(),
    mockApiPost: vi.fn(),
    mockApiPut: vi.fn(),
    mockApiDelete: vi.fn(),
    toastError: vi.fn(),
    toastSuccess: vi.fn(),
    mockPermissions: new Set<string>([
        'commissions.dispute.create',
        'commissions.dispute.resolve',
    ]),
    mockUser: { id: 99, name: 'Tecnico Teste' },
}))

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
    return {
        ...actual,
        useNavigate: () => mockNavigate,
    }
})

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: (selector?: (state: {
        user: { id: number; name: string }
        hasPermission: (permission: string) => boolean
    }) => unknown) => {
        const state = {
            user: mockUser,
            hasPermission: (permission: string) => mockPermissions.has(permission),
        }
        return typeof selector === 'function' ? selector(state) : state
    },
}))

vi.mock('@/lib/cross-tab-sync', () => ({
    broadcastQueryInvalidation: vi.fn(),
}))

vi.mock('@/lib/api', async () => {
    const actual = await vi.importActual<typeof import('@/lib/api')>('@/lib/api')
    return {
        ...actual,
        default: {
            get: mockApiGet,
            post: mockApiPost,
            put: mockApiPut,
            delete: mockApiDelete,
        },
    }
})

vi.mock('sonner', () => ({
    toast: {
        error: toastError,
        success: toastSuccess,
    },
}))

describe('Auditoria do modulo de comissoes', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockPermissions.clear()
        mockPermissions.add('commissions.dispute.create')
        mockPermissions.add('commissions.dispute.resolve')
        mockApiGet.mockImplementation((url: string) => {
            if (url === '/my/commission-summary') {
                return Promise.resolve({ data: { data: { total_month: 150, pending: 50, paid: 100 } } })
            }

            if (url === '/my/commission-events') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 1,
                                notes: 'Comissao tecnica',
                                commission_amount: 150,
                                status: 'pending',
                                created_at: '2026-03-10T10:00:00Z',
                                work_order: { os_number: 'OS-100' },
                            },
                        ],
                    },
                })
            }

            if (url === '/my/commission-settlements') {
                return Promise.resolve({ data: { data: [] } })
            }

            if (url === '/my/commission-disputes') {
                return Promise.resolve({ data: { data: [] } })
            }

            if (url === '/commission-rules') {
                return Promise.resolve({ data: { data: [{ id: 10, name: 'Regra ativa', active: true }] } })
            }

            if (url === '/commission-events') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 201,
                                user_id: 99,
                                commission_amount: 150,
                                status: 'pending',
                                user: { id: 99, name: 'Tecnico Teste' },
                            },
                            {
                                id: 202,
                                user_id: 77,
                                commission_amount: 210,
                                status: 'approved',
                                user: { id: 77, name: 'Outro Usuario' },
                            },
                        ],
                    },
                })
            }

            if (url === '/commission-users') {
                return Promise.resolve({ data: { data: [{ id: 99, name: 'Tecnico Teste' }] } })
            }

            if (url === '/commission-settlements') {
                return Promise.resolve({
                    data: {
                        data: [{
                            id: 55,
                            user_id: 99,
                            period: '2026-03',
                            total_amount: 230,
                            paid_amount: null,
                            balance: 230,
                            events_count: 2,
                            status: 'approved',
                            user: { id: 99, name: 'Tecnico Teste' },
                            paid_at: null,
                            payment_notes: null,
                            rejection_reason: null,
                        }],
                    },
                })
            }

            if (url === '/commission-goals') {
                return Promise.resolve({
                    data: {
                        data: [{
                            id: 1,
                            user_id: 99,
                            user_name: 'Tecnico Teste',
                            period: '2026-03',
                            type: 'os_count',
                            target_amount: 10,
                            achieved_amount: 6,
                            achievement_pct: 60,
                            bonus_percentage: null,
                            bonus_amount: null,
                            notes: null,
                        }],
                    },
                })
            }

            if (url === '/commission-calculation-types') {
                return Promise.resolve({ data: { data: { percent_gross: '% do Bruto' } } })
            }

            return Promise.resolve({ data: { data: [] } })
        })
        mockApiPost.mockResolvedValue({ data: { data: { id: 1 } } })
        mockApiPut.mockResolvedValue({ data: { data: { id: 1 } } })
        mockApiDelete.mockResolvedValue({ data: null })
    })

    it('carrega a tela tecnica usando o endpoint pessoal de disputas', async () => {
        render(<TechCommissionsPage />)

        await screen.findByText('Comissoes')

        await waitFor(() => {
            expect(mockApiGet).toHaveBeenCalledWith('/my/commission-disputes', {
                params: undefined,
            })
        })

        expect(mockApiGet).not.toHaveBeenCalledWith('/commission-disputes', expect.anything())
    })

    it('trata resolved como legado visual e nao como status operacional novo', async () => {
        const user = userEvent.setup()

        mockApiGet.mockImplementation((url: string) => {
            if (url === '/my/commission-summary') {
                return Promise.resolve({ data: { data: { total_month: 150, pending: 50, paid: 100 } } })
            }

            if (url === '/my/commission-events') {
                return Promise.resolve({ data: { data: [] } })
            }

            if (url === '/my/commission-settlements') {
                return Promise.resolve({ data: { data: [] } })
            }

            if (url === '/my/commission-disputes') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 301,
                                reason: 'Contestacao legada ainda persistida.',
                                status: 'resolved',
                                created_at: '2026-03-10T10:00:00Z',
                                commission_event: {
                                    commission_amount: 150,
                                    work_order: { os_number: 'OS-301' },
                                },
                            },
                        ],
                    },
                })
            }

            if (url === '/commission-events') {
                return Promise.resolve({ data: { data: [] } })
            }

            if (url === '/commission-disputes') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                id: 401,
                                user_id: 99,
                                user: { id: 99, name: 'Tecnico Teste' },
                                reason: 'Contestacao legada vinda do banco.',
                                status: 'resolved',
                                resolution_notes: 'Resolvida antes do fluxo canônico atual.',
                                commission_event: {
                                    commission_amount: 180,
                                    work_order: { os_number: 'OS-401' },
                                },
                                created_at: '2026-03-11T10:00:00Z',
                            },
                        ],
                    },
                })
            }

            return Promise.resolve({ data: { data: [] } })
        })

        render(
            <>
                <TechCommissionsPage />
                <CommissionDisputesTab />
            </>
        )

        await screen.findByText('Comissoes')
        await user.click(screen.getByRole('button', { name: 'Disputas' }))
        expect(await screen.findAllByText(/Resolvida \(legado\)/i)).toHaveLength(2)
        expect(screen.queryByRole('button', { name: 'Aceitar' })).not.toBeInTheDocument()
        expect(screen.queryByRole('button', { name: 'Rejeitar' })).not.toBeInTheDocument()
    })

    it('alinha o resumo tecnico ao filtro de periodo selecionado', async () => {
        const user = userEvent.setup()

        render(<TechCommissionsPage />)

        await screen.findByText('Comissoes')

        await user.click(screen.getByRole('button', { name: 'Mes Anterior' }))

        await waitFor(() => {
            expect(mockApiGet).toHaveBeenCalledWith('/my/commission-summary', {
                params: expect.objectContaining({ period: expect.stringMatching(/^\d{4}-\d{2}$/) }),
            })
        })

        await user.click(screen.getByRole('button', { name: 'Tudo' }))

        await waitFor(() => {
            expect(mockApiGet).toHaveBeenCalledWith('/my/commission-summary', {
                params: { all: 1 },
            })
        })
    })

    it('envia source_filter no formato esperado pelo backend ao salvar regra', async () => {
        mockPermissions.add('commissions.rule.create')
        const user = userEvent.setup()

        render(<CommissionRulesTab />)

        await user.click(await screen.findByRole('button', { name: /Nova Regra/i }))
        await user.type(screen.getByLabelText('Nome da Regra'), 'Regra por Origem')
        await user.selectOptions(screen.getByLabelText('Tipo de Calculo'), 'percent_gross')
        await user.type(screen.getByLabelText('Valor / Percentual'), '12')
        await user.clear(screen.getByLabelText('Prioridade (Maior = Executa Primeiro)'))
        await user.type(screen.getByLabelText('Prioridade (Maior = Executa Primeiro)'), '2')
        await user.type(screen.getByLabelText('Filtro de Origem (Opcional)'), 'site')

        await user.click(screen.getByRole('button', { name: 'Salvar Regra' }))

        await waitFor(() => {
            expect(mockApiPost).toHaveBeenCalledWith('/commission-rules', expect.objectContaining({
                source_filter: 'site',
                applies_to: 'all',
                applies_when: 'os_completed',
            }))
        })
    }, 10000)

    it('registra pagamento integral sem enviar paid_amount manual', async () => {
        mockPermissions.add('commissions.settlement.update')
        const user = userEvent.setup()

        render(<CommissionSettlementsTab />)

        await user.click(await screen.findByRole('button', { name: /^Pagar$/i }))
        await user.click(screen.getByRole('button', { name: /Confirmar pagamento integral/i }))

        await waitFor(() => {
            expect(mockApiPost).toHaveBeenCalledWith('/commission-settlements/55/pay', {
                payment_notes: '',
            })
        })
    })

    it('trata pending_approval como alias legado de fechado nas acoes de settlement', async () => {
        mockPermissions.clear()
        mockPermissions.add('commissions.settlement.update')
        mockPermissions.add('commissions.settlement.approve')

        mockApiGet.mockImplementation((url: string) => {
            if (url === '/commission-settlements') {
                return Promise.resolve({
                    data: {
                        data: [{
                            id: 88,
                            user_id: 99,
                            period: '2026-03',
                            total_amount: 230,
                            paid_amount: null,
                            balance: 230,
                            events_count: 2,
                            status: 'pending_approval',
                            user: { id: 99, name: 'Tecnico Teste' },
                            paid_at: null,
                            payment_notes: null,
                            rejection_reason: null,
                        }],
                    },
                })
            }

            if (url === '/commission-users') {
                return Promise.resolve({ data: { data: [{ id: 99, name: 'Tecnico Teste' }] } })
            }

            if (url === '/commission-settlements/balance-summary') {
                return Promise.resolve({ data: { data: null } })
            }

            return Promise.resolve({ data: { data: [] } })
        })

        render(<CommissionSettlementsTab />)

        await screen.findByText('Fechamentos realizados')
        expect(await screen.findByText(/Aguard\. aprovacao \(legado\)/i)).toBeInTheDocument()
        expect(screen.getByRole('button', { name: /Aprovar/i })).toBeInTheDocument()
        expect(screen.getByRole('button', { name: /^Pagar$/i })).toBeInTheDocument()
        expect(screen.getByRole('button', { name: /^Reabrir$/i })).toBeInTheDocument()
    })

    it('oculta fechamento de periodo quando usuario nao pode criar fechamento', async () => {
        render(<CommissionSettlementsTab />)

        await screen.findByText('Fechamentos realizados')

        expect(screen.queryByText('Fechar periodo')).not.toBeInTheDocument()
        expect(screen.queryByRole('button', { name: /Gerar comissoes em lote/i })).not.toBeInTheDocument()
    })

    it('habilita acoes de pagamento e reabertura com a permissao correta de settlement.update', async () => {
        mockPermissions.clear()
        mockPermissions.add('commissions.settlement.update')

        render(<CommissionSettlementsTab />)

        await screen.findByText('Fechamentos realizados')

        expect(await screen.findByRole('button', { name: /^Pagar$/i })).toBeInTheDocument()
        expect(screen.getByRole('button', { name: /^Reabrir$/i })).toBeInTheDocument()
        expect(screen.queryByText('Fechar periodo')).not.toBeInTheDocument()
    })

    it('nao exibe acoes de pagamento e reabertura quando o usuario possui apenas create de settlement', async () => {
        mockPermissions.clear()
        mockPermissions.add('commissions.settlement.create')

        render(<CommissionSettlementsTab />)

        await screen.findByText('Fechamentos realizados')

        expect(screen.getByRole('button', { name: /^Fechar periodo$/i })).toBeInTheDocument()
        expect(screen.queryByRole('button', { name: /^Pagar$/i })).not.toBeInTheDocument()
        expect(screen.queryByRole('button', { name: /^Reabrir$/i })).not.toBeInTheDocument()
    })

    it('mostra gerar comissoes em lote pela permissao real de regra', async () => {
        mockPermissions.clear()
        mockPermissions.add('commissions.rule.create')

        render(<CommissionSettlementsTab />)

        await screen.findByText('Fechamentos realizados')

        expect(screen.getByRole('button', { name: /Gerar comissoes em lote/i })).toBeInTheDocument()
        expect(screen.queryByText('Fechar periodo')).not.toBeInTheDocument()
    })

    it('usa endpoint proprio de beneficiarios de comissao sem depender de /users', async () => {
        mockPermissions.add('commissions.rule.create')

        render(<CommissionRulesTab />)

        await screen.findByText('Regras de Comissao')

        await waitFor(() => {
            expect(mockApiGet).toHaveBeenCalledWith('/commission-users')
        })

        expect(mockApiGet).not.toHaveBeenCalledWith('/users')
    })

    it('exibe metas de contagem sem formatar como moeda', async () => {
        render(<CommissionGoalsTab />)

        expect(await screen.findByText('Alcancado: 6')).toBeInTheDocument()
        expect(screen.getByText('Meta: 10')).toBeInTheDocument()
    })

    it('restringe eventos de terceiros no modal de disputa quando usuario nao pode resolver', async () => {
        mockPermissions.delete('commissions.dispute.resolve')
        const user = userEvent.setup()

        render(<CommissionDisputesTab />)

        await user.click(await screen.findByRole('button', { name: /Nova Contest/i }))

        expect(await screen.findByRole('option', { name: /#201/ })).toBeInTheDocument()
        expect(screen.queryByRole('option', { name: /#202/ })).not.toBeInTheDocument()
    })

    it('habilita acoes de eventos com a permissao correta do recurso de evento', async () => {
        mockPermissions.clear()
        mockPermissions.add('commissions.event.update')

        render(<CommissionEventsTab />)

        expect(await screen.findByText('Eventos de Comissão')).toBeInTheDocument()

        await waitFor(() => {
            expect(mockApiGet).toHaveBeenCalledWith('/commission-events', {
                params: { page: 1, per_page: 50 },
            })
        })

        expect(screen.getByText('Ações')).toBeInTheDocument()
    })

    it('nao habilita acoes de eventos quando o usuario possui apenas a permissao de regra', async () => {
        mockPermissions.clear()
        mockPermissions.add('commissions.rule.update')

        render(<CommissionEventsTab />)

        await screen.findByText('Eventos de Comissão')

        expect(screen.queryByText('Ações')).not.toBeInTheDocument()
        expect(screen.queryByLabelText('Selecionar evento 201')).not.toBeInTheDocument()
    })
})
