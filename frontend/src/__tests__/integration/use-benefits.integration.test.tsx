import type { PropsWithChildren } from 'react'
import { QueryClientProvider, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import { http, HttpResponse } from 'msw'

import api, { unwrapData } from '@/lib/api'
import { createTestQueryClient } from '../test-utils'
import { server } from '../mocks/server'

function apiPattern(path: string): RegExp {
    return new RegExp(`^http://(localhost|127\\.0\\.0\\.1):8000/api/v1${path.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`)
}

function BenefitsMutationHarness() {
    const queryClient = useQueryClient()
    const { data } = useQuery({
        queryKey: ['benefits-test'],
        queryFn: async () => {
            const response = await api.get('/hr/benefits')
            return unwrapData<{ id: string; provider: string }[]>(response) ?? []
        },
    })

    const createMutation = useMutation({
        mutationFn: async () => api.post('/hr/benefits', {
            user_id: '10',
            type: 'health',
            provider: 'Plano Azul',
            value: 350,
            employee_contribution: 50,
            start_date: '2026-03-10',
            is_active: true,
        }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['benefits-test'] })
        },
    })

    return (
        <div>
            <span data-testid="benefits-count">{data?.length ?? 0}</span>
            <button onClick={() => createMutation.mutate()} type="button">
                Criar benefício
            </button>
        </div>
    )
}

describe('Benefits mutation integration', () => {
    it('invalida a query e recarrega a lista após criar benefício', async () => {
        let listReads = 0
        let benefits = [{ id: '1', provider: 'Vale Teste' }]

        server.use(
            http.get(apiPattern('/hr/benefits'), () => {
                listReads += 1

                return HttpResponse.json({
                    data: benefits,
                    current_page: 1,
                    last_page: 1,
                    per_page: 15,
                    total: benefits.length,
                    from: benefits.length > 0 ? 1 : null,
                    to: benefits.length > 0 ? benefits.length : null,
                })
            }),
            http.post(apiPattern('/hr/benefits'), async () => {
                benefits = [...benefits, { id: '2', provider: 'Plano Azul' }]

                return HttpResponse.json({
                    data: benefits[1],
                }, { status: 201 })
            })
        )

        const user = userEvent.setup()
        const queryClient = createTestQueryClient()
        const wrapper = ({ children }: PropsWithChildren) => (
            <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
        )

        render(<BenefitsMutationHarness />, { wrapper })

        await waitFor(() => {
            expect(screen.getByTestId('benefits-count')).toHaveTextContent('1')
        })

        await user.click(screen.getByRole('button', { name: 'Criar benefício' }))

        await waitFor(() => {
            expect(screen.getByTestId('benefits-count')).toHaveTextContent('2')
        })

        expect(listReads).toBeGreaterThanOrEqual(2)
    })
})
