import { useState, useCallback } from 'react'
import { STORAGE_KEYS } from '@/lib/constants'

interface BiometricState {
    isSupported: boolean
    isRegistered: boolean
    isAuthenticating: boolean
    error: string | null
}

function base64ToArrayBuffer(base64: string): ArrayBuffer {
    const binary = atob(base64)
    const bytes = new Uint8Array(binary.length)
    for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i)
    return bytes.buffer
}

function arrayBufferToBase64(buffer: ArrayBuffer): string {
    const bytes = new Uint8Array(buffer)
    let binary = ''
    for (let i = 0; i < bytes.byteLength; i++) binary += String.fromCharCode(bytes[i])
    return btoa(binary)
}

export function useBiometricAuth() {
    const [state, setState] = useState<BiometricState>(() => ({
        isSupported: typeof window !== 'undefined'
            && !!window.PublicKeyCredential
            && !!navigator.credentials,
        isRegistered: !!localStorage.getItem(STORAGE_KEYS.BIOMETRIC_CREDENTIAL),
        isAuthenticating: false,
        error: null,
    }))

    const register = useCallback(async (userId: string, userName: string) => {
        if (!state.isSupported) {
            setState(s => ({ ...s, error: 'WebAuthn não suportado neste dispositivo' }))
            return false
        }

        setState(s => ({ ...s, isAuthenticating: true, error: null }))

        try {
            const challenge = crypto.getRandomValues(new Uint8Array(32))

            const credential = await navigator.credentials.create({
                publicKey: {
                    challenge,
                    rp: {
                        name: 'Kalibrium',
                        id: window.location.hostname,
                    },
                    user: {
                        id: new TextEncoder().encode(userId),
                        name: userName,
                        displayName: userName,
                    },
                    pubKeyCredParams: [
                        { alg: -7, type: 'public-key' },   // ES256
                        { alg: -257, type: 'public-key' },  // RS256
                    ],
                    authenticatorSelection: {
                        authenticatorAttachment: 'platform',
                        userVerification: 'required',
                        residentKey: 'preferred',
                    },
                    timeout: 60_000,
                    attestation: 'none',
                },
            }) as PublicKeyCredential

            const credentialId = arrayBufferToBase64(credential.rawId)
            localStorage.setItem(STORAGE_KEYS.BIOMETRIC_CREDENTIAL, credentialId)

            setState(s => ({ ...s, isRegistered: true, isAuthenticating: false }))
            return true
        } catch (err: unknown) {
            const e = err instanceof DOMException ? err : err instanceof Error ? err : null
            setState(s => ({
                ...s,
                isAuthenticating: false,
                error: e?.name === 'NotAllowedError'
                    ? 'Autenticação cancelada pelo usuário'
                    : e?.message || 'Erro ao registrar biometria',
            }))
            return false
        }
    }, [state.isSupported])

    const authenticate = useCallback(async () => {
        const credentialId = localStorage.getItem(STORAGE_KEYS.BIOMETRIC_CREDENTIAL)
        if (!credentialId || !state.isSupported) {
            setState(s => ({ ...s, error: 'Biometria não configurada' }))
            return false
        }

        setState(s => ({ ...s, isAuthenticating: true, error: null }))

        try {
            const challenge = crypto.getRandomValues(new Uint8Array(32))

            await navigator.credentials.get({
                publicKey: {
                    challenge,
                    allowCredentials: [{
                        id: base64ToArrayBuffer(credentialId),
                        type: 'public-key',
                        transports: ['internal'],
                    }],
                    userVerification: 'required',
                    timeout: 60_000,
                },
            })

            setState(s => ({ ...s, isAuthenticating: false }))
            return true
        } catch (err: unknown) {
            const e = err instanceof DOMException ? err : err instanceof Error ? err : null
            setState(s => ({
                ...s,
                isAuthenticating: false,
                error: e?.name === 'NotAllowedError'
                    ? 'Autenticação cancelada'
                    : e?.message || 'Falha na autenticação biométrica',
            }))
            return false
        }
    }, [state.isSupported])

    const unregister = useCallback(() => {
        localStorage.removeItem(STORAGE_KEYS.BIOMETRIC_CREDENTIAL)
        setState(s => ({ ...s, isRegistered: false }))
    }, [])

    return {
        ...state,
        register,
        authenticate,
        unregister,
    }
}
