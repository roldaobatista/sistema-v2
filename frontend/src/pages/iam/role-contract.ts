import { unwrapData } from '@/lib/api'
import type { PermissionGroup, Role } from '@/types/iam'

type ApiPayload<T> = { data?: { data?: T } | T } | null | undefined

export function normalizeRoleList(response: ApiPayload<Role[]>): Role[] {
    const payload = unwrapData<Role[] | Role>(response)

    return Array.isArray(payload) ? payload : []
}

export function normalizePermissionGroups(response: ApiPayload<PermissionGroup[]>): PermissionGroup[] {
    const payload = unwrapData<PermissionGroup[] | PermissionGroup>(response)

    return Array.isArray(payload) ? payload : []
}

export function normalizeRoleDetail(response: ApiPayload<Role>): Role {
    return unwrapData<Role>(response)
}
