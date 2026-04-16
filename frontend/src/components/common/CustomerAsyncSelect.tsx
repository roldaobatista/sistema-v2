import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import { customerApi } from '@/lib/customer-api'
import { AsyncSelect, type AsyncSelectOption } from '@/components/ui/async-select'

export interface CustomerAsyncSelectItem {
    id: number
    name: string
    document?: string | null
    phone?: string | null
    phone2?: string | null
    email?: string | null
    address_city?: string | null
    address_state?: string | null
    address_street?: string | null
    address_number?: string | null
    address_neighborhood?: string | null
    latitude?: number | null
    longitude?: number | null
    google_maps_link?: string | null
}

interface CustomerAsyncSelectProps {
    label?: string
    customerId?: number | null
    initialCustomer?: CustomerAsyncSelectItem | null
    placeholder?: string
    disabled?: boolean
    onChange: (customer: CustomerAsyncSelectItem | null) => void
}

function buildCustomerSubLabel(customer: CustomerAsyncSelectItem): string {
    const cityState = [customer.address_city, customer.address_state].filter(Boolean).join('/')

    return [
        customer.document,
        customer.phone,
        customer.email,
        cityState || null,
    ]
        .filter(Boolean)
        .join(' — ')
}

export function toCustomerAsyncSelectOption(customer: CustomerAsyncSelectItem): AsyncSelectOption<CustomerAsyncSelectItem> {
    return {
        id: customer.id,
        label: customer.name,
        subLabel: buildCustomerSubLabel(customer),
        value: customer,
    }
}

export function CustomerAsyncSelect({
    label = 'Cliente',
    customerId = null,
    initialCustomer = null,
    placeholder = 'Buscar cliente por nome, documento, telefone ou e-mail...',
    disabled = false,
    onChange,
}: CustomerAsyncSelectProps) {
    const shouldFetchCustomer = !!customerId && (!initialCustomer || initialCustomer.id !== customerId)

    const { data: hydratedCustomer } = useQuery({
        queryKey: ['customer-async-select', customerId],
        queryFn: () => customerApi.detail(customerId!),
        enabled: shouldFetchCustomer,
        staleTime: 5 * 60_000,
    })

    const selectedCustomer = useMemo(() => {
        if (initialCustomer && (!customerId || initialCustomer.id === customerId)) {
            return initialCustomer
        }

        return hydratedCustomer ?? null
    }, [customerId, hydratedCustomer, initialCustomer])

    const initialOption = selectedCustomer ? toCustomerAsyncSelectOption(selectedCustomer) : null

    return (
        <AsyncSelect<CustomerAsyncSelectItem, CustomerAsyncSelectItem>
            label={label}
            endpoint="/customers"
            placeholder={placeholder}
            value={customerId}
            disabled={disabled}
            initialOption={initialOption}
            onChange={(option) => onChange(option?.value ?? null)}
            mapData={(data) => data.map(toCustomerAsyncSelectOption)}
        />
    )
}
