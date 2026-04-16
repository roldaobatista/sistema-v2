import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api, { unwrapData } from '@/lib/api'
import { safeArray } from '@/lib/safe-array'
import { toast } from 'sonner'
import type { AxiosError } from 'axios'

function handleMutationError(error: unknown) {
    const err = error as AxiosError<{ message?: string }>
    toast.error(err?.response?.data?.message || 'Erro na operação')
}

export interface Candidate {
    id: string
    job_posting_id: string
    name: string
    email: string
    phone?: string
    resume_path?: string
    stage: 'applied' | 'screening' | 'interview' | 'technical_test' | 'offer' | 'hired' | 'rejected'
    notes?: string
    rating?: number
    rejected_reason?: string
    created_at: string
}

export interface JobPosting {
    id: string
    title: string
    department_id?: string
    position_id?: string
    description: string
    requirements?: string
    salary_range_min?: number
    salary_range_max?: number
    status: 'open' | 'closed' | 'on_hold'
    opened_at?: string
    closed_at?: string
    department?: { name: string }
    position?: { name: string }
    candidates?: Candidate[]
}

export function useRecruitment() {
    const queryClient = useQueryClient()

    const jobs = useQuery({
        queryKey: ['hr-jobs'],
        queryFn: async () => {
            const response = await api.get('/hr/job-postings')
            return safeArray<JobPosting>(unwrapData(response))
        }
    })

    const createJob = useMutation({
        mutationFn: async (data: Partial<JobPosting>) => {
            const response = await api.post('/hr/job-postings', data)
            return unwrapData<JobPosting>(response)
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr-jobs'] })
        },
        onError: handleMutationError
    })

    const updateJob = useMutation({
        mutationFn: async ({ id, data }: { id: string; data: Partial<JobPosting> }) => {
            const response = await api.put(`/hr/job-postings/${id}`, data)
            return unwrapData<JobPosting>(response)
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr-jobs'] })
        },
        onError: handleMutationError
    })

    const deleteJob = useMutation({
        mutationFn: async (id: string) => {
            await api.delete(`/hr/job-postings/${id}`)
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['hr-jobs'] })
        },
        onError: handleMutationError
    })

    return {
        jobs: jobs.data,
        isLoading: jobs.isLoading,
        error: jobs.error,
        createJob,
        updateJob,
        deleteJob
    }
}
