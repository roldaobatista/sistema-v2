import type {
  StandardWeight,
  StandardWeightConstants,
  StandardWeightExpiringSummary,
} from '@/types/equipment'

type PaginatedStandardWeightPayload = {
  data?: StandardWeight[]
  total?: number
  last_page?: number
}

export function getStandardWeightStatusLabel(
  statuses: StandardWeightConstants['statuses'] | undefined,
  status: string,
): string {
  const entry = statuses?.[status]

  if (typeof entry === 'string') {
    return entry
  }

  if (entry && typeof entry === 'object' && typeof entry.label === 'string') {
    return entry.label
  }

  return status
}

export function normalizeStandardWeightsPage(
  payload: PaginatedStandardWeightPayload | undefined,
): {
  weights: StandardWeight[]
  total: number
  lastPage: number
} {
  return {
    weights: payload?.data ?? [],
    total: payload?.total ?? 0,
    lastPage: payload?.last_page ?? 1,
  }
}

export function normalizeStandardWeightSummary(
  payload: StandardWeightExpiringSummary | undefined,
): StandardWeightExpiringSummary {
  return {
    expiring: payload?.expiring ?? [],
    expired: payload?.expired ?? [],
    expiring_count: payload?.expiring_count ?? 0,
    expired_count: payload?.expired_count ?? 0,
  }
}
