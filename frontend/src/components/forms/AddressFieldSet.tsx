import React from 'react'
import { Loader2 } from 'lucide-react'
import { useViaCep } from '@/hooks/useViaCep'
import { useIbgeStates, useIbgeCities } from '@/hooks/useIbge'
import { Input } from '@/components/ui/input'
import api from '@/lib/api'
import { maskCep } from '@/lib/form-masks'

export interface AddressData {
    address_zip: string
    address_street: string
    address_number: string
    address_complement: string
    address_neighborhood: string
    address_city: string
    address_state: string
}

interface AddressFieldSetProps {
    value: Partial<AddressData>
    onChange: (key: keyof AddressData, val: string) => void
}

export function AddressFieldSet({ value, onChange }: AddressFieldSetProps) {
    const viaCep = useViaCep()
    const { data: ibgeStates = [], isError: statesError, refetch: refetchStates } = useIbgeStates()
    const { data: ibgeCities = [] } = useIbgeCities(value.address_state || '')

    async function handleCepBlur() {
        const cepDigits = (value.address_zip || '').replace(/\D/g, '')
        if (cepDigits.length !== 8) return

        const result = await viaCep.lookup(cepDigits)
        if (result) {
            const uf = (result.state || '').trim().toUpperCase().slice(0, 2)
            let city = (result.city || '').trim()

            if (uf.length === 2) {
                try {
                    const { data: cities } = await api.get<{ id: number; name: string }[]>(`/external/states/${uf}/cities`)
                    if (cities?.length && city) {
                        const normalize = (s: string) => s.toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu, '')
                        const match = cities.find(c => normalize(c.name) === normalize(city)) ||
                                     cities.find(c => normalize(c.name).includes(normalize(city)) || normalize(city).includes(normalize(c.name)))
                        if (match) city = match.name
                    }
                } catch {
                    // fallback behavior
                }
            }
            if (result.street) onChange('address_street', result.street)
            if (result.neighborhood) onChange('address_neighborhood', result.neighborhood)
            if (city) onChange('address_city', city)
            if (uf) onChange('address_state', uf)
        }
    }

    return (
        <div className="space-y-3">
            <div className="grid grid-cols-3 gap-3">
                <div className="relative">
                    <Input
                        label="CEP"
                        value={value.address_zip || ''}
                        onChange={e => onChange('address_zip', maskCep(e.target.value.slice(0, 9)))}
                        onBlur={handleCepBlur}
                        maxLength={9}
                        placeholder="00000-000"
                    />
                    {viaCep.loading && <Loader2 className="absolute right-2 top-8 h-4 w-4 animate-spin text-brand-500" />}
                </div>
                <div className="col-span-2">
                    <Input label="Rua" value={value.address_street || ''} onChange={e => onChange('address_street', e.target.value)} />
                </div>
            </div>
            <div className="grid grid-cols-3 gap-3">
                <Input label="Número" value={value.address_number || ''} onChange={e => onChange('address_number', e.target.value)} />
                <Input label="Complemento" value={value.address_complement || ''} onChange={e => onChange('address_complement', e.target.value)} />
                <Input label="Bairro" value={value.address_neighborhood || ''} onChange={e => onChange('address_neighborhood', e.target.value)} />
            </div>
            <div className="grid grid-cols-2 gap-3">
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-surface-700">UF</label>
                    <select
                        value={value.address_state || ''}
                        onChange={e => {
                            onChange('address_state', e.target.value)
                            onChange('address_city', '')
                        }}
                        aria-label="UF (estado)"
                        className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                        <option value="">Selecione</option>
                        {(ibgeStates || []).map(s => <option key={s.abbr} value={s.abbr}>{s.abbr} — {s.name}</option>)}
                    </select>
                    {statesError && (
                        <p className="text-xs text-amber-600 mt-1 flex items-center gap-1">
                            Não foi possível carregar os estados.
                            <button type="button" onClick={() => refetchStates()} className="underline font-medium">Tentar de novo</button>
                        </p>
                    )}
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-surface-700">Cidade</label>
                    <select
                        value={value.address_city || ''}
                        onChange={e => onChange('address_city', e.target.value)}
                        aria-label="Cidade"
                        disabled={!value.address_state}
                        className="w-full rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 disabled:opacity-50 disabled:cursor-not-allowed">
                        <option value="">{value.address_state ? 'Selecione' : 'Selecione o UF primeiro'}</option>
                        {value.address_city && !ibgeCities.some(c => c.name === value.address_city) && (
                            <option value={value.address_city}>{value.address_city}</option>
                        )}
                        {(ibgeCities || []).map(c => <option key={c.id} value={c.name}>{c.name}</option>)}
                    </select>
                </div>
            </div>
        </div>
    )
}
