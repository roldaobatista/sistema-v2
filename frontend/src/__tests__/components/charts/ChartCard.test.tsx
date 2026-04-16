import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import { ChartCard, ChartCardSkeleton } from '@/components/charts/ChartCard'

describe('ChartCard', () => {
    it('renders title', () => {
        render(
            <ChartCard title="Revenue">
                <div>Chart content</div>
            </ChartCard>
        )
        expect(screen.getByText('Revenue')).toBeInTheDocument()
    })

    it('renders subtitle when provided', () => {
        render(
            <ChartCard title="Revenue" subtitle="Monthly breakdown">
                <div>Chart content</div>
            </ChartCard>
        )
        expect(screen.getByText('Monthly breakdown')).toBeInTheDocument()
    })

    it('does not render subtitle when not provided', () => {
        const { container } = render(
            <ChartCard title="Revenue">
                <div>Chart content</div>
            </ChartCard>
        )
        expect(container.querySelectorAll('p')).toHaveLength(0)
    })

    it('renders children content', () => {
        render(
            <ChartCard title="Revenue">
                <div data-testid="chart">Chart goes here</div>
            </ChartCard>
        )
        expect(screen.getByTestId('chart')).toBeInTheDocument()
    })

    it('renders icon when provided', () => {
        render(
            <ChartCard title="Revenue" icon={<span data-testid="chart-icon">$</span>}>
                <div>Content</div>
            </ChartCard>
        )
        expect(screen.getByTestId('chart-icon')).toBeInTheDocument()
    })

    it('renders actions slot', () => {
        render(
            <ChartCard title="Revenue" actions={<button>Export</button>}>
                <div>Content</div>
            </ChartCard>
        )
        expect(screen.getByRole('button', { name: 'Export' })).toBeInTheDocument()
    })

    it('applies custom height as number', () => {
        const { container } = render(
            <ChartCard title="Revenue" height={400}>
                <div>Content</div>
            </ChartCard>
        )
        const chartArea = container.querySelector('[style*="height"]')
        expect(chartArea).toHaveStyle({ height: '400px' })
    })

    it('applies default height of 280px', () => {
        const { container } = render(
            <ChartCard title="Revenue">
                <div>Content</div>
            </ChartCard>
        )
        const chartArea = container.querySelector('[style*="height"]')
        expect(chartArea).toHaveStyle({ height: '280px' })
    })
})

describe('ChartCardSkeleton', () => {
    it('renders with animate-pulse class', () => {
        const { container } = render(<ChartCardSkeleton />)
        expect(container.querySelector('.animate-pulse')).toBeInTheDocument()
    })

    it('renders skeleton bars', () => {
        const { container } = render(<ChartCardSkeleton />)
        const bars = container.querySelectorAll('.bg-surface-100')
        expect(bars.length).toBeGreaterThan(0)
    })

    it('applies custom height', () => {
        const { container } = render(<ChartCardSkeleton height={300} />)
        const area = container.querySelector('[style*="height"]')
        expect(area).toHaveStyle({ height: '300px' })
    })
})
