import type { ReactNode } from 'react'
import { act, renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useGenerateWhatsappLink, useInmetroOwner, useInmetroCompetitors } from '@/hooks/useInmetro'
import { useMarketShareTimeline } from '@/hooks/useInmetroAdvanced'

const { mockApiGet, mockApiPost } = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
    mockApiPost: vi.fn(),
}))

vi.mock('sonner', () => ({
    toast: {
        success: vi.fn(),
        error: vi.fn(),
        warning: vi.fn(),
    },
}))

vi.mock('@/lib/api', () => ({
    default: {
        get: mockApiGet,
        post: mockApiPost,
    },
}))

function createWrapper() {
    const queryClient = new QueryClient({
        defaultOptions: {
            queries: {
                retry: false,
            },
            mutations: {
                retry: false,
            },
        },
    })

    return function Wrapper({ children }: { children: ReactNode }) {
        return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
    }
}

describe('Inmetro hooks contract', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('desenvelopa owner e paginação de concorrentes vindos do Laravel', async () => {
        mockApiGet
            .mockResolvedValueOnce({
                data: {
                    data: {
                        id: 7,
                        document: '12345678900',
                        name: 'Cliente INMETRO',
                        trade_name: null,
                        type: 'PJ',
                        phone: null,
                        phone2: null,
                        email: null,
                        contact_source: null,
                        contact_enriched_at: null,
                        lead_status: 'new',
                        priority: 'high',
                        converted_to_customer_id: null,
                        notes: null,
                        created_at: '2026-03-20T10:00:00Z',
                    },
                },
            })
            .mockResolvedValueOnce({
                data: {
                    data: {
                        data: [
                            {
                                id: 11,
                                name: 'Oficina Alfa',
                                cnpj: null,
                                authorization_number: null,
                                phone: null,
                                email: null,
                                address: null,
                                city: 'Cuiabá',
                                state: 'MT',
                                authorized_species: null,
                                mechanics: null,
                                max_capacity: null,
                                accuracy_classes: null,
                                authorization_valid_until: null,
                                total_repairs: 0,
                            },
                        ],
                        current_page: 1,
                        last_page: 3,
                        total: 21,
                        from: 1,
                        to: 1,
                    },
                },
            })

        const owner = renderHook(() => useInmetroOwner(7), { wrapper: createWrapper() })
        const competitors = renderHook(() => useInmetroCompetitors({ page: 1, per_page: 25 }), { wrapper: createWrapper() })

        await waitFor(() => expect(owner.result.current.isSuccess).toBe(true))
        await waitFor(() => expect(competitors.result.current.isSuccess).toBe(true))

        expect(owner.result.current.data).toMatchObject({
            id: 7,
            name: 'Cliente INMETRO',
            document: '12345678900',
        })
        expect(competitors.result.current.data).toMatchObject({
            total: 21,
            last_page: 3,
        })
        expect(competitors.result.current.data?.data).toEqual([
            expect.objectContaining({
                id: 11,
                name: 'Oficina Alfa',
                city: 'Cuiabá',
            }),
        ])
    })

    it('desenvelopa mutations e hooks advanced que respondem com ApiResponse::data', async () => {
        mockApiPost.mockResolvedValueOnce({
            data: {
                data: {
                    whatsapp_link: 'https://wa.me/5565999990000',
                    phone: '5565999990000',
                    owner_name: 'Cliente INMETRO',
                },
            },
        })
        mockApiGet.mockResolvedValueOnce({
            data: {
                data: {
                    current_share: 55,
                    snapshots: [
                        {
                            period: 'Mar/2026',
                            data: {
                                our_share: 55,
                                total_instruments: 90,
                            },
                        },
                    ],
                },
            },
        })

        const whatsapp = renderHook(() => useGenerateWhatsappLink(), { wrapper: createWrapper() })
        const timeline = renderHook(() => useMarketShareTimeline(), { wrapper: createWrapper() })

        let mutationResult:
            | {
                whatsapp_link: string
                phone: string
                owner_name: string
            }
            | undefined

        await act(async () => {
            mutationResult = await whatsapp.result.current.mutateAsync({ ownerId: 7 })
        })

        await waitFor(() => expect(timeline.result.current.isSuccess).toBe(true))

        expect(mutationResult).toEqual({
            whatsapp_link: 'https://wa.me/5565999990000',
            phone: '5565999990000',
            owner_name: 'Cliente INMETRO',
        })
        expect(timeline.result.current.data).toMatchObject({
            current_share: 55,
            snapshots: [
                expect.objectContaining({
                    period: 'Mar/2026',
                }),
            ],
        })
    })
})
