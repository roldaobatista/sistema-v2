import { describe, it, expect, vi, afterEach } from 'vitest'
import { render, act } from '@testing-library/react'
import React from 'react'
import { usePullToRefresh } from '@/hooks/usePullToRefresh'

// Helper component that wires the containerRef to a real DOM element
function TestHarness({
    onRefresh,
    threshold,
    disabled,
    onResult,
}: {
    onRefresh: () => Promise<void>
    threshold?: number
    disabled?: boolean
    onResult: (result: ReturnType<typeof usePullToRefresh>) => void
}) {
    const hookResult = usePullToRefresh({ onRefresh, threshold, disabled })
    // Expose result to test via callback
    React.useEffect(() => {
        onResult(hookResult)
    })
    return React.createElement('div', { ref: hookResult.containerRef, 'data-testid': 'container' })
}

describe('usePullToRefresh', () => {
    afterEach(() => {
        vi.restoreAllMocks()
    })

    function renderWithContainer(opts: { onRefresh: () => Promise<void>; threshold?: number; disabled?: boolean }) {
        let latestResult: ReturnType<typeof usePullToRefresh> = null as any
        const rendered = render(
            React.createElement(TestHarness, {
                ...opts,
                onResult: (r) => { latestResult = r },
            }),
        )
        // Use the actual ref element (same one the hook attaches listeners to)
        const getContainer = () => {
            const el = latestResult.containerRef.current!
            // Ensure scrollTop is 0 so the handler doesn't bail out
            if (el && !Object.getOwnPropertyDescriptor(el, 'scrollTop')) {
                Object.defineProperty(el, 'scrollTop', { value: 0, writable: true, configurable: true })
            }
            return el
        }
        return { getResult: () => latestResult, getContainer, ...rendered }
    }

    it('should return containerRef, isRefreshing, pullDistance, isPulling', () => {
        const onRefresh = vi.fn().mockResolvedValue(undefined)
        const { getResult } = renderWithContainer({ onRefresh })
        const result = getResult()

        expect(result).toHaveProperty('containerRef')
        expect(result).toHaveProperty('isRefreshing')
        expect(result).toHaveProperty('pullDistance')
        expect(result).toHaveProperty('isPulling')
    })

    it('should start with isRefreshing false and pullDistance 0', () => {
        const onRefresh = vi.fn().mockResolvedValue(undefined)
        const { getResult } = renderWithContainer({ onRefresh })
        const result = getResult()

        expect(result.isRefreshing).toBe(false)
        expect(result.pullDistance).toBe(0)
        expect(result.isPulling).toBe(false)
    })

    it('should set isPulling to true when pullDistance > 0', () => {
        const onRefresh = vi.fn().mockResolvedValue(undefined)
        const { getResult, getContainer } = renderWithContainer({ onRefresh })
        const container = getContainer()

        act(() => {
            container.dispatchEvent(
                new TouchEvent('touchstart', {
                    touches: [{ clientX: 0, clientY: 100 } as Touch],
                    bubbles: true,
                }),
            )
        })

        act(() => {
            container.dispatchEvent(
                new TouchEvent('touchmove', {
                    touches: [{ clientX: 0, clientY: 300 } as Touch],
                    bubbles: true,
                }),
            )
        })

        // pullDistance is deltaY * 0.5 = 100
        expect(getResult().pullDistance).toBeGreaterThan(0)
        expect(getResult().isPulling).toBe(true)
    })

    it('should not trigger when disabled', () => {
        const onRefresh = vi.fn().mockResolvedValue(undefined)
        const { getResult, getContainer } = renderWithContainer({ onRefresh, disabled: true })
        const container = getContainer()

        act(() => {
            container.dispatchEvent(
                new TouchEvent('touchstart', {
                    touches: [{ clientX: 0, clientY: 100 } as Touch],
                    bubbles: true,
                }),
            )
        })

        act(() => {
            container.dispatchEvent(
                new TouchEvent('touchmove', {
                    touches: [{ clientX: 0, clientY: 300 } as Touch],
                    bubbles: true,
                }),
            )
        })

        expect(getResult().pullDistance).toBe(0)
    })

    it('should call onRefresh when pull exceeds threshold on touchend', async () => {
        const onRefresh = vi.fn().mockResolvedValue(undefined)
        const { getContainer } = renderWithContainer({ onRefresh, threshold: 80 })
        const container = getContainer()

        act(() => {
            container.dispatchEvent(
                new TouchEvent('touchstart', {
                    touches: [{ clientX: 0, clientY: 100 } as Touch],
                    bubbles: true,
                }),
            )
        })

        act(() => {
            container.dispatchEvent(
                new TouchEvent('touchmove', {
                    touches: [{ clientX: 0, clientY: 300 } as Touch], // distance = 200*0.5 = 100 > threshold 80
                    bubbles: true,
                }),
            )
        })

        await act(async () => {
            container.dispatchEvent(new TouchEvent('touchend', { changedTouches: [], bubbles: true }))
        })

        expect(onRefresh).toHaveBeenCalledTimes(1)
    })

    it('should not call onRefresh when pull is below threshold', async () => {
        const onRefresh = vi.fn().mockResolvedValue(undefined)
        const { getContainer } = renderWithContainer({ onRefresh, threshold: 80 })
        const container = getContainer()

        act(() => {
            container.dispatchEvent(
                new TouchEvent('touchstart', {
                    touches: [{ clientX: 0, clientY: 100 } as Touch],
                    bubbles: true,
                }),
            )
        })

        act(() => {
            container.dispatchEvent(
                new TouchEvent('touchmove', {
                    touches: [{ clientX: 0, clientY: 150 } as Touch], // distance = 50*0.5 = 25 < threshold 80
                    bubbles: true,
                }),
            )
        })

        await act(async () => {
            container.dispatchEvent(new TouchEvent('touchend', { changedTouches: [], bubbles: true }))
        })

        expect(onRefresh).not.toHaveBeenCalled()
    })

    it('should reset pullDistance after touchend', async () => {
        const onRefresh = vi.fn().mockResolvedValue(undefined)
        const { getResult, getContainer } = renderWithContainer({ onRefresh })
        const container = getContainer()

        act(() => {
            container.dispatchEvent(
                new TouchEvent('touchstart', {
                    touches: [{ clientX: 0, clientY: 100 } as Touch],
                    bubbles: true,
                }),
            )
        })

        act(() => {
            container.dispatchEvent(
                new TouchEvent('touchmove', {
                    touches: [{ clientX: 0, clientY: 200 } as Touch],
                    bubbles: true,
                }),
            )
        })

        await act(async () => {
            container.dispatchEvent(new TouchEvent('touchend', { changedTouches: [], bubbles: true }))
        })

        expect(getResult().pullDistance).toBe(0)
    })
})
