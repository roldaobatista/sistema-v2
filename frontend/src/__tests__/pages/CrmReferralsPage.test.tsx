import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen } from '@/__tests__/test-utils'
import { CrmReferralsPage } from '@/pages/crm/CrmReferralsPage'

const {
  mockGetReferrals,
  mockGetReferralStats,
  mockGetReferralOptions,
  mockCreateReferral,
  mockUpdateReferral,
  mockDeleteReferral,
} = vi.hoisted(() => ({
  mockGetReferrals: vi.fn(),
  mockGetReferralStats: vi.fn(),
  mockGetReferralOptions: vi.fn(),
  mockCreateReferral: vi.fn(),
  mockUpdateReferral: vi.fn(),
  mockDeleteReferral: vi.fn(),
}))

vi.mock('@/lib/crm-features-api', () => ({
  crmFeaturesApi: {
    getReferrals: mockGetReferrals,
    getReferralStats: mockGetReferralStats,
    getReferralOptions: mockGetReferralOptions,
    createReferral: mockCreateReferral,
    updateReferral: mockUpdateReferral,
    deleteReferral: mockDeleteReferral,
  },
}))

describe('CrmReferralsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    mockGetReferrals.mockResolvedValue([])
    mockGetReferralStats.mockResolvedValue({
      total: 0,
      pending: 0,
      converted: 0,
      conversion_rate: 0,
      total_rewards: 0,
      total_reward_value: 0,
      top_referrers: [],
    })
    mockGetReferralOptions.mockResolvedValue({
      customers: [{ id: 1, name: 'Cliente Alfa' }],
      deals: [{ id: 9, title: 'Negocio Teste', value: 5000 }],
    })
    mockCreateReferral.mockResolvedValue({})
    mockUpdateReferral.mockResolvedValue({})
    mockDeleteReferral.mockResolvedValue({})
  })

  it('abre o modal com valor da recompensa mascarado em real', async () => {
    const user = userEvent.setup()

    render(<CrmReferralsPage />)

    await user.click(await screen.findByRole('button', { name: /Nova Indicacao/i }))

    const rewardInput = screen.getByLabelText('Valor da Recompensa') as HTMLInputElement
    expect(rewardInput.value).toBe('R$\u00a00,00')
  })
})
