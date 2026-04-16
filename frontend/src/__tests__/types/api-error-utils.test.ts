import { describe, expect, it } from 'vitest'
import { extractApiError, extractDeleteConflict } from '@/types/api'

function createAxiosError(data?: unknown) {
    return {
        isAxiosError: true,
        response: {
            data,
        },
    }
}

describe('extractApiError', () => {
    it('retorna o primeiro erro de validacao quando errors existe', () => {
        const error = createAxiosError({
            message: 'Validation failed.',
            errors: {
                department_id: ['Departamento obrigatorio'],
                name: ['Nome obrigatorio'],
            },
        })

        expect(extractApiError(error, 'Fallback')).toBe('Departamento obrigatorio')
    })

    it('retorna message quando presente', () => {
        const error = createAxiosError({ message: 'Mensagem da API' })

        expect(extractApiError(error, 'Fallback')).toBe('Mensagem da API')
    })

    it('retorna error quando payload nao tem message', () => {
        const error = createAxiosError({ error: 'Erro alternativo' })

        expect(extractApiError(error, 'Fallback')).toBe('Erro alternativo')
    })

    it('retorna fallback quando nao ha payload util', () => {
        expect(extractApiError(createAxiosError({}), 'Fallback')).toBe('Fallback')
        expect(extractApiError(null, 'Fallback')).toBe('Fallback')
    })

    it('ignora errors malformado e cai para fallback', () => {
        const error = createAxiosError({ errors: 'invalid-format' })

        expect(extractApiError(error, 'Fallback')).toBe('Fallback')
    })
})

describe('extractDeleteConflict', () => {
    it('retorna payload de conflito com dependencias em erros 409/422', () => {
        const error = {
            isAxiosError: true,
            response: {
                status: 409,
                data: {
                    message: 'Nao e possivel excluir',
                    dependencies: {
                        quotes: 2,
                        work_orders: 1,
                    },
                },
            },
        }

        expect(extractDeleteConflict(error)).toEqual({
            message: 'Nao e possivel excluir',
            dependencies: {
                quotes: 2,
                work_orders: 1,
            },
        })
    })

    it('ignora erros sem status de conflito ou dependencias invalidas', () => {
        const serverError = {
            isAxiosError: true,
            response: {
                status: 500,
                data: {
                    message: 'Erro interno',
                },
            },
        }

        const malformedConflict = {
            isAxiosError: true,
            response: {
                status: 422,
                data: {
                    message: 'Bloqueado',
                    dependencies: ['invalid'],
                },
            },
        }

        expect(extractDeleteConflict(serverError)).toBeNull()
        expect(extractDeleteConflict(malformedConflict)).toEqual({
            message: 'Bloqueado',
            dependencies: null,
        })
    })
})
