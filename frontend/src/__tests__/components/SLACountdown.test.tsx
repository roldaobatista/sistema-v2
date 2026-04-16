import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import SLACountdown from '@/components/common/SLACountdown'

describe('SLACountdown', () => {
    beforeEach(() => {
        vi.useFakeTimers()
        vi.setSystemTime(new Date('2026-03-15T12:00:00Z'))
    })

    afterEach(() => {
        vi.useRealTimers()
    })

    it('renders nothing when dueAt is null', () => {
        const { container } = render(<SLACountdown dueAt={null} status="pending" />)
        expect(container.firstChild).toBeNull()
    })

    it('shows neutral state for completed status', () => {
        render(<SLACountdown dueAt="2026-03-16T12:00:00Z" status="completed" />)
        expect(screen.getByText('--:--')).toBeInTheDocument()
    })

    it('shows neutral state for cancelled status', () => {
        render(<SLACountdown dueAt="2026-03-16T12:00:00Z" status="cancelled" />)
        expect(screen.getByText('--:--')).toBeInTheDocument()
    })

    it('shows danger variant with "Atrasado" text when past due', () => {
        // Due date in the past
        render(<SLACountdown dueAt="2026-03-14T10:00:00Z" status="pending" />)
        expect(screen.getByText(/Atrasado/)).toBeInTheDocument()
        // Should have red/danger background class
        const container = screen.getByText(/Atrasado/).closest('div')
        expect(container?.className).toContain('bg-red')
    })

    it('shows success variant (green) when more than 4 hours remain', () => {
        // 1 day ahead = plenty of time
        render(<SLACountdown dueAt="2026-03-16T12:00:00Z" status="pending" />)
        const container = screen.getByText(/em/).closest('div')
        expect(container?.className).toContain('bg-green')
    })

    it('shows warning variant (yellow) when between 1-4 hours remain', () => {
        // 3 hours ahead
        render(<SLACountdown dueAt="2026-03-15T15:00:00Z" status="pending" />)
        const container = screen.getByText(/em/).closest('div')
        expect(container?.className).toContain('bg-yellow')
    })

    it('shows danger variant (red) when less than 1 hour remains', () => {
        // 30 minutes ahead
        render(<SLACountdown dueAt="2026-03-15T12:30:00Z" status="pending" />)
        const timeText = screen.getByText(/minuto/)
        const container = timeText.closest('div')
        expect(container?.className).toContain('bg-red')
    })

    it('treats delivered status same as completed (neutral)', () => {
        render(<SLACountdown dueAt="2026-03-16T12:00:00Z" status="delivered" />)
        expect(screen.getByText('--:--')).toBeInTheDocument()
    })

    it('treats invoiced status same as completed (neutral)', () => {
        render(<SLACountdown dueAt="2026-03-16T12:00:00Z" status="invoiced" />)
        expect(screen.getByText('--:--')).toBeInTheDocument()
    })
})
