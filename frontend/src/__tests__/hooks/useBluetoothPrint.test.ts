import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { renderHook, act } from '@testing-library/react'
import { useBluetoothPrint } from '@/hooks/useBluetoothPrint'

describe('useBluetoothPrint', () => {
    const mockWriteValue = vi.fn().mockResolvedValue(undefined)
    const mockWriteValueWithoutResponse = vi.fn().mockResolvedValue(undefined)
    let mockRequestDevice: ReturnType<typeof vi.fn>

    function createMockDevice(name = 'TestPrinter') {
        return {
            name,
            gatt: {
                connected: true,
                connect: vi.fn().mockResolvedValue({
                    getPrimaryService: vi.fn().mockResolvedValue({
                        getCharacteristics: vi.fn().mockResolvedValue([
                            {
                                properties: { write: true, writeWithoutResponse: false },
                                writeValue: mockWriteValue,
                                writeValueWithoutResponse: mockWriteValueWithoutResponse,
                            },
                        ]),
                    }),
                }),
                disconnect: vi.fn(),
            },
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        }
    }

    beforeEach(() => {
        mockWriteValue.mockClear()
        mockWriteValueWithoutResponse.mockClear()
        mockRequestDevice = vi.fn()

        Object.defineProperty(navigator, 'bluetooth', {
            value: { requestDevice: mockRequestDevice },
            writable: true,
            configurable: true,
        })
    })

    afterEach(() => {
        vi.restoreAllMocks()
    })

    it('should detect bluetooth support', () => {
        const { result } = renderHook(() => useBluetoothPrint())
        expect(result.current.isSupported).toBe(true)
    })

    it('should start in disconnected state', () => {
        const { result } = renderHook(() => useBluetoothPrint())
        expect(result.current.isConnected).toBe(false)
        expect(result.current.isConnecting).toBe(false)
        expect(result.current.deviceName).toBeNull()
    })

    it('should connect to a bluetooth printer', async () => {
        const device = createMockDevice('MyPrinter')
        mockRequestDevice.mockResolvedValue(device)

        const { result } = renderHook(() => useBluetoothPrint())

        await act(async () => {
            const success = await result.current.connect()
            expect(success).toBe(true)
        })

        expect(result.current.isConnected).toBe(true)
        expect(result.current.deviceName).toBe('MyPrinter')
    })

    it('should handle user cancellation (NotFoundError)', async () => {
        const error = new Error('No device selected')
        error.name = 'NotFoundError'
        mockRequestDevice.mockRejectedValue(error)

        const { result } = renderHook(() => useBluetoothPrint())

        await act(async () => {
            const success = await result.current.connect()
            expect(success).toBe(false)
        })

        expect(result.current.error).toBe('Nenhuma impressora selecionada')
    })

    it('should handle connection errors', async () => {
        mockRequestDevice.mockRejectedValue(new Error('Connection failed'))

        const { result } = renderHook(() => useBluetoothPrint())

        await act(async () => {
            const success = await result.current.connect()
            expect(success).toBe(false)
        })

        expect(result.current.error).toBe('Connection failed')
    })

    it('should disconnect from printer', async () => {
        const device = createMockDevice()
        mockRequestDevice.mockResolvedValue(device)

        const { result } = renderHook(() => useBluetoothPrint())

        await act(async () => {
            await result.current.connect()
        })

        await act(async () => {
            await result.current.disconnect()
        })

        expect(result.current.isConnected).toBe(false)
        expect(result.current.deviceName).toBeNull()
    })

    it('should set error when sendRaw is called without connection', async () => {
        const { result } = renderHook(() => useBluetoothPrint())

        await act(async () => {
            const success = await result.current.sendRaw('test data')
            expect(success).toBe(false)
        })

        expect(result.current.error).toBe('Impressora não conectada')
    })

    it('should set error when bluetooth is not supported', async () => {
        // Must delete the property so 'bluetooth' in navigator returns false
        delete (navigator as any).bluetooth

        // Need to re-render since isSupported is checked at init
        const { result } = renderHook(() => useBluetoothPrint())

        await act(async () => {
            const success = await result.current.connect()
            expect(success).toBe(false)
        })

        expect(result.current.error).toBe('Bluetooth não suportado neste navegador')
    })

    it('should set isPrinting during printReceipt and reset after', async () => {
        const device = createMockDevice()
        mockRequestDevice.mockResolvedValue(device)

        const { result } = renderHook(() => useBluetoothPrint())

        await act(async () => {
            await result.current.connect()
        })

        expect(result.current.isPrinting).toBe(false)

        await act(async () => {
            await result.current.printReceipt({
                title: 'Test Receipt',
                items: [{ label: 'Item 1', value: 'R$ 10.00' }],
                footer: 'Thank you!',
            })
        })

        expect(result.current.isPrinting).toBe(false)
    })
})
