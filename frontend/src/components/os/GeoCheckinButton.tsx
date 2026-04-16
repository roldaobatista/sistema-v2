import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { workOrderApi } from '@/lib/work-order-api'
import { queryKeys } from '@/lib/query-keys'
import { toast } from 'sonner'
import { MapPin, LogIn, LogOut, Loader2 } from 'lucide-react'
import { getApiErrorMessage } from '@/lib/utils'

interface GeoCheckinButtonProps {
    workOrderId: number
    hasCheckin: boolean
    hasCheckout: boolean
}

export default function GeoCheckinButton({ workOrderId, hasCheckin, hasCheckout }: GeoCheckinButtonProps) {
    const queryClient = useQueryClient()
    const [gettingLocation, setGettingLocation] = useState(false)

    const getPosition = (): Promise<GeolocationPosition> => {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject(new Error('Geolocalização não suportada'))
                return
            }
            navigator.geolocation.getCurrentPosition(resolve, reject, {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0,
            })
        })
    }

    const checkinMutation = useMutation({
        mutationFn: async () => {
            setGettingLocation(true)
            const position = await getPosition()
            setGettingLocation(false)
            return workOrderApi.checkin(workOrderId, {
                lat: position.coords.latitude,
                lng: position.coords.longitude,
            })
        },
        onSuccess: () => {
            toast.success('Check-in realizado com sucesso')
            queryClient.invalidateQueries({ queryKey: queryKeys.workOrders.detail(workOrderId) })
            queryClient.invalidateQueries({ queryKey: queryKeys.workOrders.all })
            queryClient.invalidateQueries({ queryKey: ['wo-execution-timeline', workOrderId] })
        },
        onError: (err: unknown) => {
            setGettingLocation(false)
            toast.error(getApiErrorMessage(err, 'Erro ao realizar check-in'))
        },
    })

    const checkoutMutation = useMutation({
        mutationFn: async () => {
            setGettingLocation(true)
            const position = await getPosition()
            setGettingLocation(false)
            return workOrderApi.checkout(workOrderId, {
                lat: position.coords.latitude,
                lng: position.coords.longitude,
            })
        },
        onSuccess: () => {
            toast.success('Check-out realizado com sucesso')
            queryClient.invalidateQueries({ queryKey: queryKeys.workOrders.detail(workOrderId) })
            queryClient.invalidateQueries({ queryKey: queryKeys.workOrders.all })
            queryClient.invalidateQueries({ queryKey: ['wo-execution-timeline', workOrderId] })
        },
        onError: (err: unknown) => {
            setGettingLocation(false)
            toast.error(getApiErrorMessage(err, 'Erro ao realizar check-out'))
        },
    })

    const isLoading = checkinMutation.isPending || checkoutMutation.isPending || gettingLocation

    if (hasCheckout) {
        return (
            <div className="flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                <MapPin className="h-4 w-4" />
                Check-in/out concluído
            </div>
        )
    }

    if (hasCheckin) {
        return (
            <button
                onClick={() => checkoutMutation.mutate()}
                disabled={isLoading}
                className="flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
            >
                {isLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : <LogOut className="h-4 w-4" />}
                {gettingLocation ? 'Obtendo localização...' : isLoading ? 'Processando...' : 'Check-out'}
            </button>
        )
    }

    return (
        <button
            onClick={() => checkinMutation.mutate()}
            disabled={isLoading}
            className="flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
        >
            {isLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : <LogIn className="h-4 w-4" />}
            {gettingLocation ? 'Obtendo localização...' : isLoading ? 'Processando...' : 'Check-in'}
        </button>
    )
}
