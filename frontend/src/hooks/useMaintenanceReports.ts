import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { maintenanceReportApi } from '@/lib/maintenance-report-api'
import type { MaintenanceReportPayload } from '@/lib/maintenance-report-api'
import { safeArray } from '@/lib/safe-array'
import { unwrapData } from '@/lib/api'
import { queryKeys } from '@/lib/query-keys'
import { toast } from 'sonner'
import type { AxiosError } from 'axios'
import type { MaintenanceReport } from '@/types/work-order'

function handleMutationError(error: unknown) {
    const err = error as AxiosError<{ message?: string }>
    toast.error(err?.response?.data?.message || 'Erro na operação')
}

export function useMaintenanceReports(workOrderId: number) {
    return useQuery({
        queryKey: queryKeys.maintenanceReports.list(workOrderId),
        queryFn: async () => {
            const response = await maintenanceReportApi.list({ work_order_id: workOrderId })
            return safeArray<MaintenanceReport>(unwrapData(response))
        },
        enabled: !!workOrderId,
    })
}

export function useCreateMaintenanceReport() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: async (data: MaintenanceReportPayload) => {
            const response = await maintenanceReportApi.create(data)
            return unwrapData<MaintenanceReport>(response)
        },
        onSuccess: (_data, variables) => {
            queryClient.invalidateQueries({ queryKey: queryKeys.maintenanceReports.list(variables.work_order_id) })
            queryClient.invalidateQueries({ queryKey: queryKeys.workOrders.detail(variables.work_order_id) })
            toast.success('Relatório de manutenção criado com sucesso')
        },
        onError: handleMutationError,
    })
}

export function useUpdateMaintenanceReport() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: async ({ id, data }: { id: number; data: Partial<MaintenanceReportPayload> }) => {
            const response = await maintenanceReportApi.update(id, data)
            return unwrapData<MaintenanceReport>(response)
        },
        onSuccess: (result) => {
            if (result?.work_order_id) {
                queryClient.invalidateQueries({ queryKey: queryKeys.maintenanceReports.list(result.work_order_id) })
                queryClient.invalidateQueries({ queryKey: queryKeys.workOrders.detail(result.work_order_id) })
            }
            toast.success('Relatório de manutenção atualizado')
        },
        onError: handleMutationError,
    })
}

export function useApproveMaintenanceReport() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: async ({ id, workOrderId }: { id: number; workOrderId: number }) => {
            const response = await maintenanceReportApi.approve(id)
            return { ...unwrapData<MaintenanceReport>(response), _workOrderId: workOrderId }
        },
        onSuccess: (_data, variables) => {
            queryClient.invalidateQueries({ queryKey: queryKeys.maintenanceReports.list(variables.workOrderId) })
            queryClient.invalidateQueries({ queryKey: queryKeys.workOrders.detail(variables.workOrderId) })
            toast.success('Relatório aprovado com sucesso')
        },
        onError: handleMutationError,
    })
}

export function useDeleteMaintenanceReport() {
    const queryClient = useQueryClient()

    return useMutation({
        mutationFn: async ({ id, workOrderId }: { id: number; workOrderId: number }) => {
            await maintenanceReportApi.destroy(id)
            return { workOrderId }
        },
        onSuccess: (_data, variables) => {
            queryClient.invalidateQueries({ queryKey: queryKeys.maintenanceReports.list(variables.workOrderId) })
            queryClient.invalidateQueries({ queryKey: queryKeys.workOrders.detail(variables.workOrderId) })
            toast.success('Relatório excluído com sucesso')
        },
        onError: handleMutationError,
    })
}
