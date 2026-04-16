import { beforeEach, describe, expect, it, vi } from 'vitest'
import { http, HttpResponse } from 'msw'

import { server } from '../mocks/server'

function apiPattern(path: string): RegExp {
    const escapedPath = path.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
    return new RegExp(`http://(localhost|127\\.0\\.0\\.1):8000/api/v1${escapedPath}(\\?.*)?$`)
}

const toast = {
    error: vi.fn(),
}

vi.mock('sonner', () => ({ toast }))
vi.mock('@/lib/api-health', () => ({
    reportSuccess: vi.fn(),
    reportFailure: vi.fn(),
}))

describe('API interceptor error handling', () => {
    beforeEach(() => {
        localStorage.clear()
        toast.error.mockReset()
    })

    it('mostra a mensagem real de 403 do backend', async () => {
        const api = (await import('@/lib/api')).default

        server.use(
            http.get(apiPattern('/__tests__/forbidden'), () =>
                HttpResponse.json({ message: 'Sem permissão para esta ação' }, { status: 403 })
            )
        )

        await expect(api.get('/__tests__/forbidden')).rejects.toBeDefined()

        expect(toast.error).toHaveBeenCalledWith('Sem permissão para esta ação')
    })

    it('agrega mensagens de validação 422', async () => {
        const api = (await import('@/lib/api')).default

        server.use(
            http.post(apiPattern('/__tests__/validation'), () =>
                HttpResponse.json({
                    errors: {
                        name: ['Nome obrigatório'],
                        email: ['Email inválido'],
                    },
                }, { status: 422 })
            )
        )

        await expect(api.post('/__tests__/validation', {})).rejects.toBeDefined()

        expect(toast.error).toHaveBeenCalledWith('Nome obrigatório; Email inválido')
    })

    it('prioriza a mensagem do servidor para erros 500', async () => {
        const api = (await import('@/lib/api')).default

        server.use(
            http.get(apiPattern('/__tests__/server-error'), () =>
                HttpResponse.json({ message: 'Falha ao processar lote financeiro' }, { status: 500 })
            )
        )

        await expect(api.get('/__tests__/server-error')).rejects.toBeDefined()

        expect(toast.error).toHaveBeenCalledWith('Falha ao processar lote financeiro')
    })
})
