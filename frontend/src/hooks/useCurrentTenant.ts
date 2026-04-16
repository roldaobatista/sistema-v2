import { useAuthStore } from '@/stores/auth-store'
import { useQuery, useMutation } from '@tanstack/react-query'
import { toast } from 'sonner'
import api, { getApiErrorMessage, useAuthCookie } from '@/lib/api'
import { clearAuthenticatedQueryCache } from '@/lib/query-client'

/**
 * Hook para gerenciar o tenant (empresa) ativo e listar tenants disponíveis.
 */
export function useCurrentTenant() {
    const { user, tenant, fetchMe } = useAuthStore()

    const { data: tenantsRes } = useQuery({
        queryKey: ['my-tenants'],
        queryFn: () => api.get('/my-tenants'),
        enabled: !!user,
        retry: 1,
        retryDelay: 5000,
        staleTime: 10 * 60 * 1000,
        gcTime: 30 * 60 * 1000,
        refetchOnWindowFocus: false,
    })

    const switchMut = useMutation({
        mutationFn: (tenantId: number) => api.post('/switch-tenant', { tenant_id: tenantId }),
        onSuccess: async (res) => {
            const payload = res?.data?.data ?? res?.data
            const newToken = payload?.token
            if (newToken) {
                if (!useAuthCookie) {
                    localStorage.setItem('auth_token', newToken)
                }
                useAuthStore.setState({ token: newToken })
            }
            clearAuthenticatedQueryCache()
            try {
                await fetchMe()
                toast.success('Empresa alterada com sucesso!')
            } catch {
                toast.error('Empresa alterada, mas não foi possível atualizar os dados. Recarregue a página.')
            }
        },
        onError: (err: unknown) => {
            toast.error(getApiErrorMessage(err, 'Erro ao trocar de empresa.'))
        },
    })

    return {
        currentTenant: tenant,
        tenants: ((tenantsRes?.data?.data ?? tenantsRes?.data) ?? []) as Array<{ id: number; name: string; document: string | null; status: string }>,
        switchTenant: switchMut.mutate,
        isSwitching: switchMut.isPending,
    }
}
