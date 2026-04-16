import { QueryClientProvider } from '@tanstack/react-query'
import { act } from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { renderHook, waitFor, createTestQueryClient } from '@/__tests__/test-utils'
import { useInmetroAutoSync } from '@/hooks/useInmetroAutoSync'

const { mockToast, mockUseInmetroDashboard, mockImportMutate, mockUseImportXml } = vi.hoisted(() => ({
    mockToast: { success: vi.fn(), error: vi.fn() },
    mockUseInmetroDashboard: vi.fn(),
    mockImportMutate: vi.fn(),
    mockUseImportXml: vi.fn(),
}))

vi.mock('sonner', () => ({ toast: mockToast }))
vi.mock('@/hooks/useInmetro', () => ({
    getInmetroResultsPayload: <T,>(payload: { results?: T } | { data: { results?: T } } | null | undefined) => {
        if (!payload) {
            return undefined
        }

        return typeof payload === 'object' && 'data' in payload ? payload.data.results : payload.results
    },
    useInmetroDashboard: () => mockUseInmetroDashboard(),
    useImportXml: () => mockUseImportXml(),
}))

function createWrapper() {
    const queryClient = createTestQueryClient()

    return ({ children }: { children: React.ReactNode }) => (
        <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
    )
}

describe('useInmetroAutoSync', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        vi.unstubAllEnvs()
        localStorage.clear()
        mockUseInmetroDashboard.mockReturnValue({
            data: { totals: { owners: 1, instruments: 1 } },
            isLoading: false,
        })
        mockUseImportXml.mockReturnValue({
            mutate: mockImportMutate,
            isPending: false,
        })
    })

    it('monta mensagem a partir de payload envelopado em data.data.results', async () => {
        mockImportMutate.mockImplementation((_payload, options) => {
            options?.onSuccess?.({
                data: {
                    data: {
                        results: {
                            competitors: {
                                stats: { created: 2, updated: 1 },
                            },
                            instruments: {
                                stats: { instruments_created: 3, owners_created: 4 },
                            },
                        },
                    },
                },
            })
        })

        const { result } = renderHook(() => useInmetroAutoSync(), { wrapper: createWrapper() })

        act(() => {
            result.current.triggerSync()
        })

        await waitFor(() => {
            expect(mockImportMutate).toHaveBeenCalled()
        })

        expect(mockToast.success).toHaveBeenCalledWith(
            'Concorrentes: 2 novos, 1 atualizados | Instrumentos: 3 novos, Proprietários: 4 novos'
        )
    })

    it('nao dispara importacao automatica quando VITE_INMETRO_AUTO_SYNC esta desabilitado', async () => {
        vi.stubEnv('VITE_INMETRO_AUTO_SYNC', 'false')
        mockUseInmetroDashboard.mockReturnValue({
            data: { totals: { owners: 0, instruments: 0 } },
            isLoading: false,
        })

        renderHook(() => useInmetroAutoSync(), { wrapper: createWrapper() })

        await waitFor(() => {
            expect(mockImportMutate).not.toHaveBeenCalled()
        })
    })
})
