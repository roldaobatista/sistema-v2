import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { PerformanceReview, ContinuousFeedback } from '@/types/hr'
import { extractApiError } from '@/types/api'
import { toast } from 'sonner'

type FeedbackApiType = 'praise' | 'suggestion' | 'concern'

function unwrapPayload<T>(response: { data?: { data?: T } | T }): T | undefined {
    const payload = response?.data

    if (payload != null && typeof payload === 'object' && 'data' in payload) {
        return (payload as { data?: T }).data
    }

    return payload as T | undefined
}

function extractYearFromCycle(cycle?: string): number | undefined {
    if (!cycle) {
        return undefined
    }

    const match = cycle.match(/^(\d{4})/)
    return match ? Number(match[1]) : undefined
}

function normalizeFeedbackType(type?: ContinuousFeedback['type']): FeedbackApiType | undefined {
    if (type === 'guidance') {
        return 'suggestion'
    }

    if (type === 'correction') {
        return 'concern'
    }

    return type
}

export function usePerformance() {
    const qc = useQueryClient()

    const { data: reviews, isLoading: loadingReviews } = useQuery<PerformanceReview[]>({
        queryKey: ['hr-reviews'],
        queryFn: () => api.get('/hr/performance-reviews').then((response) => unwrapPayload<PerformanceReview[]>(response) ?? []),
    })

    const createReview = useMutation({
        mutationFn: (data: Partial<PerformanceReview>) => {
            const payload = {
                ...data,
                year: data.year ?? extractYearFromCycle(data.cycle),
            }

            return api.post('/hr/performance-reviews', payload)
        },
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['hr-reviews'] })
            toast.success('Avaliação criada')
        },
        onError: (err: unknown) => toast.error(extractApiError(err, 'Erro ao criar avaliação')),
    })

    const updateReview = useMutation({
        mutationFn: ({ id, data }: { id: number; data: Partial<PerformanceReview> }) =>
            api.put(`/hr/performance-reviews/${id}`, data),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['hr-reviews'] })
            toast.success('Avaliação atualizada')
        },
        onError: (err: unknown) => toast.error(extractApiError(err, 'Erro ao atualizar avaliação')),
    })

    const { data: feedbackList, isLoading: loadingFeedback } = useQuery<ContinuousFeedback[]>({
        queryKey: ['hr-feedback'],
        queryFn: async () => {
            const feedback = unwrapPayload<ContinuousFeedback[]>(await api.get('/hr/continuous-feedback')) ?? []

            return feedback.map((item) => ({
                ...item,
                type: item.type,
            }))
        },
    })

    const sendFeedback = useMutation({
        mutationFn: (data: Partial<ContinuousFeedback>) => api.post('/hr/continuous-feedback', {
            to_user_id: data.to_user_id,
            type: normalizeFeedbackType(data.type) ?? 'suggestion',
            visibility: data.visibility ?? 'public',
            content: data.content ?? data.message,
        }),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['hr-feedback'] })
            toast.success('Feedback enviado!')
        },
        onError: (err: unknown) => toast.error(extractApiError(err, 'Erro ao enviar feedback')),
    })

    return {
        reviews,
        loadingReviews,
        createReview,
        updateReview,
        feedbackList,
        loadingFeedback,
        sendFeedback,
    }
}

export function useReview(id: number) {
    return useQuery<PerformanceReview>({
        queryKey: ['hr-review', id],
        queryFn: () => api.get(`/hr/performance-reviews/${id}`).then((response) => unwrapPayload<PerformanceReview>(response)),
        enabled: !!id,
    })
}
