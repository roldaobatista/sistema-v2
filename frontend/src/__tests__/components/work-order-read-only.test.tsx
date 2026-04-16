import { describe, expect, it } from 'vitest'
import { render, screen } from '@/__tests__/test-utils'
import { ExecutionActions } from '@/components/os/ExecutionActions'
import TagManager from '@/components/os/TagManager'
import DeliveryForecast from '@/components/os/DeliveryForecast'
import PhotoChecklist from '@/components/os/PhotoChecklist'

describe('work order components read only', () => {
    it('mostra bloqueio explicito quando a execucao em campo nao pode ser usada', () => {
        render(
            <ExecutionActions
                workOrderId={1}
                status="open"
                canExecute={false}
                blockedMessage="Fluxo de campo bloqueado."
            />,
        )

        expect(screen.getByText('Fluxo de campo bloqueado.')).toBeInTheDocument()
        expect(screen.queryByRole('button', { name: /iniciar deslocamento/i })).not.toBeInTheDocument()
    })

    it('renderiza tags em somente leitura sem acoes destrutivas', () => {
        render(<TagManager workOrderId={1} currentTags={['urgente']} canEdit={false} />)

        expect(screen.getByText('urgente')).toBeInTheDocument()
        expect(screen.queryByRole('button', { name: /adicionar/i })).not.toBeInTheDocument()
        expect(screen.queryByRole('button', { name: /remover tag urgente/i })).not.toBeInTheDocument()
    })

    it('renderiza previsao em somente leitura sem CTA de edicao', () => {
        render(<DeliveryForecast workOrderId={1} currentForecast={null} canEdit={false} />)

        expect(screen.getByText('Nenhuma previsao informada')).toBeInTheDocument()
        expect(screen.queryByRole('button')).not.toBeInTheDocument()
    })

    it('renderiza checklist de fotos sem controles de edicao quando bloqueado', () => {
        render(
            <PhotoChecklist
                workOrderId={1}
                canEdit={false}
                initialChecklist={{
                    items: [
                        { id: 'item-1', text: 'Foto do painel', checked: true },
                    ],
                }}
            />,
        )

        expect(screen.getByText('Foto do painel')).toBeInTheDocument()
        expect(screen.queryByLabelText('Texto do novo item do checklist')).not.toBeInTheDocument()
        expect(screen.queryByRole('button', { name: /anexar foto/i })).not.toBeInTheDocument()
        expect(screen.queryByRole('button', { name: /remover item/i })).not.toBeInTheDocument()
    })
})
