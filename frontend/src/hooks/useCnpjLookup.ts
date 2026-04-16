import { useState, useCallback } from 'react'
import { toast } from 'sonner'
import api from '@/lib/api'

export interface CnpjResult {
    name: string
    trade_name: string
    email: string
    phone: string
    address_zip: string
    address_street: string
    address_number: string
    address_complement: string
    address_neighborhood: string
    address_city: string
    address_state: string
    codigo_municipio_ibge?: number
    state_registration: string
    city_registration: string
    status: string
    main_activity: string
    company_size: string
}

function s(v: unknown): string {
    return typeof v === 'string' ? v : ''
}

function normalizeBackendResponse(data: Record<string, unknown>): CnpjResult {
    return {
        name: s(data.name),
        trade_name: s(data.trade_name),
        email: s(data.email),
        phone: s(data.phone),
        address_zip: s(data.address_zip),
        address_street: s(data.address_street),
        address_number: s(data.address_number),
        address_complement: s(data.address_complement),
        address_neighborhood: s(data.address_neighborhood),
        address_city: s(data.address_city),
        address_state: s(data.address_state),
        codigo_municipio_ibge: typeof data.codigo_municipio_ibge === 'number' ? data.codigo_municipio_ibge : undefined,
        state_registration: s(data.state_registration),
        city_registration: s(data.city_registration),
        status: s(data.status || data.company_status),
        main_activity: s(data.main_activity || data.cnae_description),
        company_size: s(data.company_size),
    }
}

function normalizeBrasilApiResponse(raw: Record<string, unknown>): CnpjResult {
    const street = [raw.descricao_tipo_de_logradouro, raw.logradouro]
        .filter(Boolean).join(' ').trim()

    return {
        name: s(raw.razao_social),
        trade_name: s(raw.nome_fantasia),
        email: s(raw.email),
        phone: s(raw.ddd_telefone_1),
        address_zip: s(raw.cep),
        address_street: street,
        address_number: s(raw.numero),
        address_complement: s(raw.complemento),
        address_neighborhood: s(raw.bairro),
        address_city: s(raw.municipio),
        address_state: s(raw.uf),
        codigo_municipio_ibge: typeof raw.codigo_municipio_ibge === 'number' ? raw.codigo_municipio_ibge : undefined,
        state_registration: s(raw.inscricao_estadual || raw.estadual),
        city_registration: s(raw.inscricao_municipal || raw.municipal),
        status: s(raw.descricao_situacao_cadastral),
        main_activity: s(raw.cnae_fiscal_descricao),
        company_size: s(raw.porte || raw.descricao_porte),
    }
}

async function resolveCityNameByIbge(uf: string, codigoIbge: number): Promise<string | null> {
    try {
        const { data } = await api.get<Array<{ id: number; name: string }>>(`/external/states/${uf}/cities`)
        const city = data?.find((c) => c.id === codigoIbge)
        return city?.name ?? null
    } catch {
        try {
            const res = await fetch(`https://brasilapi.com.br/api/ibge/municipios/v1/${uf}`, {
                signal: AbortSignal.timeout(8000),
            })
            if (!res.ok) return null
            const cities = (await res.json()) as Array<{ id?: number; codigo_ibge?: string; nome?: string; name?: string }>
            const city = cities?.find(
                (c) =>
                    Number(c.codigo_ibge) === codigoIbge ||
                    c.id === codigoIbge ||
                    String(c.codigo_ibge) === String(codigoIbge),
            )
            return city?.nome ?? city?.name ?? null
        } catch {
            return null
        }
    }
}

async function fetchDirectFromBrasilApi(cnpj: string): Promise<CnpjResult | null> {
    const res = await fetch(`https://brasilapi.com.br/api/cnpj/v1/${cnpj}`, {
        signal: AbortSignal.timeout(10000),
    })
    if (!res.ok) return null
    const raw = await res.json()
    if (!raw || raw.message) return null
    return normalizeBrasilApiResponse(raw)
}

export function useCnpjLookup() {
    const [loading, setLoading] = useState(false)

    const lookup = useCallback(async (cnpj: string): Promise<CnpjResult | null> => {
        const clean = cnpj.replace(/\D/g, '')
        if (clean.length !== 14) return null

        setLoading(true)
        try {
            let result: CnpjResult | null = null
            try {
                const { data } = await api.get(`/external/cnpj/${clean}`)
                result = normalizeBackendResponse(data)
            } catch {
                const direct = await fetchDirectFromBrasilApi(clean)
                result = direct
            }

            if (!result) {
                toast.error('CNPJ não encontrado. Verifique os dígitos e tente novamente.')
                return null
            }

            if (result.codigo_municipio_ibge && result.address_state) {
                const resolvedCity = await resolveCityNameByIbge(
                    result.address_state,
                    result.codigo_municipio_ibge,
                )
                if (resolvedCity) result.address_city = resolvedCity
            }

            return result
        } catch {
            toast.error('CNPJ não encontrado. Verifique os dígitos e tente novamente.')
            return null
        } finally {
            setLoading(false)
        }
    }, [])

    return { lookup, loading }
}
