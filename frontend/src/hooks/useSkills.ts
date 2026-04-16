import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { Skill } from '@/types/hr'
import { extractApiError } from '@/types/api'
import { toast } from 'sonner'

interface UserSkillAssessment {
    skill_id: number
    current_level: number
}

interface SkillMatrixUser {
    id: number
    name: string
    position?: { id: number; name: string }
    skills?: UserSkillAssessment[]
}

function unwrapPayload<T>(response: { data?: { data?: T } | T }): T | undefined {
    const payload = response?.data

    if (payload != null && typeof payload === 'object' && 'data' in payload) {
        return (payload as { data?: T }).data
    }

    return payload as T | undefined
}

function normalizeMatrixUser(user: Record<string, unknown>): SkillMatrixUser {
    const position = user.position
    const skills = Array.isArray(user.skills) ? user.skills : []

    return {
        id: Number(user.id),
        name: String(user.name ?? ''),
        position: typeof position === 'string'
            ? { name: position }
            : (position as SkillMatrixUser['position'] | undefined),
        skills: skills.map((item) => {
            const assessment = item as Record<string, unknown>

            return {
                skill_id: Number(assessment.skill_id),
                current_level: Number(assessment.current_level ?? assessment.current ?? 0),
                assessed_at: typeof assessment.assessed_at === 'string' ? assessment.assessed_at : undefined,
            }
        }),
    }
}

export function useSkills() {
    const qc = useQueryClient()

    const { data: skills, isLoading: loadingSkills } = useQuery<Skill[]>({
        queryKey: ['hr-skills'],
        queryFn: () => api.get('/hr/skills').then((response) => unwrapPayload<Skill[]>(response) ?? []),
    })

    const { data: matrix, isLoading: loadingMatrix } = useQuery<SkillMatrixUser[]>({
        queryKey: ['hr-skills-matrix'],
        queryFn: async () => {
            const users = unwrapPayload<Record<string, unknown>[]>(await api.get('/hr/skills-matrix')) ?? []
            return users.map(normalizeMatrixUser)
        },
    })

    const createSkill = useMutation({
        mutationFn: (data: Partial<Skill>) => api.post('/hr/skills', data),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['hr-skills'] })
            toast.success('Competência criada')
        },
        onError: (err: unknown) => toast.error(extractApiError(err, 'Erro ao criar competência')),
    })

    const updateSkill = useMutation({
        mutationFn: ({ id, data }: { id: number; data: Partial<Skill> }) =>
            api.put(`/hr/skills/${id}`, data),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['hr-skills'] })
            toast.success('Competência atualizada')
        },
        onError: (err: unknown) => toast.error(extractApiError(err, 'Erro ao atualizar competência')),
    })

    const deleteSkill = useMutation({
        mutationFn: (id: number) => api.delete(`/hr/skills/${id}`),
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['hr-skills'] })
            toast.success('Competência removida')
        },
        onError: (err: unknown) => toast.error(extractApiError(err, 'Erro ao remover competência')),
    })

    const assessUser = useMutation({
        mutationFn: async ({ userId, skills }: { userId: number; skills: { skill_id: number; level: number }[] }) => {
            const responses = []

            for (const skill of skills) {
                responses.push(await api.post(`/hr/skills/assess/${userId}`, skill))
            }

            return responses
        },
        onSuccess: () => {
            qc.invalidateQueries({ queryKey: ['hr-skills-matrix'] })
            toast.success('Avaliação registrada')
        },
        onError: (err: unknown) => toast.error(extractApiError(err, 'Erro ao registrar avaliação')),
    })

    return {
        skills,
        loadingSkills,
        matrix,
        loadingMatrix,
        createSkill,
        updateSkill,
        deleteSkill,
        assessUser,
    }
}
