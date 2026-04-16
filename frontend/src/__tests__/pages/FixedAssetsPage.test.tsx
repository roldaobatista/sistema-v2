import { describe, expect, it, vi } from 'vitest'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter } from 'react-router-dom'
import { FixedAssetsPage } from '@/pages/financeiro/FixedAssetsPage'

vi.mock('@/hooks/useFixedAssets', () => ({
    useFixedAssets: () => ({
        data: {
            data: [
                {
                    id: 1,
                    code: 'AT-00001',
                    name: 'Balança XR-500',
                    category: 'equipment',
                    current_book_value: '42000.00',
                    status: 'active',
                    location: 'Laboratório',
                },
            ],
        },
        isLoading: false,
        refetch: vi.fn(),
    }),
    useFixedAssetsDashboard: () => ({
        data: {
            total_assets: 1,
            total_current_book_value: 42000,
            total_accumulated_depreciation: 3000,
            ciap_credits_pending: 12,
        },
    }),
    useCreateFixedAsset: () => ({ mutateAsync: vi.fn(), isPending: false }),
    useSuspendAsset: () => ({ mutateAsync: vi.fn(), isPending: false }),
    useReactivateAsset: () => ({ mutateAsync: vi.fn(), isPending: false }),
    useDisposeAsset: () => ({ mutateAsync: vi.fn(), isPending: false }),
}))

describe('FixedAssetsPage', () => {
    it('renders asset portfolio summary and table rows', () => {
        const markup = renderToStaticMarkup(
            <MemoryRouter>
                <FixedAssetsPage />
            </MemoryRouter>
        )

        expect(markup).toContain('Ativo Imobilizado')
        expect(markup).toContain('AT-00001')
        expect(markup).toContain('Balança XR-500')
        expect(markup).toContain('Parcelas CIAP pendentes')
        expect(markup).toContain('Novo ativo')
    })
})
