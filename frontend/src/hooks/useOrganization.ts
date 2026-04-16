import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api, { unwrapData } from '@/lib/api'
import { Department, Position } from '@/types/hr'
import { extractApiError } from '@/types/api'
import { toast } from 'sonner'

export function useOrganization() {
    const qc = useQueryClient()

    // Departments
    const { data: departments, isLoading: loadingDepts, isError: isErrorDepts, error: errorDepts, refetch: refetchDepts } = useQuery<Department[]>({
        queryKey: ['hr-departments'],
        queryFn: () => api.get('/hr/departments').then(r => unwrapData<Department[]>(r)),
    })

    const { data: orgChart, isLoading: loadingChart, isError: isErrorChart, error: errorChart, refetch: refetchChart } = useQuery<Department[]>({
        queryKey: ['hr-org-chart'],
        queryFn: () => api.get('/hr/org-chart').then(r => unwrapData<Department[]>(r)),
    })

    const createDept = useMutation({
        mutationFn: (data: Partial<Department>) => api.post('/hr/departments', data),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['hr-departments'] })
            qc.invalidateQueries({ queryKey: ['hr-org-chart'] })
            toast.success('Departamento criado com sucesso')
        },
        onError: (err: unknown) => toast.error(extractApiError(err, 'Erro ao criar departamento')),
    })

    const updateDept = useMutation({
        mutationFn: ({ id, data }: { id: number; data: Partial<Department> }) =>
            api.put(`/hr/departments/${id}`, data),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['hr-departments'] })
            qc.invalidateQueries({ queryKey: ['hr-org-chart'] })
            toast.success('Departamento atualizado')
        },
        onError: (err: unknown) => toast.error(extractApiError(err, 'Erro ao atualizar departamento')),
    })

    const deleteDept = useMutation({
        mutationFn: (id: number) => api.delete(`/hr/departments/${id}`),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['hr-departments'] })
            qc.invalidateQueries({ queryKey: ['hr-org-chart'] })
            toast.success('Departamento removido')
        },
        onError: (err: unknown) => toast.error(extractApiError(err, 'Erro ao remover departamento')),
    })

    // Positions
    const { data: positions, isLoading: loadingPositions, isError: isErrorPositions, error: errorPositions, refetch: refetchPositions } = useQuery<Position[]>({
        queryKey: ['hr-positions'],
        queryFn: () => api.get('/hr/positions').then(r => unwrapData<Position[]>(r)),
    })

    const createPosition = useMutation({
        mutationFn: (data: Partial<Position>) => api.post('/hr/positions', data),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['hr-positions'] })
            toast.success('Cargo criado com sucesso')
        },
        onError: (err: unknown) => toast.error(extractApiError(err, 'Erro ao criar cargo')),
    })

    const updatePosition = useMutation({
        mutationFn: ({ id, data }: { id: number; data: Partial<Position> }) =>
            api.put(`/hr/positions/${id}`, data),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['hr-positions'] })
            toast.success('Cargo atualizado')
        },
        onError: (err: unknown) => toast.error(extractApiError(err, 'Erro ao atualizar cargo')),
    })

    const deletePosition = useMutation({
        mutationFn: (id: number) => api.delete(`/hr/positions/${id}`),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['hr-positions'] })
            toast.success('Cargo removido')
        },
        onError: (err: unknown) => toast.error(extractApiError(err, 'Erro ao remover cargo')),
    })

    const isError = isErrorDepts || isErrorChart || isErrorPositions
    const error = errorDepts ?? errorChart ?? errorPositions

    const refetchAll = () => {
        void refetchDepts()
        void refetchChart()
        void refetchPositions()
    }

    return {
        departments,
        orgChart,
        loadingDepts,
        loadingChart,
        isErrorDepts,
        errorDepts,
        isErrorChart,
        errorChart,
        isErrorPositions,
        errorPositions,
        isError,
        error,
        refetchDepts,
        refetchChart,
        refetchPositions,
        refetchAll,
        createDept,
        updateDept,
        deleteDept,

        positions,
        loadingPositions,
        createPosition,
        updatePosition,
        deletePosition,
    }
}
