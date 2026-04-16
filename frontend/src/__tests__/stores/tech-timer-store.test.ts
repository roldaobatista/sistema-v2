import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useTechTimerStore } from '@/stores/tech-timer-store'

describe('Tech Timer Store', () => {
    beforeEach(() => {
        vi.useFakeTimers()
        // Reset store state
        useTechTimerStore.getState().reset()
    })

    afterEach(() => {
        vi.useRealTimers()
    })

    it('has correct initial state', () => {
        const state = useTechTimerStore.getState()
        expect(state.isRunning).toBe(false)
        expect(state.workOrderId).toBeNull()
        expect(state.workOrderNumber).toBeNull()
        expect(state.startedAt).toBeNull()
        expect(state.pausedAt).toBeNull()
        expect(state.accumulatedMs).toBe(0)
    })

    it('starts timer with work order info', () => {
        useTechTimerStore.getState().start(42, 'OS-042')
        const state = useTechTimerStore.getState()
        expect(state.isRunning).toBe(true)
        expect(state.workOrderId).toBe(42)
        expect(state.workOrderNumber).toBe('OS-042')
        expect(state.startedAt).toBeTruthy()
        expect(state.pausedAt).toBeNull()
        expect(state.accumulatedMs).toBe(0)
    })

    it('pauses timer and accumulates elapsed time', () => {
        useTechTimerStore.getState().start(1, 'OS-001')

        // Simulate 5 seconds passing
        vi.advanceTimersByTime(5000)
        useTechTimerStore.getState().pause()

        const state = useTechTimerStore.getState()
        expect(state.isRunning).toBe(false)
        expect(state.pausedAt).toBeTruthy()
        expect(state.accumulatedMs).toBeGreaterThanOrEqual(4000)
        expect(state.startedAt).toBeNull()
    })

    it('resumes timer after pause', () => {
        useTechTimerStore.getState().start(1, 'OS-001')
        vi.advanceTimersByTime(1000)
        useTechTimerStore.getState().pause()
        useTechTimerStore.getState().resume()

        const state = useTechTimerStore.getState()
        expect(state.isRunning).toBe(true)
        expect(state.startedAt).toBeTruthy()
        expect(state.pausedAt).toBeNull()
    })

    it('stops timer and returns duration', () => {
        useTechTimerStore.getState().start(10, 'OS-010')

        // Simulate 10 seconds
        vi.advanceTimersByTime(10000)
        const result = useTechTimerStore.getState().stop()

        expect(result).not.toBeNull()
        expect(result?.workOrderId).toBe(10)
        expect(result?.durationMs).toBeGreaterThanOrEqual(9000)

        // State should be reset
        const state = useTechTimerStore.getState()
        expect(state.isRunning).toBe(false)
        expect(state.workOrderId).toBeNull()
        expect(state.accumulatedMs).toBe(0)
    })

    it('stop returns null when no work order', () => {
        const result = useTechTimerStore.getState().stop()
        expect(result).toBeNull()
    })

    it('resets all state', () => {
        useTechTimerStore.getState().start(5, 'OS-005')
        useTechTimerStore.getState().reset()

        const state = useTechTimerStore.getState()
        expect(state.isRunning).toBe(false)
        expect(state.workOrderId).toBeNull()
        expect(state.workOrderNumber).toBeNull()
        expect(state.startedAt).toBeNull()
        expect(state.pausedAt).toBeNull()
        expect(state.accumulatedMs).toBe(0)
    })

    it('accumulates paused time correctly across pause/resume cycles', () => {
        useTechTimerStore.getState().start(1, 'OS-001')

        // First run: 3 seconds
        vi.advanceTimersByTime(3000)
        useTechTimerStore.getState().pause()
        expect(useTechTimerStore.getState().accumulatedMs).toBeGreaterThanOrEqual(2000)

        // Resume and run 2 more seconds
        useTechTimerStore.getState().resume()
        vi.advanceTimersByTime(2000)
        const result = useTechTimerStore.getState().stop()

        // Total should be ~5 seconds (3 first run + 2 second run)
        expect(result?.durationMs).toBeGreaterThanOrEqual(4000)
    })
})
