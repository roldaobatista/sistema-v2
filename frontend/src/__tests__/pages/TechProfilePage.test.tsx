import { beforeEach, describe, expect, it, vi } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import TechProfilePage from '@/pages/tech/TechProfilePage'

const {
    mockNavigate,
    mockLogout,
    mockSyncNow,
    mockClearStore,
} = vi.hoisted(() => ({
    mockNavigate: vi.fn(),
    mockLogout: vi.fn(),
    mockSyncNow: vi.fn(),
    mockClearStore: vi.fn(),
}))

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
    return {
        ...actual,
        useNavigate: () => mockNavigate,
    }
})

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({
        user: { name: 'Tecnico Teste', email: 'tech@example.com' },
        logout: mockLogout,
    }),
}))

vi.mock('@/hooks/useSyncStatus', () => ({
    useSyncStatus: () => ({
        isOnline: true,
        pendingCount: 2,
        lastSyncAt: '2026-03-20T12:00:00Z',
        isSyncing: false,
        syncNow: mockSyncNow,
    }),
}))

vi.mock('@/lib/offlineDb', () => ({
    clearStore: mockClearStore,
}))

vi.mock('@/lib/utils', () => ({
    cn: (...args: Array<string | false | null | undefined>) => args.filter(Boolean).join(' '),
}))

describe('TechProfilePage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockClearStore.mockResolvedValue(undefined)
        vi.stubGlobal('confirm', vi.fn(() => true))
    })

    it('limpa todas as stores offline, incluindo customer-capsules', async () => {
        const user = userEvent.setup()
        render(<TechProfilePage />)

        await user.click(screen.getByRole('button', { name: /limpar dados locais/i }))

        await waitFor(() => {
            expect(mockClearStore).toHaveBeenCalledWith('customer-capsules')
        })

        expect(mockClearStore).toHaveBeenCalledTimes(9)
    })
})
