import { unwrapData } from '@/lib/api'

const PRIVILEGED_FIELD_ROLES = new Set(['super_admin', 'admin', 'gerente'])

type WorkOrderFieldScope = {
    assigned_to?: number | string | null
    technicians?: unknown[] | null
    technician_ids?: Array<number | string> | null
}

export type WorkOrderQrProduct = {
    id: number
    name: string
    sell_price?: string | number | null
}

export function isPrivilegedFieldRole(roles: string[] = []): boolean {
    return roles.some((role) => PRIVILEGED_FIELD_ROLES.has(role))
}

export function isTechnicianLinkedToWorkOrder(
    workOrder: WorkOrderFieldScope | null | undefined,
    userId: number | null | undefined,
    isPrivileged = false,
): boolean {
    if (isPrivileged) {
        return true
    }

    const normalizedUserId = Number(userId)
    if (!workOrder || !Number.isFinite(normalizedUserId) || normalizedUserId <= 0) {
        return false
    }

    if (Number(workOrder.assigned_to) === normalizedUserId) {
        return true
    }

    if ((workOrder.technician_ids ?? []).some((technicianId) => Number(technicianId) === normalizedUserId)) {
        return true
    }

    return (workOrder.technicians ?? []).some((technician) => {
        if (!technician || typeof technician !== 'object' || Array.isArray(technician)) {
            return false
        }

        const candidateId = Number((technician as { id?: unknown }).id)
        return Number.isFinite(candidateId) && candidateId === normalizedUserId
    })
}

export function extractWorkOrderQrProduct(response: { data?: unknown } | null | undefined): WorkOrderQrProduct | null {
    const payload = unwrapData<unknown>(response)

    if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
        return null
    }

    const record = payload as Record<string, unknown>
    const id = Number(record.id)

    if (!Number.isFinite(id) || id <= 0) {
        return null
    }

    const sellPrice = typeof record.sell_price === 'string' || typeof record.sell_price === 'number'
        ? record.sell_price
        : typeof record.sale_price === 'string' || typeof record.sale_price === 'number'
            ? record.sale_price
            : null

    return {
        id,
        name: typeof record.name === 'string' ? record.name : '',
        sell_price: sellPrice,
    }
}
