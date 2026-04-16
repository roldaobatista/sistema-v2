import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { DonutChart } from '@/components/charts/DonutChart'

// Mock recharts to avoid canvas/SVG rendering issues in tests
vi.mock('recharts', () => ({
    PieChart: ({ children }: { children: React.ReactNode }) => <div data-testid="pie-chart">{children}</div>,
    Pie: ({ children, data }: { children: React.ReactNode; data: any[] }) => (
        <div data-testid="pie" data-items={data?.length}>{children}</div>
    ),
    Cell: ({ fill }: { fill: string }) => <div data-testid="cell" data-fill={fill} />,
    ResponsiveContainer: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
    Tooltip: () => <div data-testid="tooltip" />,
}))

const sampleData = [
    { name: 'Calibracao', value: 50 },
    { name: 'Manutencao', value: 30 },
    { name: 'Verificacao', value: 20 },
]

describe('DonutChart', () => {
    it('renders chart with data', () => {
        render(<DonutChart data={sampleData} />)
        expect(screen.getByTestId('pie-chart')).toBeInTheDocument()
    })

    it('shows "Sem dados" when data is empty', () => {
        render(<DonutChart data={[]} />)
        expect(screen.getByText('Sem dados')).toBeInTheDocument()
    })

    it('shows "Sem dados" when all values are zero', () => {
        render(<DonutChart data={[{ name: 'A', value: 0 }]} />)
        expect(screen.getByText('Sem dados')).toBeInTheDocument()
    })

    it('renders legend items with names and percentages', () => {
        render(<DonutChart data={sampleData} />)
        expect(screen.getByText('Calibracao')).toBeInTheDocument()
        expect(screen.getByText('Manutencao')).toBeInTheDocument()
        expect(screen.getByText('Verificacao')).toBeInTheDocument()
        // 50/100 = 50%
        expect(screen.getByText(/50%/)).toBeInTheDocument()
        expect(screen.getByText(/30%/)).toBeInTheDocument()
        expect(screen.getByText(/20%/)).toBeInTheDocument()
    })

    it('renders center label and value when provided', () => {
        render(<DonutChart data={sampleData} centerLabel="Total" centerValue="100" />)
        expect(screen.getByText('Total')).toBeInTheDocument()
        expect(screen.getByText('100')).toBeInTheDocument()
    })

    it('applies custom formatValue to legend values', () => {
        const formatValue = (v: number) => `R$ ${v.toFixed(2)}`
        render(<DonutChart data={sampleData} formatValue={formatValue} />)
        expect(screen.getByText(/R\$ 50\.00/)).toBeInTheDocument()
    })

    it('renders colored legend dots', () => {
        const { container } = render(<DonutChart data={sampleData} />)
        const dots = container.querySelectorAll('.rounded-full[style*="background"]')
        expect(dots).toHaveLength(3)
    })

    it('uses custom colors from data when provided', () => {
        const coloredData = [
            { name: 'A', value: 50, color: '#ff0000' },
            { name: 'B', value: 50, color: '#00ff00' },
        ]
        const { container } = render(<DonutChart data={coloredData} />)
        const dots = container.querySelectorAll('.rounded-full[style*="background"]')
        expect(dots[0]).toHaveStyle({ backgroundColor: '#ff0000' })
        expect(dots[1]).toHaveStyle({ backgroundColor: '#00ff00' })
    })
})
