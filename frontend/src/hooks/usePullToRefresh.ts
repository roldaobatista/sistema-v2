import { useRef, useCallback, useEffect, useState } from 'react'

interface PullToRefreshOptions {
    onRefresh: () => Promise<void>
    threshold?: number
    disabled?: boolean
}

export function usePullToRefresh({ onRefresh, threshold = 80, disabled = false }: PullToRefreshOptions) {
    const [isRefreshing, setIsRefreshing] = useState(false)
    const [pullDistance, setPullDistance] = useState(0)
    const startY = useRef(0)
    const pullDistanceRef = useRef(0)
    const containerRef = useRef<HTMLDivElement>(null)
    const onRefreshRef = useRef(onRefresh)
    const disabledRef = useRef(disabled)
    const isRefreshingRef = useRef(false)
    const mountedRef = useRef(true)

    // Keep refs in sync
    useEffect(() => { onRefreshRef.current = onRefresh }, [onRefresh])
    useEffect(() => { disabledRef.current = disabled }, [disabled])
    useEffect(() => { isRefreshingRef.current = isRefreshing }, [isRefreshing])
    useEffect(() => {
        mountedRef.current = true
        return () => { mountedRef.current = false }
    }, [])

    const handleTouchStart = useCallback((e: TouchEvent) => {
        if (disabledRef.current || isRefreshingRef.current) return
        const container = containerRef.current
        if (!container || container.scrollTop > 0) return
        startY.current = e.touches[0].clientY
    }, [])

    const handleTouchMove = useCallback((e: TouchEvent) => {
        if (disabledRef.current || isRefreshingRef.current || !startY.current) return
        const container = containerRef.current
        if (!container || container.scrollTop > 0) return

        const deltaY = e.touches[0].clientY - startY.current
        if (deltaY > 0) {
            const distance = Math.min(deltaY * 0.5, threshold * 1.5)
            pullDistanceRef.current = distance
            setPullDistance(distance)
        }
    }, [threshold])

    const handleTouchEnd = useCallback(async () => {
        if (disabledRef.current || isRefreshingRef.current) return
        if (pullDistanceRef.current >= threshold) {
            isRefreshingRef.current = true
            setIsRefreshing(true)
            try {
                await onRefreshRef.current()
            } finally {
                isRefreshingRef.current = false
                if (mountedRef.current) setIsRefreshing(false)
            }
        }
        pullDistanceRef.current = 0
        if (mountedRef.current) setPullDistance(0)
        startY.current = 0
    }, [threshold])

    useEffect(() => {
        const container = containerRef.current
        if (!container) return

        container.addEventListener('touchstart', handleTouchStart, { passive: true })
        container.addEventListener('touchmove', handleTouchMove, { passive: true })
        container.addEventListener('touchend', handleTouchEnd, { passive: true })

        return () => {
            container.removeEventListener('touchstart', handleTouchStart)
            container.removeEventListener('touchmove', handleTouchMove)
            container.removeEventListener('touchend', handleTouchEnd)
        }
    }, [handleTouchStart, handleTouchMove, handleTouchEnd])

    return { containerRef, isRefreshing, pullDistance, isPulling: pullDistance > 0 }
}
