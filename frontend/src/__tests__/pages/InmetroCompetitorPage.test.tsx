import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@/__tests__/test-utils'
import InmetroCompetitorPage from '@/pages/inmetro/InmetroCompetitorPage'

const {
  mockUseMarketShareTimeline,
  mockUseSnapshotMarketShare,
  mockUseCompetitorMovements,
  mockUsePricingEstimate,
  mockUseWinLossAnalysis,
} = vi.hoisted(() => ({
  mockUseMarketShareTimeline: vi.fn(),
  mockUseSnapshotMarketShare: vi.fn(),
  mockUseCompetitorMovements: vi.fn(),
  mockUsePricingEstimate: vi.fn(),
  mockUseWinLossAnalysis: vi.fn(),
}))

vi.mock('@/hooks/useInmetroAdvanced', () => ({
  useMarketShareTimeline: () => mockUseMarketShareTimeline(),
  useSnapshotMarketShare: () => mockUseSnapshotMarketShare(),
  useCompetitorMovements: () => mockUseCompetitorMovements(),
  usePricingEstimate: () => mockUsePricingEstimate(),
  useWinLossAnalysis: () => mockUseWinLossAnalysis(),
}))

describe('InmetroCompetitorPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.stubGlobal('ResizeObserver', class {
      observe() {}
      unobserve() {}
      disconnect() {}
    })

    mockUseMarketShareTimeline.mockReturnValue({
      data: {
        current_share: 55,
        snapshots: [
          { period: 'Jan/2026', data: { total_instruments: 80 } },
          { period: 'Fev/2026', data: { total_instruments: 92, our_share: 55 } },
        ],
      },
      isLoading: false,
    })
    mockUseSnapshotMarketShare.mockReturnValue({ mutate: vi.fn(), isPending: false })
    mockUseCompetitorMovements.mockReturnValue({ data: { total_new: 0, movements: [] } })
    mockUsePricingEstimate.mockReturnValue({ data: { estimates: [] } })
    mockUseWinLossAnalysis.mockReturnValue({ data: { wins: 0, losses: 0, win_rate: 0, records: [] } })
  })

  it('não exibe NaN quando um snapshot anterior não possui our_share', async () => {
    render(<InmetroCompetitorPage />)

    expect(await screen.findByText('Fev/2026')).toBeInTheDocument()
    expect(screen.getAllByText('—').length).toBeGreaterThan(0)
    expect(screen.queryByText('NaN%')).not.toBeInTheDocument()
  })
})
