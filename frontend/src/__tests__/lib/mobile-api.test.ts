import { beforeEach, describe, expect, it, vi } from 'vitest'

const { mockApi } = vi.hoisted(() => ({
    mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn() },
}))

vi.mock('@/lib/api', () => ({
    default: mockApi,
}))

import { mobileApi } from '@/lib/mobile-api'

describe('mobileApi route contract', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('usa rotas reais do backend para assinatura, barcode, voz e foto', async () => {
        const formData = new FormData()

        await mobileApi.signature.store(formData)
        expect(mockApi.post).toHaveBeenCalledWith('/mobile/signatures', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })

        await mobileApi.barcode.lookup('TAG-001')
        expect(mockApi.get).toHaveBeenCalledWith('/mobile/barcode-lookup', { params: { code: 'TAG-001' } })

        await mobileApi.voiceReport.store(formData)
        expect(mockApi.post).toHaveBeenCalledWith('/mobile/voice-reports', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })

        await mobileApi.photoAnnotation.store(formData)
        expect(mockApi.post).toHaveBeenCalledWith('/mobile/photo-annotations', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
    })

    it('usa a rota real de configuracao biometrica', async () => {
        await mobileApi.biometric.config()
        expect(mockApi.get).toHaveBeenCalledWith('/mobile/biometric-config')

        const payload = { enabled: true }
        await mobileApi.biometric.update(payload)
        expect(mockApi.put).toHaveBeenCalledWith('/mobile/biometric-config', payload)
    })
})
