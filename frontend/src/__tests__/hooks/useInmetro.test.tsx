import { QueryClientProvider } from '@tanstack/react-query'
import { describe, it, expect, vi, beforeEach } from 'vitest'

import { renderHook, waitFor, createTestQueryClient } from '@/__tests__/test-utils'
import {
    getInmetroResultsPayload,
    getInmetroStatsPayload,
    useCrossReferenceStats,
    useInmetroConfig,
    useInmetroDashboard,
    useInmetroLeads,
    useInstrumentTypes,
} from '@/hooks/useInmetro'

const { mockToast, mockGet, mockPost, mockPut, mockPatch, mockDelete } = vi.hoisted(() => ({
    mockToast: { error: vi.fn(), success: vi.fn() },
    mockGet: vi.fn(),
    mockPost: vi.fn(),
    mockPut: vi.fn(),
    mockPatch: vi.fn(),
    mockDelete: vi.fn(),
}))

vi.mock('sonner', () => ({ toast: mockToast }))

vi.mock('@/lib/api', () => ({
    default: {
        get: (...args: unknown[]) => mockGet(...args),
        post: (...args: unknown[]) => mockPost(...args),
        put: (...args: unknown[]) => mockPut(...args),
        patch: (...args: unknown[]) => mockPatch(...args),
        delete: (...args: unknown[]) => mockDelete(...args),
    },
}))

function createWrapper() {
    const queryClient = createTestQueryClient()

    return ({ children }: { children: React.ReactNode }) => (
        <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
    )
}

describe('useInmetro — payload envelopado', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('desenvelopa dashboard em data.data', async () => {
        mockGet.mockResolvedValueOnce({
            data: {
                data: {
                    totals: { owners: 10, instruments: 20, overdue: 1, expiring_30d: 2, expiring_60d: 3, expiring_90d: 4 },
                    leads: { new: 5, contacted: 2, negotiating: 1, converted: 1, lost: 0 },
                    by_city: [],
                    by_status: [],
                    by_brand: [],
                },
            },
        })

        const { result } = renderHook(() => useInmetroDashboard(), { wrapper: createWrapper() })

        await waitFor(() => {
            expect(result.current.isSuccess).toBe(true)
        })

        expect(result.current.data?.totals.owners).toBe(10)
        expect(mockGet).toHaveBeenCalledWith('/inmetro/dashboard')
    })

    it('desenvelopa leads paginados em data.data', async () => {
        mockGet.mockResolvedValueOnce({
            data: {
                data: {
                    data: [
                        {
                            id: 7,
                            name: 'ACME',
                            document: '123',
                            type: 'PJ',
                            lead_status: 'new',
                            priority: 'normal',
                            trade_name: null,
                            phone: null,
                            phone2: null,
                            email: null,
                            contact_source: null,
                            contact_enriched_at: null,
                            converted_to_customer_id: null,
                            notes: null,
                            created_at: '2026-01-01',
                        },
                    ],
                    total: 1,
                    current_page: 1,
                    last_page: 1,
                },
            },
        })

        const { result } = renderHook(() => useInmetroLeads({ page: 1 }), { wrapper: createWrapper() })

        await waitFor(() => {
            expect(result.current.isSuccess).toBe(true)
        })

        expect(result.current.data?.data).toHaveLength(1)
        expect(result.current.data?.data[0].id).toBe(7)
        expect(result.current.data?.total).toBe(1)
    })

    it('desenvelopa estatisticas auxiliares e configuracao em data.data', async () => {
        mockGet
            .mockResolvedValueOnce({
                data: {
                    data: {
                        linked: 12,
                        total_owners: 20,
                        linked_owners: 12,
                        unlinked_owners: 8,
                        link_percentage: 60,
                    },
                },
            })
            .mockResolvedValueOnce({
                data: {
                    data: [
                        { slug: 'balanca', label: 'Balança' },
                        { slug: 'bombas-medidoras', label: 'Bombas medidoras' },
                    ],
                },
            })
            .mockResolvedValueOnce({
                data: {
                    data: {
                        monitored_ufs: ['MT', 'GO'],
                        instrument_types: ['balanca'],
                        auto_sync_enabled: true,
                        sync_interval_days: 7,
                    },
                },
            })

        const crossRef = renderHook(() => useCrossReferenceStats(), { wrapper: createWrapper() })
        const instrumentTypes = renderHook(() => useInstrumentTypes(), { wrapper: createWrapper() })
        const config = renderHook(() => useInmetroConfig(), { wrapper: createWrapper() })

        await waitFor(() => {
            expect(crossRef.result.current.isSuccess).toBe(true)
            expect(instrumentTypes.result.current.isSuccess).toBe(true)
            expect(config.result.current.isSuccess).toBe(true)
        })

        expect(crossRef.result.current.data?.linked).toBe(12)
        expect(instrumentTypes.result.current.data?.[0].slug).toBe('balanca')
        expect(config.result.current.data?.monitored_ufs).toEqual(['MT', 'GO'])
    })

    it('desenvelopa results e stats reutilizados pelas paginas', () => {
        expect(
            getInmetroResultsPayload({
                data: {
                    results: {
                        instruments: {
                            stats: { instruments_created: 3 },
                        },
                    },
                },
            })
        ).toEqual({
            instruments: {
                stats: { instruments_created: 3 },
            },
        })

        expect(
            getInmetroStatsPayload({
                data: {
                    stats: {
                        enriched: 2,
                        failed: 1,
                    },
                },
            })
        ).toEqual({
            enriched: 2,
            failed: 1,
        })
    })
})
