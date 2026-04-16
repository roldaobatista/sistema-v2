import { useQuery } from '@tanstack/react-query'
import api, { unwrapData } from '@/lib/api'
import { safeArray } from '@/lib/safe-array'

interface State {
    id: number
    abbr: string
    name: string
}

interface City {
    id: number
    name: string
}

// Fallback estático caso API IBGE esteja indisponível
const FALLBACK_STATES: State[] = [
    { id: 12, abbr: 'AC', name: 'Acre' },
    { id: 27, abbr: 'AL', name: 'Alagoas' },
    { id: 16, abbr: 'AP', name: 'Amapá' },
    { id: 13, abbr: 'AM', name: 'Amazonas' },
    { id: 29, abbr: 'BA', name: 'Bahia' },
    { id: 23, abbr: 'CE', name: 'Ceará' },
    { id: 53, abbr: 'DF', name: 'Distrito Federal' },
    { id: 32, abbr: 'ES', name: 'Espírito Santo' },
    { id: 52, abbr: 'GO', name: 'Goiás' },
    { id: 21, abbr: 'MA', name: 'Maranhão' },
    { id: 51, abbr: 'MT', name: 'Mato Grosso' },
    { id: 50, abbr: 'MS', name: 'Mato Grosso do Sul' },
    { id: 31, abbr: 'MG', name: 'Minas Gerais' },
    { id: 15, abbr: 'PA', name: 'Pará' },
    { id: 25, abbr: 'PB', name: 'Paraíba' },
    { id: 41, abbr: 'PR', name: 'Paraná' },
    { id: 26, abbr: 'PE', name: 'Pernambuco' },
    { id: 22, abbr: 'PI', name: 'Piauí' },
    { id: 33, abbr: 'RJ', name: 'Rio de Janeiro' },
    { id: 24, abbr: 'RN', name: 'Rio Grande do Norte' },
    { id: 43, abbr: 'RS', name: 'Rio Grande do Sul' },
    { id: 11, abbr: 'RO', name: 'Rondônia' },
    { id: 14, abbr: 'RR', name: 'Roraima' },
    { id: 42, abbr: 'SC', name: 'Santa Catarina' },
    { id: 35, abbr: 'SP', name: 'São Paulo' },
    { id: 28, abbr: 'SE', name: 'Sergipe' },
    { id: 17, abbr: 'TO', name: 'Tocantins' },
]

export function useIbgeStates() {
    return useQuery<State[]>({
        queryKey: ['ibge', 'states'],
        queryFn: async () => {
            try {
                const response = await api.get('/external/states')
                const states = safeArray<State>(unwrapData(response))
                if (Array.isArray(states) && states.length > 0) {
                    return states
                }
                return FALLBACK_STATES
            } catch {
                return FALLBACK_STATES
            }
        },
        staleTime: 1000 * 60 * 60 * 24, // 24h cache
    })
}

export function useIbgeCities(uf: string) {
    return useQuery<City[]>({
        queryKey: ['ibge', 'cities', uf],
        queryFn: async () => {
            const response = await api.get(`/external/states/${uf}/cities`)
            return safeArray<City>(unwrapData(response))
        },
        enabled: uf.length === 2,
        staleTime: 1000 * 60 * 60 * 24,
    })
}
