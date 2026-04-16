import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { renderHook, act, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import React from 'react'
import { toast } from 'sonner'

vi.mock('@/lib/api', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}))

vi.mock('sonner', () => ({
    toast: {
        success: vi.fn(),
        error: vi.fn(),
    },
}))

import { useSkills } from '@/hooks/useSkills'
import api from '@/lib/api'

const mockGet = vi.mocked(api.get)
const mockPost = vi.mocked(api.post)

function createWrapper() {
    const queryClient = new QueryClient({
        defaultOptions: { queries: { retry: false, gcTime: 0 } },
    })

    return ({ children }: { children: React.ReactNode }) =>
        React.createElement(QueryClientProvider, { client: queryClient }, children)
}

describe('useSkills', () => {
    beforeEach(() => {
        mockGet.mockReset()
        mockPost.mockReset()
    })

    afterEach(() => {
        vi.restoreAllMocks()
    })

    it('usa a mensagem de validacao da API ao criar competencia', async () => {
        mockGet.mockResolvedValue({ data: { data: [] } })
        mockPost.mockRejectedValue({
            isAxiosError: true,
            response: {
                data: {
                    errors: {
                        name: ['Competencia obrigatoria'],
                    },
                },
            },
        })

        const { result } = renderHook(() => useSkills(), { wrapper: createWrapper() })

        await waitFor(() => {
            expect(result.current.loadingSkills).toBe(false)
            expect(result.current.loadingMatrix).toBe(false)
        })

        await act(async () => {
            result.current.createSkill.mutate({ name: '' })
        })

        await waitFor(() => {
            expect(toast.error).toHaveBeenCalledWith('Competencia obrigatoria')
        })
    })

    it('desembrulha envelopes Laravel e normaliza a matriz de skills', async () => {
        mockGet.mockResolvedValueOnce({
            data: {
                data: [{ id: 10, name: 'React', category: 'Tecnica' }],
            },
        })
        mockGet.mockResolvedValueOnce({
            data: {
                data: [{
                    id: 3,
                    name: 'Ana',
                    position: 'Desenvolvedora',
                    skills: [{ skill_id: 10, current: 4, assessed_at: '2026-03-20T00:00:00Z' }],
                }],
            },
        })

        const { result } = renderHook(() => useSkills(), { wrapper: createWrapper() })

        await waitFor(() => {
            expect(result.current.loadingSkills).toBe(false)
            expect(result.current.loadingMatrix).toBe(false)
        })

        expect(result.current.skills).toEqual([{ id: 10, name: 'React', category: 'Tecnica' }])
        expect(result.current.matrix).toEqual([{
            id: 3,
            name: 'Ana',
            position: { name: 'Desenvolvedora' },
            skills: [{ skill_id: 10, current_level: 4, assessed_at: '2026-03-20T00:00:00Z' }],
        }])
    })

    it('envia uma avaliacao por skill no contrato aceito pelo backend', async () => {
        mockGet.mockResolvedValue({ data: { data: [] } })
        mockPost.mockResolvedValue({ data: { data: { id: 1 } } })

        const { result } = renderHook(() => useSkills(), { wrapper: createWrapper() })

        await waitFor(() => {
            expect(result.current.loadingSkills).toBe(false)
            expect(result.current.loadingMatrix).toBe(false)
        })

        await act(async () => {
            result.current.assessUser.mutate({
                userId: 9,
                skills: [
                    { skill_id: 10, level: 3 },
                    { skill_id: 12, level: 5 },
                ],
            })
        })

        await waitFor(() => {
            expect(mockPost).toHaveBeenCalledTimes(2)
        })

        expect(mockPost).toHaveBeenNthCalledWith(1, '/hr/skills/assess/9', { skill_id: 10, level: 3 })
        expect(mockPost).toHaveBeenNthCalledWith(2, '/hr/skills/assess/9', { skill_id: 12, level: 5 })
    })
})
