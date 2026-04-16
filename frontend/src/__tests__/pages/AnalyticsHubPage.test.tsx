import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen } from '@/__tests__/test-utils'
import { AnalyticsHubPage } from '@/pages/analytics/AnalyticsHubPage'

const {
    mockUseAnalyticsDatasets,
    mockUseDataExportJobs,
    mockUseEmbeddedDashboards,
} = vi.hoisted(() => ({
    mockUseAnalyticsDatasets: vi.fn(() => ({ data: { data: [] }, isLoading: false })),
    mockUseDataExportJobs: vi.fn(() => ({ data: { data: [] }, isLoading: false })),
    mockUseEmbeddedDashboards: vi.fn(() => ({ data: { data: [] }, isLoading: false })),
}))

vi.mock('@/components/analytics/PdfExportButton', () => ({
    PdfExportButton: () => <button type="button">Exportar PDF</button>,
}))

vi.mock('@/features/analytics-bi/hooks', () => ({
    useAnalyticsDatasets: mockUseAnalyticsDatasets,
    useDataExportJobs: mockUseDataExportJobs,
    useEmbeddedDashboards: mockUseEmbeddedDashboards,
}))

vi.mock('@/pages/analytics/AnalyticsOverview', () => ({
    AnalyticsOverview: ({ from, to }: { from: string; to: string }) => <div>{`overview:${from}:${to}`}</div>,
}))

vi.mock('@/pages/analytics/PredictiveAnalytics', () => ({
    PredictiveAnalytics: () => <div>predictive-content</div>,
}))

describe('AnalyticsHubPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('monta painéis pesados somente quando a aba correspondente é aberta', async () => {
        const user = userEvent.setup()

        render(<AnalyticsHubPage />)

        expect(await screen.findByText(/overview:/i)).toBeInTheDocument()
        expect(mockUseAnalyticsDatasets).not.toHaveBeenCalled()
        expect(mockUseDataExportJobs).not.toHaveBeenCalled()
        expect(mockUseEmbeddedDashboards).not.toHaveBeenCalled()

        await user.click(screen.getByRole('tab', { name: /datasets/i }))
        expect(mockUseAnalyticsDatasets).toHaveBeenCalledTimes(1)

        await user.click(screen.getByRole('tab', { name: /exportações/i }))
        expect(mockUseDataExportJobs).toHaveBeenCalledTimes(1)

        await user.click(screen.getByRole('tab', { name: /dashboards/i }))
        expect(mockUseEmbeddedDashboards).toHaveBeenCalledTimes(1)
    })
})
