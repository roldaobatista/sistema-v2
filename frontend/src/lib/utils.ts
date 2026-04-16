import { clsx, type ClassValue } from 'clsx'
import { twMerge } from 'tailwind-merge'
import { getApiErrorMessage as getApiErrorMessageFromApi } from './api'

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs))
}



export function formatCurrency(value: number | string): string {
    const n = typeof value === 'string' ? parseFloat(value) : value
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n || 0)
}

const BRAZILIAN_STATES = new Set([
    'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS',
    'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC',
    'SP', 'SE', 'TO',
])

/**
 * Tenta extrair cidade e UF de um endereço completo quando os campos
 * address_city/address_state estão vazios.
 * Padrões reconhecidos: "..., Cidade - UF, Brasil", "..., Cidade - UF", "..., Cidade/UF"
 */
export function parseCityStateFromAddress(address: string | null | undefined): { city?: string; state?: string } {
    if (!address) return {}

    // "..., Cidade - UF, Brasil" ou "..., Cidade - UF"
    const dashMatch = address.match(/,\s*([^,]+?)\s*[-–]\s*([A-Z]{2})(?:\s*,\s*Brasil)?$/i)
    if (dashMatch) {
        const uf = dashMatch[2].toUpperCase()
        if (BRAZILIAN_STATES.has(uf)) return { city: dashMatch[1].trim(), state: uf }
    }

    // "..., Cidade/UF"
    const slashMatch = address.match(/,\s*([^,]+?)\/([A-Z]{2})(?:\s*,\s*Brasil)?$/i)
    if (slashMatch) {
        const uf = slashMatch[2].toUpperCase()
        if (BRAZILIAN_STATES.has(uf)) return { city: slashMatch[1].trim(), state: uf }
    }

    return {}
}

/** Mantém compatibilidade com imports antigos, usando a extração centralizada da API. */
export function getApiErrorMessage(err: unknown, fallback: string): string {
    return getApiErrorMessageFromApi(err, fallback)
}

export function formatDate(date: string | Date | null | undefined): string {
    if (!date) return '-'
    const d = typeof date === 'string' ? new Date(date) : date
    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(d)
}
