export {}

declare global {
    interface BluetoothRemoteGATTCharacteristic {
        properties: {
            write: boolean
            writeWithoutResponse: boolean
        }
        writeValue(data: BufferSource): Promise<void>
        writeValueWithoutResponse(data: BufferSource): Promise<void>
    }

    interface BluetoothRemoteGATTService {
        getCharacteristics(): Promise<BluetoothRemoteGATTCharacteristic[]>
    }

    interface BluetoothRemoteGATTServer {
        connected: boolean
        connect(): Promise<BluetoothRemoteGATTServer>
        disconnect(): void
        getPrimaryService(service: string): Promise<BluetoothRemoteGATTService>
    }

    interface BluetoothDevice extends EventTarget {
        name?: string
        gatt?: BluetoothRemoteGATTServer
        addEventListener(
            type: 'gattserverdisconnected',
            listener: EventListenerOrEventListenerObject
        ): void
    }

    interface RequestDeviceFilter {
        services?: string[]
        name?: string
        namePrefix?: string
    }

    interface RequestDeviceOptions {
        filters?: RequestDeviceFilter[]
        optionalServices?: string[]
        acceptAllDevices?: boolean
    }

    interface Bluetooth {
        requestDevice(options?: RequestDeviceOptions): Promise<BluetoothDevice>
    }

    interface Navigator {
        bluetooth: Bluetooth
        wakeLock?: WakeLock
        connection?: NetworkInformation
        mozConnection?: NetworkInformation
        webkitConnection?: NetworkInformation
    }

    interface WakeLock {
        request(type: 'screen'): Promise<WakeLockSentinel>
    }

    interface WakeLockSentinel extends EventTarget {
        readonly released: boolean
        readonly type: 'screen'
        release(): Promise<void>
    }

    interface NetworkInformation extends EventTarget {
        readonly effectiveType?: string
        readonly type?: string
        readonly downlink?: number
        readonly rtt?: number
        readonly saveData?: boolean
        addEventListener(type: 'change', listener: EventListener): void
        removeEventListener(type: 'change', listener: EventListener): void
    }

    interface DetectedBarcode {
        rawValue: string
        format: string
        boundingBox: DOMRectReadOnly
        cornerPoints: { x: number; y: number }[]
    }

    class BarcodeDetector {
        constructor(options?: { formats: string[] })
        detect(source: ImageBitmapSource): Promise<DetectedBarcode[]>
        static getSupportedFormats(): Promise<string[]>
    }

    interface Window {
        BarcodeDetector?: typeof BarcodeDetector
    }

    interface ScreenOrientation {
        lock?(orientation: string): Promise<void>
    }

    interface SyncManager {
        register(tag: string): Promise<void>
    }

    interface ServiceWorkerRegistration {
        sync: SyncManager
    }
}
