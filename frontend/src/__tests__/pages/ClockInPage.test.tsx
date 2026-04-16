import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import ClockInPage from '@/pages/rh/ClockInPage'

const {
    mockHasPermission,
    mockApiGet,
    mockApiPost,
} = vi.hoisted(() => ({
    mockHasPermission: vi.fn(),
    mockApiGet: vi.fn(),
    mockApiPost: vi.fn(),
}))

vi.mock('@/stores/auth-store', () => ({
    useAuthStore: () => ({ hasPermission: mockHasPermission }),
}))

vi.mock('@/lib/api', () => ({
    default: { get: mockApiGet, post: mockApiPost },
    getApiErrorMessage: (_: unknown, fb: string) => fb,
    unwrapData: (r: { data?: { data?: unknown } | unknown } | null | undefined) => {
        const d = r?.data
        if (d != null && typeof d === 'object' && 'data' in d) return (d as { data: unknown }).data
        return d
    },
}))

vi.mock('@/lib/cross-tab-sync', () => ({ broadcastQueryInvalidation: vi.fn() }))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))
vi.mock('@/lib/utils', () => ({
    cn: (...args: unknown[]) => args.filter(Boolean).join(' '),
}))

// Mock navigator.mediaDevices
const mockGetUserMedia = vi.fn()
Object.defineProperty(global.navigator, 'mediaDevices', {
    value: { getUserMedia: mockGetUserMedia },
    writable: true,
})

// Mock navigator.geolocation
const mockGetCurrentPosition = vi.fn()
Object.defineProperty(global.navigator, 'geolocation', {
    value: { getCurrentPosition: mockGetCurrentPosition },
    writable: true,
})

describe('ClockInPage', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockHasPermission.mockReturnValue(true)
        mockGetUserMedia.mockRejectedValue(new Error('Camera not available in test'))
    })

    it('renders page title', async () => {
        mockApiGet.mockResolvedValue({ data: { data: { isClocked_in: false } } })
        render(<ClockInPage />)
        await waitFor(() => {
            expect(screen.getByText('Ponto Digital')).toBeInTheDocument()
        })
    })

    it('shows loading state initially', () => {
        mockApiGet.mockReturnValue(new Promise(() => {}))
        render(<ClockInPage />)
        const spinner = document.querySelector('.animate-spin')
        expect(spinner).toBeTruthy()
    })

    it('shows "Sem Ponto Ativo" when not clocked in', async () => {
        mockApiGet.mockResolvedValue({ data: { data: { isClocked_in: false } } })
        render(<ClockInPage />)
        await waitFor(() => {
            expect(screen.getByText('Sem Ponto Ativo')).toBeInTheDocument()
        })
    })

    it('shows "Ponto Aberto" when clocked in', async () => {
        mockApiGet.mockResolvedValue({
            data: {
                data: {
                    isClocked_in: true,
                    current_entry: { id: 1, clock_in: new Date().toISOString(), approval_status: 'pending' },
                },
            },
        })
        render(<ClockInPage />)
        await waitFor(() => {
            expect(screen.getByText('Ponto Aberto')).toBeInTheDocument()
        })
    })

    it('shows clock-out button when clocked in', async () => {
        mockApiGet.mockResolvedValue({
            data: {
                data: {
                    isClocked_in: true,
                    current_entry: { id: 1, clock_in: new Date().toISOString(), approval_status: 'pending' },
                },
            },
        })
        render(<ClockInPage />)
        await waitFor(() => {
            expect(screen.getByText('Registrar Saída')).toBeInTheDocument()
        })
    })

    it('shows elapsed timer when clocked in', async () => {
        mockApiGet.mockResolvedValue({
            data: {
                data: {
                    isClocked_in: true,
                    current_entry: { id: 1, clock_in: new Date(Date.now() - 3600000).toISOString(), approval_status: 'pending' },
                },
            },
        })
        render(<ClockInPage />)
        await waitFor(() => {
            // Timer should show some time elapsed
            const timerElement = document.querySelector('.font-mono')
            expect(timerElement).toBeTruthy()
        })
    })

    it('shows camera step when not clocked in', async () => {
        mockApiGet.mockResolvedValue({ data: { data: { isClocked_in: false } } })
        render(<ClockInPage />)
        await waitFor(() => {
            expect(screen.getByText('Selfie')).toBeInTheDocument()
            expect(screen.getByText('Confirmar')).toBeInTheDocument()
            expect(screen.getByText('GPS & Enviar')).toBeInTheDocument()
        })
    })

    it('shows capture selfie button', async () => {
        mockApiGet.mockResolvedValue({ data: { data: { isClocked_in: false } } })
        render(<ClockInPage />)
        await waitFor(() => {
            // Camera will error in test, but button should be present or error message
            const captureBtn = screen.queryByText('Capturar Selfie')
            const retryBtn = screen.queryByText('Tentar Novamente')
            expect(captureBtn || retryBtn).toBeTruthy()
        })
    })
})
