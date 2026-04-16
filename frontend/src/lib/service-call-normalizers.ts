import { unwrapData } from '@/lib/api'

type Assignee = {
  id: number
  name: string
  email: string
}

type ServiceCallAssigneesResponse = {
  technicians: Assignee[]
  drivers: Assignee[]
}

type AuditLogEntry = {
  id: number
  action: string
  action_label?: string
  description: string
  user?: { id: number; name: string } | null
  created_at?: string | null
}

export function unwrapServiceCallPayload<T>(response: { data?: { data?: T } | T }): T {
  return unwrapData(response)
}

export function unwrapServiceCallAssignees(
  response: { data?: { data?: ServiceCallAssigneesResponse } | ServiceCallAssigneesResponse }
): ServiceCallAssigneesResponse {
  return unwrapData(response)
}

export function unwrapServiceCallAuditLogs(
  response: { data?: { data?: AuditLogEntry[] } | AuditLogEntry[] }
): AuditLogEntry[] {
  const data = unwrapData(response)
  return Array.isArray(data) ? data : []
}

export function canAcceptServiceCall(status: string): boolean {
  return status === 'pending_scheduling'
}
