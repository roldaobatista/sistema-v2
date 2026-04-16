import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
    Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList,
} from '@/components/ui/command'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Button } from '@/components/ui/button'
import { ChevronsUpDown, Check, Plus, Loader2 } from 'lucide-react'
import { cn } from '@/lib/utils'
import { toast } from 'sonner'
import api, { getApiErrorMessage, unwrapData } from '@/lib/api'
import { safeArray } from '@/lib/safe-array'

interface LookupItem {
    id: number
    name: string
    slug?: string
    code?: string
    is_active?: boolean
}

type LookupResponse = LookupItem[] | { data?: LookupItem[] }

interface Props {
    lookupType: string
    value: string
    onChange: (value: string) => void
    placeholder?: string
    className?: string
    label?: string
    endpoint?: string
    nameField?: string
    valueField?: string
    allowCreate?: boolean
}

function slugify(str: string) {
    return str.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '')
}

const DEFAULT_VALUE_FIELD_BY_LOOKUP_TYPE: Record<string, string> = {
    'payment-methods': 'code',
    'service-types': 'slug',
    'lead-sources': 'slug',
    'quote-sources': 'slug',
    'payment-terms': 'slug',
    'bank-account-types': 'slug',
    'fleet-vehicle-types': 'slug',
    'fleet-fuel-types': 'slug',
    'fleet-vehicle-statuses': 'slug',
    'fueling-fuel-types': 'slug',
    'inmetro-seal-types': 'slug',
    'inmetro-seal-statuses': 'slug',
    'tv-camera-types': 'slug',
    'onboarding-template-types': 'slug',
    'follow-up-channels': 'slug',
    'follow-up-statuses': 'slug',
    'price-table-adjustment-types': 'slug',
    'automation-report-types': 'slug',
    'automation-report-frequencies': 'slug',
    'automation-report-formats': 'slug',
    'supplier-contract-payment-frequencies': 'slug',
}

export function LookupCombobox({ lookupType, value, onChange, placeholder, className, label, endpoint, nameField = 'name', valueField, allowCreate = true }: Props) {
    const qc = useQueryClient()
    const [open, setOpen] = useState(false)
    const [search, setSearch] = useState('')
    const [creating, setCreating] = useState(false)

    const apiPath = endpoint ?? `/lookups/${lookupType}`
    const cacheKey = endpoint ? ['custom-lookup', endpoint] : ['lookups', lookupType]

    const { data: items = [] } = useQuery<LookupItem[]>({
        queryKey: cacheKey,
        queryFn: async () => {
            const response = await api.get<LookupResponse>(apiPath)
            return safeArray<LookupItem>(unwrapData<LookupResponse>(response))
        },
        staleTime: 5 * 60_000,
    })

    const isPaymentMethods = lookupType === 'payment-methods'
    const resolvedValueField = valueField ?? DEFAULT_VALUE_FIELD_BY_LOOKUP_TYPE[lookupType]

    const createMut = useMutation({
        mutationFn: (name: string) => {
            if (isPaymentMethods) {
                return api.post('/payment-methods', { name, code: slugify(name), is_active: true })
            }
            return api.post(apiPath, { name, is_active: true })
        },
        onSuccess: (res) => {
            qc.invalidateQueries({ queryKey: cacheKey })
            const newItem = unwrapData<Record<string, unknown>>(res as { data?: unknown })
            const itemName = String(newItem[nameField] ?? newItem.name ?? search.trim())
            const emitValue = String(
                resolvedValueField
                    ? (newItem[resolvedValueField] ?? newItem[nameField] ?? newItem.name ?? '')
                    : (newItem[nameField] ?? newItem.name ?? ''),
            )
            onChange(emitValue)
            setSearch('')
            setCreating(false)
            setOpen(false)
            toast.success(`"${itemName}" cadastrado`)
        },
        onError: (err: unknown) => {
            toast.error(getApiErrorMessage(err, 'Erro ao cadastrar'))
            setCreating(false)
        },
    })

    const handleQuickCreate = () => {
        if (!search.trim()) return
        setCreating(true)
        createMut.mutate(search.trim())
    }

    const getName = (item: LookupItem) => item[nameField as keyof LookupItem] as string ?? item.name
    const getItemValue = (item: LookupItem) => resolvedValueField ? (item[resolvedValueField as keyof LookupItem] as string ?? getName(item)) : getName(item)
    const isActive = (item: LookupItem) => item.is_active !== false
    const exactMatch = items.some(i => getName(i).toLowerCase() === search.toLowerCase())

    return (
        <div>
            {label && <label className="mb-1 block text-xs font-medium text-surface-600">{label}</label>}
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        variant="outline"
                        role="combobox"
                        aria-expanded={open}
                        className={cn("justify-between font-normal text-left h-[38px]", className)}
                    >
                        <span className={cn("truncate", value ? "text-surface-900" : "text-surface-500")}>
                            {(resolvedValueField ? items.find(i => getItemValue(i) === value) : null)?.name ?? (value || (placeholder ?? 'Selecione...'))}
                        </span>
                        <ChevronsUpDown className="h-3.5 w-3.5 shrink-0 opacity-50" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-[280px] sm:w-[320px] p-0" align="start">
                    <Command filter={(val, srch) => {
                        const normalize = (s: string) => s.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "")
                        return normalize(val).includes(normalize(srch)) ? 1 : 0
                    }}>
                        <CommandInput
                            placeholder="Buscar ou digitar novo..."
                            className="h-9"
                            value={search}
                            onValueChange={setSearch}
                        />
                        <CommandList>
                            <CommandEmpty>
                                <div className="py-1 text-center">
                                    <p className="text-sm text-surface-500 mb-2">Nenhum resultado</p>
                                    {allowCreate && search.trim() && !exactMatch && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={handleQuickCreate}
                                            disabled={creating}
                                            className="text-xs"
                                        >
                                            {creating ? <Loader2 className="h-3 w-3 mr-1 animate-spin" /> : <Plus className="h-3 w-3 mr-1" />}
                                            Cadastrar "{search.trim()}"
                                        </Button>
                                    )}
                                </div>
                            </CommandEmpty>
                            <CommandGroup>
                                {items.filter(isActive).map((item) => {
                                    const itemName = getName(item)
                                    const itemValue = getItemValue(item)
                                    return (
                                        <CommandItem
                                            key={item.id}
                                            value={itemName}
                                            onSelect={() => {
                                                onChange(itemValue)
                                                setOpen(false)
                                                setSearch('')
                                            }}
                                        >
                                            <Check className={cn("mr-2 h-3.5 w-3.5 shrink-0", value === itemValue ? "opacity-100" : "opacity-0")} />
                                            {itemName}
                                        </CommandItem>
                                    )
                                })}
                            </CommandGroup>
                            {allowCreate && search.trim() && !exactMatch && items.length > 0 && (
                                <div className="border-t px-2 py-2">
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={handleQuickCreate}
                                        disabled={creating}
                                        className="w-full text-xs justify-start"
                                    >
                                        {creating ? <Loader2 className="h-3 w-3 mr-1 animate-spin" /> : <Plus className="h-3 w-3 mr-1" />}
                                        Cadastrar "{search.trim()}"
                                    </Button>
                                </div>
                            )}
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
        </div>
    )
}
