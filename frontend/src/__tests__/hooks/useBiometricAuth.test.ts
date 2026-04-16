import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { renderHook, act } from '@testing-library/react'

vi.mock('@/lib/constants', () => ({
    STORAGE_KEYS: {
        BIOMETRIC_CREDENTIAL: 'biometric_credential_id',
    },
}))

import { useBiometricAuth } from '@/hooks/useBiometricAuth'

describe('useBiometricAuth', () => {
    let mockStorage: Record<string, string>

    beforeEach(() => {
        mockStorage = {}
        vi.spyOn(localStorage, 'getItem').mockImplementation((key) => mockStorage[key] ?? null)
        vi.spyOn(localStorage, 'setItem').mockImplementation((key, value) => {
            mockStorage[key] = value
        })
        vi.spyOn(localStorage, 'removeItem').mockImplementation((key) => {
            delete mockStorage[key]
        })

        // Mock PublicKeyCredential and navigator.credentials
        ;(window as any).PublicKeyCredential = class {}
        Object.defineProperty(navigator, 'credentials', {
            value: {
                create: vi.fn(),
                get: vi.fn(),
            },
            writable: true,
            configurable: true,
        })

        // Mock crypto.getRandomValues
        vi.spyOn(crypto, 'getRandomValues').mockImplementation((arr: any) => arr)
    })

    afterEach(() => {
        vi.restoreAllMocks()
        delete (window as any).PublicKeyCredential
    })

    it('should detect WebAuthn support', () => {
        const { result } = renderHook(() => useBiometricAuth())
        expect(result.current.isSupported).toBe(true)
    })

    it('should report not registered when no credential in storage', () => {
        const { result } = renderHook(() => useBiometricAuth())
        expect(result.current.isRegistered).toBe(false)
    })

    it('should report registered when credential exists in storage', () => {
        mockStorage['biometric_credential_id'] = 'some-credential'
        const { result } = renderHook(() => useBiometricAuth())
        expect(result.current.isRegistered).toBe(true)
    })

    it('should register a new biometric credential', async () => {
        const mockRawId = new ArrayBuffer(4)
        ;(navigator.credentials.create as ReturnType<typeof vi.fn>).mockResolvedValue({
            rawId: mockRawId,
        })

        const { result } = renderHook(() => useBiometricAuth())

        await act(async () => {
            const success = await result.current.register('user1', 'John Doe')
            expect(success).toBe(true)
        })

        expect(result.current.isRegistered).toBe(true)
        expect(mockStorage['biometric_credential_id']).toBeDefined()
    })

    it('should handle registration cancellation (NotAllowedError)', async () => {
        const error = new Error('User cancelled')
        error.name = 'NotAllowedError'
        ;(navigator.credentials.create as ReturnType<typeof vi.fn>).mockRejectedValue(error)

        const { result } = renderHook(() => useBiometricAuth())

        await act(async () => {
            const success = await result.current.register('user1', 'John')
            expect(success).toBe(false)
        })

        expect(result.current.error).toBe('Autenticação cancelada pelo usuário')
    })

    it('should authenticate with stored credential', async () => {
        mockStorage['biometric_credential_id'] = btoa('test-id')
        ;(navigator.credentials.get as ReturnType<typeof vi.fn>).mockResolvedValue({})

        const { result } = renderHook(() => useBiometricAuth())

        await act(async () => {
            const success = await result.current.authenticate()
            expect(success).toBe(true)
        })

        expect(result.current.isAuthenticating).toBe(false)
    })

    it('should fail authentication when no credential is registered', async () => {
        const { result } = renderHook(() => useBiometricAuth())

        await act(async () => {
            const success = await result.current.authenticate()
            expect(success).toBe(false)
        })

        expect(result.current.error).toBe('Biometria não configurada')
    })

    it('should handle authentication cancellation', async () => {
        mockStorage['biometric_credential_id'] = btoa('test-id')
        const error = new Error('Cancelled')
        error.name = 'NotAllowedError'
        ;(navigator.credentials.get as ReturnType<typeof vi.fn>).mockRejectedValue(error)

        const { result } = renderHook(() => useBiometricAuth())

        await act(async () => {
            const success = await result.current.authenticate()
            expect(success).toBe(false)
        })

        expect(result.current.error).toBe('Autenticação cancelada')
    })

    it('should unregister and remove credential from storage', () => {
        mockStorage['biometric_credential_id'] = 'cred-id'
        const { result } = renderHook(() => useBiometricAuth())

        expect(result.current.isRegistered).toBe(true)

        act(() => {
            result.current.unregister()
        })

        expect(result.current.isRegistered).toBe(false)
        expect(mockStorage['biometric_credential_id']).toBeUndefined()
    })

    it('should set error when WebAuthn is not supported and register is called', async () => {
        delete (window as any).PublicKeyCredential

        const { result } = renderHook(() => useBiometricAuth())

        await act(async () => {
            const success = await result.current.register('u1', 'User')
            expect(success).toBe(false)
        })

        expect(result.current.error).toBe('WebAuthn não suportado neste dispositivo')
    })
})
