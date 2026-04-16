import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@/__tests__/test-utils'
import BeforeAfterPhotos from '@/components/os/BeforeAfterPhotos'

const { mockApiGet, toastError, toastSuccess } = vi.hoisted(() => ({
    mockApiGet: vi.fn(),
    toastError: vi.fn(),
    toastSuccess: vi.fn(),
}))

vi.mock('@/lib/api', async () => {
    const actual = await vi.importActual<typeof import('@/lib/api')>('@/lib/api')
    return {
        ...actual,
        default: {
            get: mockApiGet,
            post: vi.fn(),
        },
    }
})

vi.mock('sonner', () => ({
    toast: {
        error: toastError,
        success: toastSuccess,
    },
}))

describe('BeforeAfterPhotos', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockApiGet.mockResolvedValue({ data: { data: [] } })
    })

    it('nao exibe botoes de upload sem permissao de alteracao', async () => {
        render(<BeforeAfterPhotos workOrderId={10} canUpload={false} />)

        expect(await screen.findByText(/nenhuma foto registrada/i)).toBeInTheDocument()
        expect(screen.queryByRole('button', { name: 'Antes' })).not.toBeInTheDocument()
        expect(screen.queryByRole('button', { name: 'Depois' })).not.toBeInTheDocument()
        expect(screen.queryByLabelText(/upload de foto/i)).not.toBeInTheDocument()
    })
})
