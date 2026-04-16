import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen } from '@/__tests__/test-utils'
import { PredictiveAnalytics } from '@/pages/analytics/PredictiveAnalytics'

const { mockApiGet } = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
}))

vi.mock('@/lib/api', () => ({
    default: {
        get: mockApiGet,
    },
}))

vi.mock('@/components/charts', () => ({
    ChartCard: ({ title, children }: { title: string; children: React.ReactNode }) => (
        <section>
            <h3>{title}</h3>
            {children}
        </section>
    ),
    ChartCardSkeleton: () => <div>loading chart</div>,
    TrendAreaChart: () => <div>trend chart</div>,
}))

describe('PredictiveAnalytics', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockApiGet.mockResolvedValue({ data: { historical: [], forecast: [], trend: 'up' } })
    })

    it('expoe o campo de pergunta com nome acessivel e o estado pressionado das abas', async () => {
        const user = userEvent.setup()

        render(<PredictiveAnalytics />)

        expect(screen.getByRole('button', { name: 'Previsão de Tendências' })).toHaveAttribute('aria-pressed', 'true')

        await user.click(screen.getByRole('button', { name: 'Pergunte aos Dados' }))

        expect(screen.getByRole('button', { name: 'Pergunte aos Dados' })).toHaveAttribute('aria-pressed', 'true')
        expect(screen.getByRole('textbox', { name: /pergunta analitica/i })).toBeInTheDocument()
    })
})
