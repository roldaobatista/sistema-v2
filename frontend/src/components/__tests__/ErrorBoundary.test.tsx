import { Component } from 'react'
import { describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@/__tests__/test-utils'
import { ErrorBoundary } from '@/components/ErrorBoundary'

vi.mock('@sentry/react', () => ({
    captureException: vi.fn(),
}))

class BrokenComponent extends Component {
    override render() {
        throw new Error('Falha de teste')
    }
}

describe('ErrorBoundary', () => {
    it('renderiza o estado de erro como alerta principal com acoes acessiveis', () => {
        vi.spyOn(console, 'error').mockImplementation(() => {})

        render(
            <ErrorBoundary>
                <BrokenComponent />
            </ErrorBoundary>
        )

        expect(screen.getByRole('main')).toBeInTheDocument()
        expect(screen.getByRole('alert')).toBeInTheDocument()
        expect(screen.getByRole('button', { name: 'Tentar novamente' })).toBeInTheDocument()
        expect(screen.getByRole('button', { name: 'Recarregar pagina' })).toBeInTheDocument()
    })

    it('permite rearmar o boundary pela acao primaria', () => {
        vi.spyOn(console, 'error').mockImplementation(() => {})

        render(
            <ErrorBoundary>
                <BrokenComponent />
            </ErrorBoundary>
        )

        fireEvent.click(screen.getByRole('button', { name: 'Tentar novamente' }))

        expect(screen.getByRole('alert')).toBeInTheDocument()
    })
})
