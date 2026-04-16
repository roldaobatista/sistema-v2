import '@testing-library/jest-dom/vitest'
import { vi, afterAll, afterEach, beforeAll } from 'vitest'
import { server } from './mocks/server'

// ── Mock localStorage ──────────────────────────────────────────────────────
const localStorageMock = (() => {
    let store: Record<string, string> = {}
    return {
        getItem: (key: string) => store[key] ?? null,
        setItem: (key: string, value: string) => { store[key] = value },
        removeItem: (key: string) => { delete store[key] },
        clear: () => { store = {} },
        get length() { return Object.keys(store).length },
        key: (index: number) => Object.keys(store)[index] ?? null,
    }
})()

Object.defineProperty(window, 'localStorage', { value: localStorageMock })

// ── Mock matchMedia ────────────────────────────────────────────────────────
Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: vi.fn().mockImplementation((query: string) => ({
        matches: false,
        media: query,
        onchange: null,
        addListener: vi.fn(),
        removeListener: vi.fn(),
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
        dispatchEvent: vi.fn(),
    })),
})

// ── Mock window.URL.createObjectURL ────────────────────────────────────────
Object.defineProperty(window.URL, 'createObjectURL', {
    writable: true,
    value: vi.fn(() => 'blob:mock-url'),
})

// ── MSW server lifecycle ───────────────────────────────────────────────────
// Start before all tests, reset handlers after each, close after all
const strictUnhandledRequests = (request: Request, print: { warning(): void; error(): void }) => {
    const url = new URL(request.url)
    const isInternalApiRequest = url.pathname.startsWith('/api') || url.pathname.startsWith('/sanctum')
    const strictModeEnabled = process.env.VITEST_STRICT_MSW === 'true'

    if (isInternalApiRequest && strictModeEnabled) {
        throw new Error(`Unhandled API request during test: ${request.method} ${request.url}`)
    }

    print.warning()
}

beforeAll(() => server.listen({ onUnhandledRequest: strictUnhandledRequests }))
afterEach(() => server.resetHandlers())
afterAll(() => server.close())
