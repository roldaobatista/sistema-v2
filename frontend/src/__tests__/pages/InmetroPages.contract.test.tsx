import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'

import { render, screen, waitFor } from '@/__tests__/test-utils'
import { InmetroImportPage } from '@/pages/inmetro/InmetroImportPage'
import { InmetroMapPage } from '@/pages/inmetro/InmetroMapPage'
import { InmetroLeadsPage } from '@/pages/inmetro/InmetroLeadsPage'
import { InmetroCompetitorsPage } from '@/pages/inmetro/InmetroCompetitorsPage'

const {
    mockToastSuccess,
    mockToastError,
    mockToastWarning,
    mockHasPermission,
    mockUseImportXml,
    mockUseSubmitPsieResults,
    mockUseInmetroConfig,
    mockUseUpdateInmetroConfig,
    mockUseInstrumentTypes,
    mockUseInmetroLeads,
    mockUseEnrichOwner,
    mockUseConvertToCustomer,
    mockUseEnrichBatch,
    mockUseDeleteOwner,
    mockUseConversionStats,
    mockUseCrossReference,
    mockUseCrossReferenceStats,
    mockUseExportLeadsPdf,
    mockUseInmetroAutoSync,
    mockUseMapData,
    mockUseGeocodeLocations,
    mockUseCalculateDistances,
    mockUseInmetroCompetitors,
    mockOwnerEditModal,
} = vi.hoisted(() => ({
    mockToastSuccess: vi.fn(),
    mockToastError: vi.fn(),
    mockToastWarning: vi.fn(),
    mockHasPermission: vi.fn(),
    mockUseImportXml: vi.fn(),
    mockUseSubmitPsieResults: vi.fn(),
    mockUseInmetroConfig: vi.fn(),
    mockUseUpdateInmetroConfig: vi.fn(),
    mockUseInstrumentTypes: vi.fn(),
    mockUseInmetroLeads: vi.fn(),
    mockUseEnrichOwner: vi.fn(),
    mockUseConvertToCustomer: vi.fn(),
    mockUseEnrichBatch: vi.fn(),
    mockUseDeleteOwner: vi.fn(),
    mockUseConversionStats: vi.fn(),
    mockUseCrossReference: vi.fn(),
    mockUseCrossReferenceStats: vi.fn(),
    mockUseExportLeadsPdf: vi.fn(),
    mockUseInmetroAutoSync: vi.fn(),
    mockUseMapData: vi.fn(),
    mockUseGeocodeLocations: vi.fn(),
    mockUseCalculateDistances: vi.fn(),
    mockUseInmetroCompetitors: vi.fn(),
    mockOwnerEditModal: vi.fn(),
}))

vi.mock('sonner', () => ({
    toast: {
        success: mockToastSuccess,
        error: mockToastError,
        warning: mockToastWarning,
    },
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        hasPermission: mockHasPermission,
    }),
}))

vi.mock('@/components/inmetro/InmetroBaseConfigSection', () => ({
    InmetroBaseConfigSection: () => <div data-testid="inmetro-base-config-section" />,
}))

vi.mock('@/pages/inmetro/InmetroOwnerEditModal', () => ({
    InmetroOwnerEditModal: (props: unknown) => {
        mockOwnerEditModal(props)
        return null
    },
}))

vi.mock('@/pages/inmetro/InmetroStatusUpdateModal', () => ({
    InmetroStatusUpdateModal: () => null,
}))

vi.mock('@/components/ui/modal', () => ({
    Modal: ({ children }: { children?: React.ReactNode }) => <div>{children}</div>,
}))

vi.mock('@/lib/api', () => ({
    default: {
        get: vi.fn(),
    },
    getApiErrorMessage: (err: unknown, fallback: string) =>
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? fallback,
}))

vi.mock('@/hooks/useInmetro', async () => {
    const actual = await vi.importActual<typeof import('@/hooks/useInmetro')>('@/hooks/useInmetro')

    return {
        ...actual,
        useImportXml: (...args: unknown[]) => mockUseImportXml(...args),
        useSubmitPsieResults: (...args: unknown[]) => mockUseSubmitPsieResults(...args),
        useInmetroConfig: (...args: unknown[]) => mockUseInmetroConfig(...args),
        useUpdateInmetroConfig: (...args: unknown[]) => mockUseUpdateInmetroConfig(...args),
        useInstrumentTypes: (...args: unknown[]) => mockUseInstrumentTypes(...args),
        useInmetroLeads: (...args: unknown[]) => mockUseInmetroLeads(...args),
        useEnrichOwner: (...args: unknown[]) => mockUseEnrichOwner(...args),
        useConvertToCustomer: (...args: unknown[]) => mockUseConvertToCustomer(...args),
        useEnrichBatch: (...args: unknown[]) => mockUseEnrichBatch(...args),
        useDeleteOwner: (...args: unknown[]) => mockUseDeleteOwner(...args),
        useConversionStats: (...args: unknown[]) => mockUseConversionStats(...args),
        useCrossReference: (...args: unknown[]) => mockUseCrossReference(...args),
        useCrossReferenceStats: (...args: unknown[]) => mockUseCrossReferenceStats(...args),
        useExportLeadsPdf: (...args: unknown[]) => mockUseExportLeadsPdf(...args),
        useMapData: (...args: unknown[]) => mockUseMapData(...args),
        useGeocodeLocations: (...args: unknown[]) => mockUseGeocodeLocations(...args),
        useCalculateDistances: (...args: unknown[]) => mockUseCalculateDistances(...args),
        useInmetroCompetitors: (...args: unknown[]) => mockUseInmetroCompetitors(...args),
    }
})

vi.mock('@/hooks/useInmetroAutoSync', () => ({
    useInmetroAutoSync: (...args: unknown[]) => mockUseInmetroAutoSync(...args),
}))

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')

    return {
        ...actual,
        useSearchParams: () => [new URLSearchParams(), vi.fn()],
    }
})

vi.mock('react-leaflet', () => ({
    MapContainer: ({ children }: { children?: React.ReactNode }) => <div data-testid="map-container">{children}</div>,
    TileLayer: () => null,
    Marker: ({ children }: { children?: React.ReactNode }) => <div>{children}</div>,
    Popup: ({ children }: { children?: React.ReactNode }) => <div>{children}</div>,
    useMap: () => ({
        fitBounds: vi.fn(),
    }),
}))

describe('Inmetro pages contract', () => {
    beforeEach(() => {
        vi.clearAllMocks()

        mockHasPermission.mockReturnValue(true)

        mockUseImportXml.mockReturnValue({
            mutate: vi.fn(),
            isPending: false,
            isSuccess: false,
        })

        mockUseSubmitPsieResults.mockReturnValue({
            mutate: vi.fn(),
            isPending: false,
        })

        mockUseInmetroConfig.mockReturnValue({
            data: {
                monitored_ufs: ['MT'],
                instrument_types: ['balanca'],
                auto_sync_enabled: true,
                sync_interval_days: 7,
            },
            isLoading: false,
        })

        mockUseUpdateInmetroConfig.mockReturnValue({
            mutate: vi.fn(),
            isPending: false,
        })

        mockUseInstrumentTypes.mockReturnValue({
            data: [
                { slug: 'balanca', label: 'Balança' },
                { slug: 'taximetro', label: 'Taxímetro' },
            ],
        })

        mockUseInmetroLeads.mockReturnValue({
            data: {
                data: [
                    {
                        id: 101,
                        name: 'Cliente INMETRO',
                        document: '12345678900',
                        type: 'PJ',
                        city: 'Cuiabá',
                        state: 'MT',
                        lead_status: 'new',
                        priority: 'high',
                        contact_source: null,
                        phone: null,
                        phone2: null,
                        email: null,
                        trade_name: null,
                        notes: null,
                        contact_enriched_at: null,
                        converted_to_customer_id: null,
                        created_at: '2026-03-20T10:00:00Z',
                    },
                ],
                total: 1,
                current_page: 1,
                last_page: 1,
            },
            isLoading: false,
        })

        mockUseEnrichOwner.mockReturnValue({ mutate: vi.fn(), isPending: false })
        mockUseConvertToCustomer.mockReturnValue({ mutate: vi.fn(), isPending: false })
        mockUseEnrichBatch.mockReturnValue({ mutate: vi.fn(), isPending: false })
        mockUseDeleteOwner.mockReturnValue({ mutate: vi.fn(), isPending: false })
        mockUseConversionStats.mockReturnValue({ data: { converted: 3, pending: 1 } })
        mockUseCrossReference.mockReturnValue({ mutate: vi.fn(), isPending: false })
        mockUseCrossReferenceStats.mockReturnValue({ data: { linked: 2, total_owners: 3, linked_owners: 2, unlinked_owners: 1, link_percentage: 66 } })
        mockUseExportLeadsPdf.mockReturnValue({ mutate: vi.fn(), isPending: false })
        mockUseInmetroAutoSync.mockReturnValue({ isSyncing: false, triggerSync: vi.fn() })
        mockUseMapData.mockReturnValue({
            data: {
                data: {
                    markers: [],
                    total_geolocated: 0,
                    total_without_geo: 0,
                    by_city: {},
                },
            },
            isLoading: false,
            refetch: vi.fn(),
        })
        mockUseGeocodeLocations.mockReturnValue({ mutate: vi.fn(), isPending: false })
        mockUseCalculateDistances.mockReturnValue({ mutate: vi.fn(), isPending: false })
        mockUseInmetroCompetitors.mockReturnValue({
            data: {
                data: {
                    data: [
                        {
                            id: 1,
                            name: 'Oficina Alfa',
                            cnpj: '12.345.678/0001-99',
                            authorization_number: '12345',
                            phone: '(65) 99999-0000',
                            email: 'alfa@example.com',
                            address: 'Rua das Balanças, 10',
                            city: 'Cuiabá',
                            state: 'MT',
                            authorized_species: ['balanças'],
                            mechanics: ['João'],
                            max_capacity: '300kg',
                            accuracy_classes: ['III'],
                            authorization_valid_until: '2030-12-31',
                            total_repairs: 2,
                            repairs: [],
                        },
                    ],
                    total: 2,
                    current_page: 1,
                    last_page: 2,
                    from: 1,
                    to: 1,
                },
            },
            isLoading: false,
            isError: false,
            error: null,
        })
    })

    it('salva configuracao com monitored_ufs e usa payload envelopado ao importar xml', async () => {
        const user = userEvent.setup()
        const mutateConfig = vi.fn((_payload, options?: { onSuccess?: () => void }) => options?.onSuccess?.())
        const mutateImport = vi.fn((_payload, options?: { onSuccess?: (response: unknown) => void }) => {
            options?.onSuccess?.({
                data: {
                    data: {
                        results: {
                            instruments: {
                                results: {
                                    grand_totals: {
                                        instruments_created: 4,
                                        owners_created: 2,
                                    },
                                },
                            },
                        },
                    },
                },
            })
        })

        mockUseUpdateInmetroConfig.mockReturnValue({
            mutate: mutateConfig,
            isPending: false,
        })

        mockUseImportXml.mockReturnValue({
            mutate: mutateImport,
            isPending: false,
            isSuccess: false,
        })

        render(<InmetroImportPage />)

        await user.click(screen.getByRole('button', { name: /Centro-Oeste/i }))
        await user.click(screen.getByRole('button', { name: /^Salvar$/i }))

        expect(mutateConfig).toHaveBeenCalledWith(
            expect.objectContaining({
                monitored_ufs: expect.arrayContaining(['MT', 'DF', 'GO', 'MS']),
                instrument_types: ['balanca'],
                auto_sync_enabled: true,
                sync_interval_days: 7,
            }),
            expect.any(Object)
        )

        await user.click(screen.getByRole('button', { name: /Importar XML/i }))

        expect(mutateImport).toHaveBeenCalledWith(
            expect.objectContaining({
                type: 'all',
                uf: expect.arrayContaining(['MT', 'DF', 'GO', 'MS']),
            }),
            expect.any(Object)
        )

        expect(mockToastSuccess).toHaveBeenCalledWith('Instrumentos: 4 novos, 2 proprietários')
    })

    it('usa stats envelopado no enriquecimento em lote da lista de leads', async () => {
        const user = userEvent.setup()
        const mutateBatch = vi.fn((_ids, options?: { onSuccess?: (response: unknown) => void }) => {
            options?.onSuccess?.({
                data: {
                    data: {
                        stats: {
                            enriched: 2,
                            failed: 1,
                            skipped: 0,
                        },
                    },
                },
            })
        })

        mockUseEnrichBatch.mockReturnValue({ mutate: mutateBatch, isPending: false })

        render(<InmetroLeadsPage />)

        const checkboxes = await screen.findAllByRole('checkbox')
        await user.click(checkboxes[1])
        await user.click(screen.getByRole('button', { name: /Enriquecer 1 selecionados/i }))

        await waitFor(() => {
            expect(mutateBatch).toHaveBeenCalledWith([101], expect.any(Object))
        })

        expect(mockToastSuccess).toHaveBeenCalledWith('2 enriquecidos, 1 falhas, 0 ignorados')
    })

    it('normaliza owner ao abrir modal de edicao sem depender de suppression', async () => {
        const user = userEvent.setup()

        render(<InmetroLeadsPage />)

        const editButtons = await screen.findAllByTitle('Editar')
        await user.click(editButtons[0])

        await waitFor(() => {
            expect(mockOwnerEditModal).toHaveBeenLastCalledWith(expect.objectContaining({
                open: true,
                owner: {
                    id: 101,
                    name: 'Cliente INMETRO',
                    trade_name: undefined,
                    phone: undefined,
                    phone2: undefined,
                    email: undefined,
                    notes: undefined,
                },
            }))
        })
    })

    it('consome payload envelopado no mapa e corrige feedbacks de geocodificacao e distancias', async () => {
        const user = userEvent.setup()
        const mutateGeocode = vi.fn((_limit, options?: { onSuccess?: (response: unknown) => void }) => {
            options?.onSuccess?.({
                data: {
                    message: 'Geocoding concluido: 3 sucesso, 1 falhas',
                    data: {
                        stats: {
                            success: 3,
                            failed: 1,
                        },
                    },
                },
            })
        })
        const mutateDistances = vi.fn((_payload, options?: { onSuccess?: (response: unknown) => void }) => {
            options?.onSuccess?.({
                data: {
                    message: 'Distancias calculadas para 4 locais',
                    data: {
                        updated: 4,
                    },
                },
            })
        })

        mockUseMapData.mockReturnValue({
            data: {
                data: {
                    markers: [
                        {
                            id: 1,
                            lat: -15.6,
                            lng: -56.1,
                            owner_name: 'Lead Cuiaba',
                            owner_document: '123',
                            city: 'Cuiaba',
                            state: 'MT',
                            farm_name: null,
                            instrument_count: 2,
                            overdue: 1,
                            expiring_30d: 0,
                            is_customer: false,
                            distance_km: 12,
                            owner_priority: 'high',
                        },
                    ],
                    total_geolocated: 1,
                    total_without_geo: 4,
                    by_city: {
                        Cuiaba: {
                            count: 1,
                            instruments: 2,
                            overdue: 1,
                        },
                    },
                },
            },
            isLoading: false,
            refetch: vi.fn(),
        })
        mockUseGeocodeLocations.mockReturnValue({ mutate: mutateGeocode, isPending: false })
        mockUseCalculateDistances.mockReturnValue({ mutate: mutateDistances, isPending: false })

        render(<InmetroMapPage />)

        expect(screen.getByText('1 locais no mapa • 4 sem coordenadas')).toBeInTheDocument()
        expect(screen.getByRole('button', { name: /Geocodificar \(4\)/i })).toBeInTheDocument()
        expect(screen.getByText('Cuiaba')).toBeInTheDocument()

        await user.click(screen.getByRole('button', { name: /Geocodificar \(4\)/i }))
        await user.click(screen.getByRole('button', { name: /Calcular Distâncias/i }))

        expect(mutateGeocode).toHaveBeenCalledWith(50, expect.any(Object))
        expect(mutateDistances).toHaveBeenCalledWith({ base_lat: -15.78, base_lng: -47.93 }, expect.any(Object))
        expect(mockToastSuccess).toHaveBeenCalledWith('Geocoding concluido: 3 sucesso, 1 falhas')
        expect(mockToastSuccess).toHaveBeenCalledWith('Distancias calculadas para 4 locais')
    })

    it('renderiza concorrentes quando o hook ainda entrega paginação dentro de data.data', () => {
        render(<InmetroCompetitorsPage />)

        expect(screen.getByText('Oficina Alfa')).toBeInTheDocument()
        expect(screen.getByText('(2 registros)')).toBeInTheDocument()
        expect(screen.getByText('Cuiabá/MT — Rua das Balanças, 10')).toBeInTheDocument()
        expect(screen.getByRole('button', { name: '2' })).toBeInTheDocument()
    })
})
