import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { renderHook, act } from '@testing-library/react'
import { useBarcodeScanner } from '@/hooks/useBarcodeScanner'

describe('useBarcodeScanner', () => {
    let mockGetUserMedia: ReturnType<typeof vi.fn>
    let mockDetect: ReturnType<typeof vi.fn>

    beforeEach(() => {
        mockDetect = vi.fn().mockResolvedValue([])
        mockGetUserMedia = vi.fn().mockResolvedValue({
            getTracks: () => [{ stop: vi.fn() }],
        })

        Object.defineProperty(navigator, 'mediaDevices', {
            value: { getUserMedia: mockGetUserMedia },
            writable: true,
            configurable: true,
        })

        ;(window as any).BarcodeDetector = class {
            detect = mockDetect
        }

        vi.spyOn(window, 'requestAnimationFrame').mockImplementation((cb) => {
            // Don't auto-call to prevent infinite loops
            return 1
        })
        vi.spyOn(window, 'cancelAnimationFrame').mockImplementation(() => {})
    })

    afterEach(() => {
        vi.restoreAllMocks()
        delete (window as any).BarcodeDetector
    })

    it('should detect BarcodeDetector support', () => {
        const { result } = renderHook(() => useBarcodeScanner())
        expect(result.current.isSupported).toBe(true)
    })

    it('should report unsupported when BarcodeDetector is absent', () => {
        delete (window as any).BarcodeDetector
        const { result } = renderHook(() => useBarcodeScanner())
        expect(result.current.isSupported).toBe(false)
    })

    it('should have initial state with no scanning', () => {
        const { result } = renderHook(() => useBarcodeScanner())
        expect(result.current.isScanning).toBe(false)
        expect(result.current.lastResult).toBeNull()
        expect(result.current.error).toBeNull()
    })

    it('should set error when camera access fails', async () => {
        mockGetUserMedia.mockRejectedValue(new Error('NotAllowedError'))
        const { result } = renderHook(() => useBarcodeScanner())

        const video = document.createElement('video')
        video.play = vi.fn().mockResolvedValue(undefined)

        await act(async () => {
            await result.current.startScanning(video)
        })

        expect(result.current.error).toBe('NotAllowedError')
        expect(result.current.isScanning).toBe(false)
    })

    it('should allow manual input as fallback', () => {
        const { result } = renderHook(() => useBarcodeScanner())

        act(() => {
            result.current.manualInput('1234567890123')
        })

        expect(result.current.lastResult).not.toBeNull()
        expect(result.current.lastResult!.rawValue).toBe('1234567890123')
        expect(result.current.lastResult!.format).toBe('manual')
    })

    it('should clear result when clearResult is called', () => {
        const { result } = renderHook(() => useBarcodeScanner())

        act(() => {
            result.current.manualInput('test')
        })
        expect(result.current.lastResult).not.toBeNull()

        act(() => {
            result.current.clearResult()
        })
        expect(result.current.lastResult).toBeNull()
    })

    it('should stop scanning and clean up tracks', async () => {
        const stopTrack = vi.fn()
        mockGetUserMedia.mockResolvedValue({
            getTracks: () => [{ stop: stopTrack }],
        })

        const { result } = renderHook(() => useBarcodeScanner())
        const video = document.createElement('video')
        video.play = vi.fn().mockResolvedValue(undefined)

        await act(async () => {
            await result.current.startScanning(video)
        })

        act(() => {
            result.current.stopScanning()
        })

        expect(result.current.isScanning).toBe(false)
        expect(stopTrack).toHaveBeenCalled()
    })

    it('should clean up on unmount', async () => {
        const stopTrack = vi.fn()
        mockGetUserMedia.mockResolvedValue({
            getTracks: () => [{ stop: stopTrack }],
        })

        const { result, unmount } = renderHook(() => useBarcodeScanner())
        const video = document.createElement('video')
        video.play = vi.fn().mockResolvedValue(undefined)

        await act(async () => {
            await result.current.startScanning(video)
        })

        unmount()
        expect(stopTrack).toHaveBeenCalled()
    })

    it('should include timestamp in manual input result', () => {
        vi.spyOn(Date, 'now').mockReturnValue(1234567890)
        const { result } = renderHook(() => useBarcodeScanner())

        act(() => {
            result.current.manualInput('test-barcode')
        })

        expect(result.current.lastResult!.timestamp).toBe(1234567890)
    })
})
