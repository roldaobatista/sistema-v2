import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { renderHook } from '@testing-library/react'
import { useSwipeGesture } from '@/hooks/useSwipeGesture'
import React from 'react'

describe('useSwipeGesture', () => {
    let element: HTMLDivElement

    beforeEach(() => {
        element = document.createElement('div')
        document.body.appendChild(element)
        vi.spyOn(Date, 'now').mockReturnValue(0)
        Object.defineProperty(window, 'scrollY', { value: 0, writable: true, configurable: true })
    })

    afterEach(() => {
        document.body.removeChild(element)
        vi.restoreAllMocks()
    })

    function createRef(): React.RefObject<HTMLElement> {
        return { current: element } as React.RefObject<HTMLElement>
    }

    function simulateSwipe(
        el: HTMLElement,
        startX: number,
        startY: number,
        endX: number,
        endY: number,
        elapsedMs = 200,
    ) {
        let time = 0
        vi.spyOn(Date, 'now').mockImplementation(() => time)

        el.dispatchEvent(
            new TouchEvent('touchstart', {
                touches: [{ clientX: startX, clientY: startY } as Touch],
            }),
        )

        time = elapsedMs

        el.dispatchEvent(
            new TouchEvent('touchend', {
                changedTouches: [{ clientX: endX, clientY: endY } as Touch],
            }),
        )
    }

    it('should call onSwipeLeft for left swipe', () => {
        const onSwipeLeft = vi.fn()
        renderHook(() => useSwipeGesture(createRef(), { onSwipeLeft }))

        simulateSwipe(element, 200, 100, 50, 100) // dx = -150 > threshold 80
        expect(onSwipeLeft).toHaveBeenCalledTimes(1)
    })

    it('should call onSwipeRight for right swipe', () => {
        const onSwipeRight = vi.fn()
        renderHook(() => useSwipeGesture(createRef(), { onSwipeRight }))

        simulateSwipe(element, 50, 100, 200, 100) // dx = 150 > threshold 80
        expect(onSwipeRight).toHaveBeenCalledTimes(1)
    })

    it('should call onSwipeDown for downward swipe at top of page', () => {
        const onSwipeDown = vi.fn()
        renderHook(() => useSwipeGesture(createRef(), { onSwipeDown }))

        simulateSwipe(element, 100, 0, 100, 200) // dy = 200 > threshold 80
        expect(onSwipeDown).toHaveBeenCalledTimes(1)
    })

    it('should not call onSwipeDown when not at top of page', () => {
        Object.defineProperty(window, 'scrollY', { value: 100, writable: true, configurable: true })
        const onSwipeDown = vi.fn()
        renderHook(() => useSwipeGesture(createRef(), { onSwipeDown }))

        simulateSwipe(element, 100, 0, 100, 200)
        expect(onSwipeDown).not.toHaveBeenCalled()
    })

    it('should not trigger when swipe is too slow (>500ms)', () => {
        const onSwipeLeft = vi.fn()
        renderHook(() => useSwipeGesture(createRef(), { onSwipeLeft }))

        simulateSwipe(element, 200, 100, 50, 100, 600) // elapsed 600ms
        expect(onSwipeLeft).not.toHaveBeenCalled()
    })

    it('should not trigger when distance is below threshold', () => {
        const onSwipeLeft = vi.fn()
        renderHook(() => useSwipeGesture(createRef(), { onSwipeLeft, threshold: 80 }))

        simulateSwipe(element, 200, 100, 150, 100) // dx = -50 < threshold 80
        expect(onSwipeLeft).not.toHaveBeenCalled()
    })

    it('should respect custom threshold', () => {
        const onSwipeLeft = vi.fn()
        renderHook(() => useSwipeGesture(createRef(), { onSwipeLeft, threshold: 30 }))

        simulateSwipe(element, 200, 100, 150, 100) // dx = -50 > threshold 30
        expect(onSwipeLeft).toHaveBeenCalledTimes(1)
    })

    it('should not trigger when enabled is false', () => {
        const onSwipeLeft = vi.fn()
        renderHook(() => useSwipeGesture(createRef(), { onSwipeLeft, enabled: false }))

        simulateSwipe(element, 200, 100, 50, 100)
        expect(onSwipeLeft).not.toHaveBeenCalled()
    })

    it('should prefer horizontal swipe when dx > dy', () => {
        const onSwipeLeft = vi.fn()
        const onSwipeDown = vi.fn()
        renderHook(() => useSwipeGesture(createRef(), { onSwipeLeft, onSwipeDown }))

        // dx = -150, dy = 50 -> horizontal wins
        simulateSwipe(element, 250, 100, 100, 150)
        expect(onSwipeLeft).toHaveBeenCalledTimes(1)
        expect(onSwipeDown).not.toHaveBeenCalled()
    })
})
