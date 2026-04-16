import { useState } from 'react'
import { toast } from 'sonner'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { ArrowRight, Calculator, CheckCircle2 } from 'lucide-react'
import api, { getApiErrorMessage, unwrapData } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import { useAuthStore } from '@/stores/auth-store'
import { CurrencyInputInline } from '@/components/common/CurrencyInput'

type FuelComparisonResult = {
    recommendation: 'ethanol' | 'gasoline'
    recommendation_label: string
    ratio: number
    savings_percent: number
    diesel_price?: number | null
    gasoline_cost_per_km?: number
    ethanol_cost_per_km?: number
    diesel_cost_per_km?: number
}

export function FuelComparisonTab() {
    const _queryClient = useQueryClient()
    const { hasPermission } = useAuthStore()

    const [SearchTerm, _setSearchTerm] = useState('')

    const [gasolinePrice, setGasolinePrice] = useState(0)
    const [ethanolPrice, setEthanolPrice] = useState(0)
    const [dieselPrice, setDieselPrice] = useState(0)
    const [result, setResult] = useState<FuelComparisonResult | null>(null)

    const compareMutation = useMutation({
        mutationFn: (data: { gasoline_price: number; ethanol_price: number; diesel_price: number | null }) =>
            api.post('/fleet/fuel-comparison', data).then(response => unwrapData<FuelComparisonResult>(response)),
        onSuccess: (data) => {
            setResult(data)
            toast.success('Comparacao realizada com sucesso')
        },
        onError: (err: unknown) => {
            toast.error(getApiErrorMessage(err, 'Erro ao comparar combustiveis'))
        },
    })

    const handleCompare = () => {
        if (!gasolinePrice || !ethanolPrice) return
        compareMutation.mutate({
            gasoline_price: gasolinePrice,
            ethanol_price: ethanolPrice,
            diesel_price: dieselPrice || null,
        })
    }

    return (
        <div className="max-w-2xl mx-auto space-y-6">
            <div className="text-center mb-8">
                <div className="h-16 w-16 rounded-2xl bg-brand-100 flex items-center justify-center mx-auto mb-4">
                    <Calculator size={28} className="text-brand-600" />
                </div>
                <h3 className="text-lg font-bold text-surface-900">Calculadora de Combustivel</h3>
                <p className="text-sm text-surface-500 mt-1">Compare etanol, gasolina e diesel para descobrir qual compensa mais</p>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <PriceInput label="Gasolina (R$/L)" value={gasolinePrice} onChange={setGasolinePrice} color="bg-amber-50 border-amber-200" />
                <PriceInput label="Etanol (R$/L)" value={ethanolPrice} onChange={setEthanolPrice} color="bg-emerald-50 border-emerald-200" />
                <PriceInput label="Diesel (R$/L)" value={dieselPrice} onChange={setDieselPrice} color="bg-blue-50 border-blue-200" optional />
            </div>

            <Button
                className="w-full"
                onClick={handleCompare}
                disabled={!gasolinePrice || !ethanolPrice || compareMutation.isPending}
                icon={<ArrowRight size={16} />}
            >
                {compareMutation.isPending ? 'Calculando...' : 'Comparar'}
            </Button>

            {result && (
                <div className="space-y-4 animate-in fade-in slide-in-from-bottom-4 duration-500">
                    <div
                        className={cn(
                            'p-6 rounded-2xl border-2 text-center',
                            result.recommendation === 'ethanol'
                                ? 'bg-emerald-50 border-emerald-300'
                                : 'bg-amber-50 border-amber-300'
                        )}
                    >
                        <CheckCircle2
                            size={32}
                            className={cn('mx-auto mb-3', result.recommendation === 'ethanol' ? 'text-emerald-600' : 'text-amber-600')}
                        />
                        <p className="text-xl font-bold text-surface-900">{result.recommendation_label}</p>
                        <p className="text-sm text-surface-600 mt-2">
                            Razao Etanol/Gasolina: <span className="font-bold">{(result.ratio * 100).toFixed(1)}%</span>
                            {' '}(referencia: 70%)
                        </p>
                        <p className="text-xs text-surface-500 mt-1">
                            Economia estimada de <span className="font-bold">{result.savings_percent}%</span>
                        </p>
                    </div>

                    {result.diesel_price && (
                        <div className="grid grid-cols-3 gap-3">
                            <CostCard label="Gasolina" costPerKm={result.gasoline_cost_per_km} color="text-amber-600" />
                            <CostCard label="Etanol" costPerKm={result.ethanol_cost_per_km} color="text-emerald-600" />
                            <CostCard label="Diesel" costPerKm={result.diesel_cost_per_km} color="text-blue-600" />
                        </div>
                    )}
                </div>
            )}
        </div>
    )
}

function PriceInput({ label, value, onChange, color, optional }: { label: string; value: number; onChange: (v: number) => void; color: string; optional?: boolean }) {
    return (
        <div className={cn('p-4 rounded-xl border', color)}>
            <label className="text-[10px] uppercase font-bold text-surface-500 block mb-2">
                {label} {optional && <span className="text-surface-300">(opcional)</span>}
            </label>
            <CurrencyInputInline
                value={value}
                onChange={onChange}
                className="w-full bg-transparent text-2xl font-bold text-surface-900 outline-none placeholder:text-surface-300"
            />
        </div>
    )
}

function CostCard({ label, costPerKm, color }: { label: string; costPerKm?: number; color: string }) {
    return (
        <div className="p-4 rounded-xl bg-surface-50 border border-default text-center">
            <p className="text-[10px] uppercase font-bold text-surface-400">{label}</p>
            <p className={cn('text-lg font-bold mt-1', color)}>R$ {costPerKm?.toFixed(4)}</p>
            <p className="text-[10px] text-surface-400">/km</p>
        </div>
    )
}
