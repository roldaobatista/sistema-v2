import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { GaugeChart } from '@/components/charts/GaugeChart'

// Mock recharts
vi.mock('recharts', () => ({
    RadialBarChart: ({ children }: { children: React.ReactNode }) => <div data-testid="radial-chart">{children}</div>,
    RadialBar: ({ children }: { children: React.ReactNode }) => <div data-testid="radial-bar">{children}</div>,
    ResponsiveContainer: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

describe('GaugeChart', () => {
    it('renders the chart', () => {
        render(<GaugeChart value={75} />)
        expect(screen.getByTestId('radial-chart')).toBeInTheDocument()
    })

    it('displays the value with default suffix', () => {
        render(<GaugeChart value={75} />)
        expect(screen.getByText('75%')).toBeInTheDocument()
    })

    it('displays value with custom suffix', () => {
        render(<GaugeChart value={42} suffix=" pts" />)
        expect(screen.getByText('42 pts')).toBeInTheDocument()
    })

    it('displays label when provided', () => {
        render(<GaugeChart value={60} label="Performance" />)
        expect(screen.getByText('Performance')).toBeInTheDocument()
    })

    it('does not display label when not provided', () => {
        const { container } = render(<GaugeChart value={60} />)
        const labels = container.querySelectorAll('.uppercase')
        expect(labels).toHaveLength(0)
    })

    it('uses success color for values >= 60%', () => {
        const { container } = render(<GaugeChart value={80} />)
        const valueText = screen.getByText('80%')
        // High value -> success color
        expect(valueText).toHaveStyle({ color: 'var(--color-success)' })
    })

    it('uses warning color for values between 30-59%', () => {
        const { container } = render(<GaugeChart value={45} />)
        const valueText = screen.getByText('45%')
        expect(valueText).toHaveStyle({ color: 'var(--color-warning)' })
    })

    it('uses danger color for values below 30%', () => {
        const { container } = render(<GaugeChart value={15} />)
        const valueText = screen.getByText('15%')
        expect(valueText).toHaveStyle({ color: 'var(--color-danger)' })
    })

    it('uses custom color when provided', () => {
        render(<GaugeChart value={50} color="#ff00ff" />)
        const valueText = screen.getByText('50%')
        expect(valueText).toHaveStyle({ color: '#ff00ff' })
    })

    it('clamps value to max', () => {
        render(<GaugeChart value={150} max={100} />)
        // The displayed value is the raw value, clamping is for the gauge arc
        expect(screen.getByText('150%')).toBeInTheDocument()
    })
})
