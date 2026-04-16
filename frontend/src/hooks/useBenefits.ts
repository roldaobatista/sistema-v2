import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { toast } from 'sonner'
import type { AxiosError } from 'axios'

function handleMutationError(error: unknown) {
    const err = error as AxiosError<{ message?: string }>
    toast.error(err?.response?.data?.message || 'Erro na operação')
}

export interface EmployeeBenefit {
    id: string
    tenant_id: string
    user_id: string
    user?: {
        id: string
        name: string
    }
    type: 'vt' | 'vr' | 'va' | 'health' | 'dental' | 'life_insurance' | 'other'
    provider?: string
    value: number
    employee_contribution: number
    start_date: string
    end_date?: string
    is_active: boolean
    notes?: string
    created_at: string
    updated_at: string
}

export interface BenefitFilters {
    user_id?: string
    type?: string
}

type BenefitPaginationMeta = {
    current_page?: number
    last_page?: number
    per_page?: number
    total?: number
}

type BenefitQueryData =
    | { data?: EmployeeBenefit[]; meta?: BenefitPaginationMeta }
    | (EmployeeBenefit[] & { __pagination?: BenefitPaginationMeta })

export function useBenefits(filters?: BenefitFilters) {
    const queryClient = useQueryClient()

    const { data, isLoading, error } = useQuery<BenefitQueryData>({
        queryKey: ['hr-benefits', filters],
        queryFn: async () => {
            const params = new URLSearchParams()
            if (filters?.user_id) params.append('user_id', filters.user_id)
            if (filters?.type) params.append('type', filters.type)

            const response = await api.get(`/hr/benefits?${params.toString()}`)
            return response.data
        }
    })

    const createBenefit = useMutation({
        mutationFn: async (data: Partial<EmployeeBenefit>) => {
            const response = await api.post('/hr/benefits', data)
            return response.data
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr-benefits'] })
        },
        onError: handleMutationError
    })

    const updateBenefit = useMutation({
        mutationFn: async ({ id, data }: { id: string, data: Partial<EmployeeBenefit> }) => {
            const response = await api.put(`/hr/benefits/${id}`, data)
            return response.data
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr-benefits'] })
        },
        onError: handleMutationError
    })

    const deleteBenefit = useMutation({
        mutationFn: async (id: string) => {
            await api.delete(`/hr/benefits/${id}`)
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr-benefits'] })
        },
        onError: handleMutationError
    })

    const normalizedBenefits = Array.isArray(data)
        ? data
        : (data?.data ?? [])

    const normalizedMeta = Array.isArray(data)
        ? (data as EmployeeBenefit[] & { __pagination?: BenefitPaginationMeta }).__pagination
        : data?.meta

    return {
        benefits: normalizedBenefits,
        meta: normalizedMeta,
        isLoading,
        error,
        createBenefit,
        updateBenefit,
        deleteBenefit
    }
}
