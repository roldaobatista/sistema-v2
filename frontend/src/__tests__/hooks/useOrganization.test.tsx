import { beforeEach, describe, expect, it, vi } from 'vitest'
import { QueryClientProvider } from '@tanstack/react-query'
import { act, renderHook, waitFor, createTestQueryClient } from '@/__tests__/test-utils'
import { useOrganization } from '@/hooks/useOrganization'
import { toast } from 'sonner'

const { mockApiGet, mockApiPost } = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
    mockApiPost: vi.fn(),
}))

vi.mock('@/lib/api', async () => {
    const actual = await vi.importActual<typeof import('@/lib/api')>('@/lib/api')
    return {
        ...actual,
        default: {
            get: mockApiGet,
            post: mockApiPost,
            put: vi.fn(),
            delete: vi.fn(),
        },
    }
})

vi.mock('sonner', () => ({
    toast: {
        success: vi.fn(),
        error: vi.fn(),
    },
}))

describe('useOrganization', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockApiPost.mockReset()
        mockApiGet.mockImplementation((url: string) => {
            if (url === '/hr/departments') {
                return Promise.resolve({ data: { data: [{ id: 1, name: 'Operações' }] } })
            }

            if (url === '/hr/org-chart') {
                return Promise.resolve({ data: { data: [{ id: 2, name: 'Diretoria' }] } })
            }

            if (url === '/hr/positions') {
                return Promise.resolve({ data: { data: [{ id: 3, name: 'Analista' }] } })
            }

            return Promise.resolve({ data: { data: [] } })
        })
    })

    it('normaliza responses envelopadas dos endpoints de RH', async () => {
        const queryClient = createTestQueryClient()

        const wrapper = ({ children }: { children: React.ReactNode }) => (
            <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
        )

        const { result } = renderHook(() => useOrganization(), { wrapper })

        await waitFor(() => {
            expect(result.current.departments).toEqual([{ id: 1, name: 'Operações' }])
            expect(result.current.orgChart).toEqual([{ id: 2, name: 'Diretoria' }])
            expect(result.current.positions).toEqual([{ id: 3, name: 'Analista' }])
        })
    })

    it('propaga o primeiro erro de validacao ao criar departamento', async () => {
        const queryClient = createTestQueryClient()

        const wrapper = ({ children }: { children: React.ReactNode }) => (
            <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
        )

        mockApiPost.mockRejectedValue({
            isAxiosError: true,
            response: {
                data: {
                    message: 'Validation failed.',
                    errors: {
                        name: ['Nome do departamento obrigatorio'],
                    },
                },
            },
        })

        const { result } = renderHook(() => useOrganization(), { wrapper })

        await waitFor(() => {
            expect(result.current.loadingDepts).toBe(false)
        })

        await act(async () => {
            result.current.createDept.mutate({ name: '' })
        })

        await waitFor(() => {
            expect(toast.error).toHaveBeenCalledWith('Nome do departamento obrigatorio')
        })
    })
})
