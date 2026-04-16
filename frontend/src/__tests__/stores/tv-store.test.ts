import { beforeEach, describe, expect, it } from 'vitest'
import { useTvStore, camerasPerLayout, TV_THEMES } from '@/stores/tv-store'

describe('TV Store', () => {
    beforeEach(() => {
        // Clear persisted state first
        localStorage.removeItem('tv-settings')
        // Reset to defaults
        const store = useTvStore.getState()
        store.setCameraPage(0)
        store.setAutoRotate(false)
        store.setRotationInterval(15)
        store.setSoundAlerts(false)
        store.setShowAlertPanel(false)
        store.setKiosk(false)
        store.setHeaderVisible(true)
        store.setFullscreenAccepted(false)
        store.setTheme('dark')
        store.setDesktopNotifications(false)
        // setLayout last because it resets cameraPage to 0
        store.setLayout('3x2')
    })

    it('has correct default state', () => {
        const state = useTvStore.getState()
        expect(state.layout).toBe('3x2')
        expect(state.autoRotateCameras).toBe(false)
        expect(state.rotationInterval).toBe(15)
        expect(state.soundAlerts).toBe(false)
        expect(state.isKiosk).toBe(false)
        expect(state.theme).toBe('dark')
    })

    it('setLayout changes layout and resets camera page', () => {
        useTvStore.getState().setCameraPage(3)
        useTvStore.getState().setLayout('2x2')
        const state = useTvStore.getState()
        expect(state.layout).toBe('2x2')
        expect(state.cameraPage).toBe(0)
    })

    it('setAutoRotate toggles auto rotation', () => {
        useTvStore.getState().setAutoRotate(true)
        expect(useTvStore.getState().autoRotateCameras).toBe(true)
        useTvStore.getState().setAutoRotate(false)
        expect(useTvStore.getState().autoRotateCameras).toBe(false)
    })

    it('setRotationInterval updates interval', () => {
        useTvStore.getState().setRotationInterval(30)
        expect(useTvStore.getState().rotationInterval).toBe(30)
    })

    it('setTheme changes theme', () => {
        useTvStore.getState().setTheme('matrix')
        expect(useTvStore.getState().theme).toBe('matrix')
        useTvStore.getState().setTheme('ocean')
        expect(useTvStore.getState().theme).toBe('ocean')
    })

    it('nextCameraPage cycles through pages', () => {
        // 3x2 layout = 6 cameras per page
        // 18 total cameras = 3 pages (0, 1, 2)
        useTvStore.getState().setLayout('3x2')
        useTvStore.getState().nextCameraPage(18)
        expect(useTvStore.getState().cameraPage).toBe(1)

        useTvStore.getState().nextCameraPage(18)
        expect(useTvStore.getState().cameraPage).toBe(2)

        // Should wrap around
        useTvStore.getState().nextCameraPage(18)
        expect(useTvStore.getState().cameraPage).toBe(0)
    })

    it('nextCameraPage stays at 0 when all cameras fit', () => {
        useTvStore.getState().setLayout('3x2') // 6 per page
        useTvStore.getState().nextCameraPage(4) // Only 4 cameras
        expect(useTvStore.getState().cameraPage).toBe(0)
    })

    it('nextCameraPage handles map-full layout (falls back to default per page)', () => {
        // map-full has 0 cameras per page in the config, but the implementation
        // uses `|| 6` fallback, treating 0 as falsy and defaulting to 6 per page
        useTvStore.getState().setLayout('map-full')
        useTvStore.getState().nextCameraPage(10)
        // With fallback of 6 per page and 10 cameras: maxPage = ceil(10/6)-1 = 1
        // current page is 0 (from setLayout), so next = 1
        expect(useTvStore.getState().cameraPage).toBe(1)
    })

    it('camerasPerLayout has correct values', () => {
        expect(camerasPerLayout['3x2']).toBe(6)
        expect(camerasPerLayout['2x2']).toBe(4)
        expect(camerasPerLayout['1+list']).toBe(1)
        expect(camerasPerLayout['map-full']).toBe(0)
        expect(camerasPerLayout['cameras-only']).toBe(9)
        expect(camerasPerLayout['focus']).toBe(1)
        expect(camerasPerLayout['4x4']).toBe(16)
    })

    it('TV_THEMES has all expected themes', () => {
        expect(TV_THEMES.dark).toBeDefined()
        expect(TV_THEMES.midnight).toBeDefined()
        expect(TV_THEMES.matrix).toBeDefined()
        expect(TV_THEMES.ocean).toBeDefined()
        expect(TV_THEMES.ember).toBeDefined()
    })

    it('each theme has required color keys', () => {
        const requiredKeys = ['bg', 'bgCard', 'bgHeader', 'border', 'text', 'textMuted', 'accent', 'accentAlt']
        Object.values(TV_THEMES).forEach(theme => {
            requiredKeys.forEach(key => {
                expect(theme).toHaveProperty(key)
            })
        })
    })

    it('setDesktopNotifications toggles notifications', () => {
        useTvStore.getState().setDesktopNotifications(true)
        expect(useTvStore.getState().desktopNotifications).toBe(true)
    })

    it('setKiosk toggles kiosk mode', () => {
        useTvStore.getState().setKiosk(true)
        expect(useTvStore.getState().isKiosk).toBe(true)
    })

    it('setHeaderVisible toggles header', () => {
        useTvStore.getState().setHeaderVisible(false)
        expect(useTvStore.getState().headerVisible).toBe(false)
    })
})
