import { describe, expect, it, vi, beforeEach } from 'vitest'
import userEvent from '@testing-library/user-event'
import { render, screen, waitFor } from '@/__tests__/test-utils'
import QRTracking from '@/components/os/QRTracking'

const { toastSuccess, toastError, writeTextMock } = vi.hoisted(() => ({
    toastSuccess: vi.fn(),
    toastError: vi.fn(),
    writeTextMock: vi.fn(),
}))

vi.mock('sonner', () => ({
    toast: {
        success: toastSuccess,
        error: toastError,
    },
}))

describe('QRTracking', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: {
                writeText: writeTextMock,
            },
        })
    })

    it('gera QR localmente sem depender de imagem externa', () => {
        render(<QRTracking workOrderId={42} osNumber="OS-42" />)

        expect(screen.getByLabelText('QR Code OS OS-42')).toBeInTheDocument()
        expect(document.querySelector('img')).toBeNull()
        expect(document.querySelector('svg')).not.toBeNull()
    })

    it('copia o link de rastreamento', async () => {
        writeTextMock.mockResolvedValue(undefined)
        const user = userEvent.setup()

        render(<QRTracking workOrderId={42} osNumber="OS-42" />)

        await user.click(screen.getByRole('button', { name: /copiar link de rastreamento/i }))

        await waitFor(() => {
            expect(screen.getByRole('button', { name: /copiar link de rastreamento/i })).toHaveTextContent('Copiado!')
        })
        expect(toastSuccess).toHaveBeenCalledWith('Link de rastreamento copiado!')
    })
})
