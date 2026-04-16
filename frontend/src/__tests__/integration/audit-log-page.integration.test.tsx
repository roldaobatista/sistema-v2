import { describe, expect, it } from 'vitest'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'

import { render, screen, waitFor } from '../test-utils'
import { server } from '../mocks/server'
import { AuditLogPage } from '@/pages/admin/AuditLogPage'

function apiPattern(path: string): RegExp {
    const escapedPath = path.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
    return new RegExp(`http://(localhost|127\\.0\\.0\\.1):8000/api/v1${escapedPath}(\\?.*)?$`)
}

describe('AuditLogPage integration', () => {
    it('renderiza dados envelopados, pagina e abre o diff detalhado', async () => {
        server.use(
            http.get(apiPattern('/audit-logs/actions'), () =>
                HttpResponse.json({ data: ['created', 'updated'] })
            ),
            http.get(apiPattern('/audit-logs/entity-types'), () =>
                HttpResponse.json({
                    data: [{ value: 'App\\Models\\WorkOrder', label: 'WorkOrder' }],
                })
            ),
            http.get(apiPattern('/audit-logs'), ({ request }) => {
                const page = Number(new URL(request.url).searchParams.get('page') ?? '1')
                const entries = page === 1
                    ? [
                        {
                            id: 101,
                            action: 'created',
                            auditable_type: 'App\\Models\\Customer',
                            auditable_id: 8,
                            description: 'Cliente criado',
                            old_values: null,
                            new_values: null,
                            ip_address: '127.0.0.1',
                            user: { id: 1, name: 'Ana' },
                            created_at: '2026-03-10T10:00:00.000Z',
                        },
                    ]
                    : [
                        {
                            id: 202,
                            action: 'updated',
                            auditable_type: 'App\\Models\\WorkOrder',
                            auditable_id: 33,
                            description: 'OS atualizada',
                            old_values: { status: 'open' },
                            new_values: { status: 'completed' },
                            ip_address: '127.0.0.1',
                            user: { id: 2, name: 'Bruno' },
                            created_at: '2026-03-10T11:00:00.000Z',
                        },
                    ]

                return HttpResponse.json({
                    data: entries,
                    current_page: page,
                    last_page: 2,
                    per_page: 20,
                    total: 2,
                    from: page,
                    to: page,
                })
            }),
            http.get(/http:\/\/(localhost|127\.0\.0\.1):8000\/api\/v1\/audit-logs\/\d+(\?.*)?$/, ({ request }) =>
                HttpResponse.json({
                    data: {
                        id: Number(request.url.split('/').pop()),
                        description: 'OS atualizada',
                    },
                    diff: [{ field: 'status', old: 'open', new: 'completed' }],
                })
            )
        )

        const user = userEvent.setup()

        render(<AuditLogPage />)

        await screen.findByText('Cliente criado')
        expect(screen.getByText(/1 de 2/)).toBeInTheDocument()

        await user.click(screen.getByRole('button', { name: 'Próxima página' }))

        await screen.findByText('OS atualizada')
        await waitFor(() => {
            expect(screen.queryByText('Cliente criado')).not.toBeInTheDocument()
        })

        await user.click(screen.getByRole('button', { name: /ver diff/i }))

        await screen.findByText(/Diff de Alterações/i)
        expect(screen.getByText('status')).toBeInTheDocument()
        expect(screen.getByText('open')).toBeInTheDocument()
        expect(screen.getByText('completed')).toBeInTheDocument()
    })
})
