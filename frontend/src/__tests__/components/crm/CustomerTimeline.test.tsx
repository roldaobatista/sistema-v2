import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import { CustomerTimeline } from '@/components/crm/CustomerTimeline'

const sampleActivities = [
    {
        id: 1,
        type: 'ligacao',
        title: 'Ligacao para prospect',
        description: 'Discutiu necessidades de calibracao',
        created_at: '2026-03-15T10:00:00Z',
        user: { name: 'Carlos' },
        channel: 'phone',
        outcome: 'Interessado',
        is_automated: false,
        contact: { name: 'Ana Souza' },
        deal: null,
    },
    {
        id: 2,
        type: 'email',
        title: 'Proposta enviada',
        description: null,
        created_at: '2026-03-15T14:00:00Z',
        user: { name: 'Maria' },
        channel: null,
        outcome: null,
        is_automated: true,
        contact: null,
        deal: { title: 'Contrato Anual' },
    },
    {
        id: 3,
        type: 'reuniao',
        title: 'Reuniao de apresentacao',
        description: 'Apresentou portfolio de servicos',
        created_at: '2026-03-14T09:00:00Z',
        user: { name: 'Pedro' },
        channel: null,
        outcome: null,
        is_automated: false,
        contact: null,
        deal: null,
    },
] as any

describe('CustomerTimeline', () => {
    it('renders empty state when no activities', () => {
        render(<CustomerTimeline activities={[]} />)
        expect(screen.getByText('Nenhuma atividade registrada')).toBeInTheDocument()
    })

    it('renders activity titles', () => {
        render(<CustomerTimeline activities={sampleActivities} />)
        expect(screen.getByText('Ligacao para prospect')).toBeInTheDocument()
        expect(screen.getByText('Proposta enviada')).toBeInTheDocument()
        expect(screen.getByText('Reuniao de apresentacao')).toBeInTheDocument()
    })

    it('groups activities by date', () => {
        render(<CustomerTimeline activities={sampleActivities} />)
        // Two distinct dates: March 15 and March 14
        const dateHeaders = screen.getAllByText(/de março de 2026/i)
        expect(dateHeaders.length).toBe(2)
    })

    it('displays user name on activity', () => {
        render(<CustomerTimeline activities={sampleActivities} />)
        expect(screen.getByText('Carlos')).toBeInTheDocument()
        expect(screen.getByText('Maria')).toBeInTheDocument()
    })

    it('shows "Auto" badge for automated activities', () => {
        render(<CustomerTimeline activities={sampleActivities} />)
        expect(screen.getByText('Auto')).toBeInTheDocument()
    })

    it('displays activity description when present', () => {
        render(<CustomerTimeline activities={sampleActivities} />)
        expect(screen.getByText('Discutiu necessidades de calibracao')).toBeInTheDocument()
    })

    it('displays contact name when present', () => {
        render(<CustomerTimeline activities={sampleActivities} />)
        expect(screen.getByText('Ana Souza')).toBeInTheDocument()
    })

    it('displays deal reference when present', () => {
        render(<CustomerTimeline activities={sampleActivities} />)
        expect(screen.getByText(/Deal: Contrato Anual/)).toBeInTheDocument()
    })

    it('displays channel and outcome when present', () => {
        render(<CustomerTimeline activities={sampleActivities} />)
        expect(screen.getByText('phone')).toBeInTheDocument()
        expect(screen.getByText('Interessado')).toBeInTheDocument()
    })

    it('applies compact spacing when compact=true', () => {
        const { container } = render(<CustomerTimeline activities={sampleActivities} compact />)
        expect(container.firstChild).toHaveClass('space-y-4')
    })

    it('applies normal spacing when compact=false', () => {
        const { container } = render(<CustomerTimeline activities={sampleActivities} />)
        expect(container.firstChild).toHaveClass('space-y-6')
    })
})
