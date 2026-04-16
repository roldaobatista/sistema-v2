import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import { CustomerHealthScore } from '@/components/crm/CustomerHealthScore'

const breakdown = [
    { score: 25, max: 30, label: 'Frequencia de Compra' },
    { score: 15, max: 20, label: 'Ticket Medio' },
    { score: 20, max: 20, label: 'Pontualidade' },
    { score: 10, max: 30, label: 'Recencia' },
]

describe('CustomerHealthScore', () => {
    it('displays the score number', () => {
        render(<CustomerHealthScore score={85} breakdown={breakdown} />)
        expect(screen.getByText('85')).toBeInTheDocument()
    })

    it('shows "Excelente" label for score >= 80', () => {
        render(<CustomerHealthScore score={85} breakdown={breakdown} />)
        expect(screen.getByText('Excelente')).toBeInTheDocument()
    })

    it('shows "Bom" label for score 60-79', () => {
        render(<CustomerHealthScore score={65} breakdown={breakdown} />)
        expect(screen.getByText('Bom')).toBeInTheDocument()
    })

    it('shows "Atenção" label for score 40-59', () => {
        render(<CustomerHealthScore score={45} breakdown={breakdown} />)
        expect(screen.getByText('Atenção')).toBeInTheDocument()
    })

    it('shows "Crítico" label for score < 40', () => {
        render(<CustomerHealthScore score={20} breakdown={breakdown} />)
        expect(screen.getByText('Crítico')).toBeInTheDocument()
    })

    it('renders all breakdown items', () => {
        render(<CustomerHealthScore score={70} breakdown={breakdown} />)
        expect(screen.getByText('Frequencia de Compra')).toBeInTheDocument()
        expect(screen.getByText('Ticket Medio')).toBeInTheDocument()
        expect(screen.getByText('Pontualidade')).toBeInTheDocument()
        expect(screen.getByText('Recencia')).toBeInTheDocument()
    })

    it('displays breakdown scores with max values', () => {
        render(<CustomerHealthScore score={70} breakdown={breakdown} />)
        expect(screen.getByText('25/30')).toBeInTheDocument()
        expect(screen.getByText('15/20')).toBeInTheDocument()
        expect(screen.getByText('20/20')).toBeInTheDocument()
        expect(screen.getByText('10/30')).toBeInTheDocument()
    })

    it('shows "Health Score do Cliente" description text', () => {
        render(<CustomerHealthScore score={70} breakdown={breakdown} />)
        expect(screen.getByText('Health Score do Cliente')).toBeInTheDocument()
    })

    it('renders progress bars for breakdown items', () => {
        const { container } = render(<CustomerHealthScore score={70} breakdown={breakdown} />)
        const progressBars = container.querySelectorAll('.rounded-full.bg-surface-100')
        expect(progressBars.length).toBeGreaterThan(0)
    })

    it('uses emerald color for full-score breakdown items', () => {
        const { container } = render(<CustomerHealthScore score={70} breakdown={breakdown} />)
        // Pontualidade is 20/20 (full score), should have bg-emerald-500
        const emeraldBars = container.querySelectorAll('.bg-emerald-500')
        expect(emeraldBars.length).toBeGreaterThan(0)
    })
})
