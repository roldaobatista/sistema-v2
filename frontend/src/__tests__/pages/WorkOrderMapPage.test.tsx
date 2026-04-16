import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import { WorkOrderMapPage } from '@/pages/os/WorkOrderMapPage'

const {
    mockList,
    mockHasPermission,
    mockNavigate,
} = vi.hoisted(() => ({
    mockList: vi.fn(),
    mockHasPermission: vi.fn(),
    mockNavigate: vi.fn(),
}))

vi.mock('react-leaflet', () => ({
    MapContainer: ({ children }: { children: React.ReactNode }) => <div data-testid="map-container">{children}</div>,
    TileLayer: () => <div data-testid="tile-layer" />,
    Marker: ({ children }: { children?: React.ReactNode }) => <div data-testid="marker">{children}</div>,
    Popup: ({ children }: { children?: React.ReactNode }) => <div>{children}</div>,
    useMap: () => ({
        fitBounds: vi.fn(),
        flyTo: vi.fn(),
    }),
}))

vi.mock('leaflet', () => ({
    default: {
        latLngBounds: () => ({
            isValid: () => true,
        }),
        divIcon: vi.fn(() => ({})),
    },
}))

vi.mock('@/lib/work-order-api', () => ({
    workOrderApi: {
        list: mockList,
    },
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        hasPermission: mockHasPermission,
    }),
}))

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
    return {
        ...actual,
        useNavigate: () => mockNavigate,
    }
})

describe('WorkOrderMapPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockHasPermission.mockReturnValue(true)
        mockList.mockResolvedValue({
            data: {
                data: {
                    data: [
                        {
                            id: 1,
                            number: 'OS-001',
                            status: 'open',
                            priority: 'urgent',
                            description: 'Visita tecnica',
                            total: '0',
                            created_at: '2026-03-13T10:00:00Z',
                            customer: {
                                id: 10,
                                name: 'Cliente Alfa',
                                latitude: -16.46,
                                longitude: -54.63,
                                address_city: 'Rondonopolis',
                                address_state: 'MT',
                            },
                        },
                        {
                            id: 2,
                            number: 'OS-002',
                            status: 'in_service',
                            priority: 'normal',
                            description: 'Atendimento em campo',
                            total: '0',
                            created_at: '2026-03-13T11:00:00Z',
                            customer: {
                                id: 11,
                                name: 'Cliente Beta',
                            },
                        },
                    ],
                },
            },
        })
    })

    it('bloqueia acesso sem permissao de visualizacao', () => {
        mockHasPermission.mockReturnValue(false)

        render(<WorkOrderMapPage />)

        expect(screen.getByText(/nao possui permissao para visualizar o mapa de ordens de servico/i)).toBeInTheDocument()
        expect(screen.queryByTestId('map-container')).not.toBeInTheDocument()
    })

    it('renderiza o mapa operacional e separa OS sem coordenadas', async () => {
        render(<WorkOrderMapPage />)

        await waitFor(() => {
            expect(screen.getByText(/os geolocalizadas \(1\)/i)).toBeInTheDocument()
        })

        expect(screen.getByText(/os sem coordenadas \(1\)/i)).toBeInTheDocument()
        expect(screen.getAllByText('Cliente Alfa').length).toBeGreaterThan(0)
        expect(screen.getByText('Cliente Beta')).toBeInTheDocument()
        expect(screen.getByTestId('map-container')).toBeInTheDocument()
        expect(screen.getByText(/urgentes/i)).toBeInTheDocument()
    })
})
